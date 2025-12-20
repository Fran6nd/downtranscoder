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

        $this->setStatus([
            'is_transcoding' => true,
            'current_index' => 0,
            'total_items' => count($queuedItems),
            'started_at' => time(),
        ]);

        $this->logger->info("Starting transcoding of " . count($queuedItems) . " items");

        foreach ($queuedItems as $index => $item) {
            $this->setStatus([
                'is_transcoding' => true,
                'current_index' => $index + 1,
                'total_items' => count($queuedItems),
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
            $this->logger->error("File {$fileId} not found for transcoding");
            return false;
        }

        // Update state to 'transcoding' in the database by fileId
        try {
            $mediaItem = $this->stateService->getMediaItemsByState('queued');
            foreach ($mediaItem as $item) {
                if ($item['fileId'] === $fileId) {
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
            $this->logger->error("Cannot get local path for file {$fileId}");
            return false;
        }

        // Create output path
        $outputPath = $inputPath . '.transcoded.' . $extension;

        $this->logger->info("Transcoding file {$fileId}: {$inputPath} -> {$outputPath}");

        // Determine if it's a video or image
        $isVideo = in_array($extension, ['mp4', 'mkv', 'avi', 'mov', 'wmv', 'flv', 'webm', 'm4v', 'mpg', 'mpeg', 'ts', 'vob']);
        $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'webp']);

        $success = false;

        if ($isVideo) {
            $success = $this->transcodingService->transcodeVideo($inputPath, $outputPath);
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
            $this->logger->error("Failed to transcode file {$fileId}");

            // Update state to 'aborted' in the database
            try {
                $mediaItem = $this->stateService->getMediaItemsByState('transcoding');
                foreach ($mediaItem as $item) {
                    if ($item['fileId'] === $fileId) {
                        $this->stateService->updateMediaState($item['id'], 'aborted');
                        break;
                    }
                }
            } catch (\Exception $e) {
                $this->logger->warning("Could not update media state to aborted: " . $e->getMessage());
            }
        }

        return $success;
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
