<?php

declare(strict_types=1);

namespace OCA\DownTranscoder\Service;

use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

class TranscodingQueueService {
    private IRootFolder $rootFolder;
    private IConfig $config;
    private LoggerInterface $logger;
    private MediaScannerService $scannerService;
    private TranscodingService $transcodingService;
    private MediaStateService $stateService;

    private const STATUS_KEY = 'transcode_status';

    public function __construct(
        IRootFolder $rootFolder,
        IConfig $config,
        LoggerInterface $logger,
        MediaScannerService $scannerService,
        TranscodingService $transcodingService,
        MediaStateService $stateService
    ) {
        $this->rootFolder = $rootFolder;
        $this->config = $config;
        $this->logger = $logger;
        $this->scannerService = $scannerService;
        $this->transcodingService = $transcodingService;
        $this->stateService = $stateService;
    }

    /**
     * Start transcoding all queued items
     *
     * @return bool Success
     */
    public function startTranscoding(): bool {
        // Get items from the new database-based system with state='queued'
        $queuedItems = $this->stateService->getMediaItemsByState('queued');

        if (empty($queuedItems)) {
            $this->logger->info("No items in transcode queue");
            return true;
        }

        // Get concurrent limit from settings (default: 1)
        $concurrentLimit = (int)$this->config->getAppValue('downtranscoder', 'concurrent_limit', '1');
        $concurrentLimit = max(1, min(10, $concurrentLimit)); // Clamp between 1 and 10

        // Limit items to process based on concurrent limit
        $itemsToProcess = array_slice($queuedItems, 0, $concurrentLimit);

        $this->setStatus([
            'is_transcoding' => true,
            'current_index' => 0,
            'total_items' => count($itemsToProcess),
            'started_at' => time(),
        ]);

        $this->logger->info("Starting transcoding of " . count($itemsToProcess) . " items (concurrent limit: {$concurrentLimit})");

        foreach ($itemsToProcess as $index => $item) {
            $this->setStatus([
                'is_transcoding' => true,
                'current_index' => $index + 1,
                'total_items' => count($itemsToProcess),
                'current_file' => $item['name'],
            ]);

            $this->transcodeFile($item['fileId']);
        }

        $this->setStatus([
            'is_transcoding' => false,
            'completed_at' => time(),
        ]);

        $this->logger->info("Transcoding completed");
        return true;
    }

    /**
     * Start transcoding a specific item immediately (for manual trigger)
     *
     * @param int $id Database record ID
     * @return bool Success
     */
    public function startTranscodingSingle(int $id): bool {
        // Get the specific media item by ID
        try {
            $allItems = $this->stateService->getAllMediaItems();
            $mediaItem = null;
            foreach ($allItems as $item) {
                if ($item['id'] === $id) {
                    $mediaItem = $item;
                    break;
                }
            }

            if ($mediaItem === null) {
                $this->logger->error("Media item {$id} not found for transcoding");
                return false;
            }

            // Ensure item is in 'transcoding' state (should already be set by frontend)
            if ($mediaItem['state'] !== 'transcoding') {
                $this->logger->warning("Media item {$id} is not in 'transcoding' state, current state: {$mediaItem['state']}");
                // Update to transcoding state
                $this->stateService->updateMediaState($id, 'transcoding');
            }

            $this->logger->info("Starting immediate transcoding of item {$id} (fileId: {$mediaItem['fileId']})");

            // Start transcoding immediately
            $success = $this->transcodeFile($mediaItem['fileId']);

            return $success;
        } catch (\Exception $e) {
            $this->logger->error("Error in startTranscodingSingle for item {$id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Transcode a single file
     *
     * @param int $fileId File ID
     * @return bool Success
     */
    private function transcodeFile(int $fileId): bool {
        $file = $this->scannerService->getFileById($fileId);
        if ($file === null) {
            $errorReason = "File no longer exists in Nextcloud (fileId: {$fileId}). The file may have been deleted, moved outside Nextcloud, or external storage was remounted. Please rescan your media to update the file list.";
            $this->logger->error($errorReason);

            // Update state to 'aborted' with reason
            $this->updateMediaStateToAborted($fileId, $errorReason);
            return false;
        }

        // Get media item to access preset
        $mediaItemData = null;
        $preset = null;
        try {
            $mediaItem = $this->stateService->getMediaItemsByState('queued');
            foreach ($mediaItem as $item) {
                if ($item['fileId'] === $fileId) {
                    $mediaItemData = $item;
                    $preset = $item['transcodePreset'] ?? null;
                    $this->stateService->updateMediaState($item['id'], 'transcoding');
                    break;
                }
            }
        } catch (\Exception $e) {
            $this->logger->warning("Could not update media state to transcoding: " . $e->getMessage());
        }

        $extension = strtolower(pathinfo($file->getName(), PATHINFO_EXTENSION));
        $inputPath = $file->getStorage()->getLocalFile($file->getInternalPath());

        if ($inputPath === false) {
            $errorReason = "Cannot get local path for file {$fileId}";
            $this->logger->error($errorReason);

            // Update state to 'aborted' with reason
            $this->updateMediaStateToAborted($fileId, $errorReason);
            return false;
        }

        // Debug logging for external storage issues
        $this->logger->debug("File {$fileId} path resolution: getLocalFile()={$inputPath}, getPath()={$file->getPath()}, getInternalPath()={$file->getInternalPath()}");

        // Verify file actually exists on disk and is readable
        $fileAccessible = file_exists($inputPath) && is_readable($inputPath);
        $usingTempFile = false;

        // Additional check: verify file size matches what Nextcloud thinks it is
        // This helps catch cases where Nextcloud's file cache is stale
        if ($fileAccessible) {
            $actualSize = filesize($inputPath);
            $expectedSize = $file->getSize();
            if ($actualSize !== $expectedSize) {
                $this->logger->warning("File size mismatch for {$fileId}: disk={$actualSize}, nextcloud={$expectedSize}. File may have been modified outside Nextcloud.");
            }
        }

        if (!$fileAccessible) {
            // For external storage, the path might not be accessible directly by PHP
            // Try to use Nextcloud's file stream wrapper as a workaround
            $storage = $file->getStorage();
            $storageId = $storage->getId();
            $storageClass = get_class($storage);

            $this->logger->warning("Direct file access failed for {$inputPath}. Storage: {$storageId} (class: {$storageClass}). Attempting Nextcloud stream wrapper for external storage...");

            try {
                // First, verify the file still exists in Nextcloud's view
                if (!$file->isReadable()) {
                    throw new \Exception("File is not readable in Nextcloud (permissions issue or storage unavailable)");
                }

                // Create a temporary file and copy content using Nextcloud's file abstraction with streaming
                $tempPath = sys_get_temp_dir() . '/nextcloud_transcode_' . $fileId . '.' . $extension;

                // Use Nextcloud's fopen to get a stream (more memory efficient for large files)
                $sourceStream = $file->fopen('r');
                if ($sourceStream === false) {
                    throw new \Exception("Failed to open file stream via Nextcloud (storage may be unavailable or disconnected)");
                }

                $destStream = fopen($tempPath, 'w');
                if ($destStream === false) {
                    fclose($sourceStream);
                    throw new \Exception("Failed to create temporary file");
                }

                // Stream copy (memory efficient)
                $copied = stream_copy_to_stream($sourceStream, $destStream);
                fclose($sourceStream);
                fclose($destStream);

                if ($copied === false) {
                    throw new \Exception("Failed to copy file content");
                }

                $this->logger->info("Created temporary file for transcoding: {$tempPath} ({$copied} bytes copied)");
                $inputPath = $tempPath;
                $fileAccessible = true;
                $usingTempFile = true;
            } catch (\Exception $e) {
                $storage = $file->getStorage();
                $storageId = $storage->getId();
                $storageClass = get_class($storage);

                $errorReason = "Cannot access file for transcoding. Direct path '{$inputPath}' not accessible and fallback failed: {$e->getMessage()}. Storage: {$storageId} ({$storageClass}). Troubleshooting steps: 1) Check if external storage is mounted and available in Nextcloud Files app, 2) Verify the external storage backend is running (SMB/NFS/etc), 3) Check that the file still exists at the original location, 4) Review Nextcloud external storage configuration in Admin settings.";
                $this->logger->error("File {$fileId} not accessible: {$errorReason}");

                // Update state to 'aborted' with reason
                $this->updateMediaStateToAborted($fileId, $errorReason);
                return false;
            }
        }

        // Create output path
        $outputPath = $inputPath . '.transcoded.' . $extension;

        $presetInfo = $preset ? " with preset '{$preset}'" : " with default settings";
        $this->logger->info("Transcoding file {$fileId}{$presetInfo}: {$inputPath} -> {$outputPath}");

        // Determine if it's a video or image
        $isVideo = in_array($extension, ['mp4', 'mkv', 'avi', 'mov', 'wmv', 'flv', 'webm', 'm4v', 'mpg', 'mpeg', 'ts', 'vob']);
        $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'webp']);

        $success = false;

        if ($isVideo) {
            // Get file owner for progress updates
            $owner = $file->getOwner();
            $ownerId = $owner ? $owner->getUID() : null;

            // Set up progress callback to update database
            if ($ownerId) {
                $this->transcodingService->setProgressCallback(function($progress) use ($fileId, $ownerId) {
                    $this->stateService->updateTranscodeProgress($fileId, $progress, $ownerId);
                    $this->logger->debug("Transcode progress for file {$fileId}: {$progress}%");
                });
            }

            $success = $this->transcodingService->transcodeVideo($inputPath, $outputPath, $preset);

            // Clear callback after transcode
            $this->transcodingService->setProgressCallback(null);
        } elseif ($isImage) {
            $success = $this->transcodingService->compressImage($inputPath, $outputPath);
        }

        if ($success) {
            $this->logger->info("Successfully transcoded file {$fileId}");

            // Update state to 'transcoded' in the database
            try {
                $mediaItem = $this->stateService->getMediaItemsByState('transcoding');
                foreach ($mediaItem as $item) {
                    if ($item['fileId'] === $fileId) {
                        $this->stateService->updateMediaState($item['id'], 'transcoded');
                        break;
                    }
                }
            } catch (\Exception $e) {
                $this->logger->warning("Could not update media state to transcoded: " . $e->getMessage());
            }

            // Check if auto-delete is enabled
            $autoDelete = $this->config->getAppValue('downtranscoder', 'auto_delete_originals', 'false') === 'true';
            if ($autoDelete) {
                $this->logger->info("Auto-delete enabled, replacing original file");
                // Replace original with transcoded version
                if (file_exists($outputPath)) {
                    rename($outputPath, $inputPath);
                }
            }
        } else {
            // Get error message from TranscodingService
            $errorReason = $this->transcodingService->getLastError() ?? "Unknown error during transcoding";
            $this->logger->error("Failed to transcode file {$fileId}: {$errorReason}");

            // Update state to 'aborted' with reason
            $this->updateMediaStateToAborted($fileId, $errorReason);
        }

        // Clean up temporary file if we created one for external storage
        if ($usingTempFile && file_exists($inputPath)) {
            $this->logger->debug("Cleaning up temporary file: {$inputPath}");
            unlink($inputPath);
        }

        return $success;
    }

    /**
     * Helper method to update media state to 'aborted' with a reason
     *
     * @param int $fileId File ID
     * @param string $errorReason Error message or reason for abort
     */
    private function updateMediaStateToAborted(int $fileId, string $errorReason): void {
        try {
            // Try to find the item in 'transcoding' state first
            $mediaItems = $this->stateService->getMediaItemsByState('transcoding');
            foreach ($mediaItems as $item) {
                if ($item['fileId'] === $fileId) {
                    $this->stateService->updateMediaState($item['id'], 'aborted', $errorReason);
                    return;
                }
            }

            // If not in transcoding, check queued state
            $mediaItems = $this->stateService->getMediaItemsByState('queued');
            foreach ($mediaItems as $item) {
                if ($item['fileId'] === $fileId) {
                    $this->stateService->updateMediaState($item['id'], 'aborted', $errorReason);
                    return;
                }
            }

            $this->logger->warning("Could not find media item with fileId {$fileId} to mark as aborted");
        } catch (\Exception $e) {
            $this->logger->warning("Could not update media state to aborted: " . $e->getMessage());
        }
    }

    /**
     * Get transcoding status
     *
     * @return array Status information
     */
    public function getStatus(): array {
        $statusJson = $this->config->getAppValue('downtranscoder', self::STATUS_KEY, '{}');
        $status = json_decode($statusJson, true) ?: [];

        // Get counts from the new database system
        $status['queued_items'] = count($this->stateService->getMediaItemsByState('queued'));
        $status['transcoding_items'] = count($this->stateService->getMediaItemsByState('transcoding'));
        $status['transcoded_items'] = count($this->stateService->getMediaItemsByState('transcoded'));
        $status['aborted_items'] = count($this->stateService->getMediaItemsByState('aborted'));

        return $status;
    }

    /**
     * Set transcoding status
     *
     * @param array $status Status data
     */
    private function setStatus(array $status): void {
        $statusJson = $this->config->getAppValue('downtranscoder', self::STATUS_KEY, '{}');
        $currentStatus = json_decode($statusJson, true) ?: [];
        $newStatus = array_merge($currentStatus, $status);
        $this->config->setAppValue('downtranscoder', self::STATUS_KEY, json_encode($newStatus));
    }

    /**
     * Clear transcoding status (abort all tasks)
     */
    public function clearStatus(): void {
        $this->config->setAppValue('downtranscoder', self::STATUS_KEY, json_encode([
            'is_transcoding' => false,
            'current_index' => 0,
            'total_items' => 0,
            'current_file' => null,
        ]));
    }

    /**
     * Delete original file after transcoding
     *
     * @param int $fileId Database ID (not file ID)
     * @return bool Success
     */
    public function deleteOriginal(int $id): bool {
        // Get the media item from database
        try {
            $items = $this->stateService->getMediaItemsByState('transcoded');
            $mediaItem = null;
            foreach ($items as $item) {
                if ($item['id'] === $id) {
                    $mediaItem = $item;
                    break;
                }
            }

            if ($mediaItem === null) {
                $this->logger->warning("Media item {$id} not found");
                return false;
            }

            $file = $this->scannerService->getFileById($mediaItem['fileId']);
            if ($file === null) {
                $this->logger->warning("File {$mediaItem['fileId']} not found");
                return false;
            }

            $file->delete();
            // Remove from database by setting state to a removed state or deleting the record
            // For now, we can keep it in transcoded state or add a 'deleted' state
            $this->logger->info("Deleted original file {$mediaItem['fileId']}");
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Error deleting file: {$e->getMessage()}");
            return false;
        }
    }
}
