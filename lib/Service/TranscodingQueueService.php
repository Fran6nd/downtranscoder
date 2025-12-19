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

    private const QUEUE_KEY = 'transcode_queue';
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
     * Get the transcode queue
     *
     * @return array Array of queued file IDs
     */
    public function getQueue(): array {
        $queueJson = $this->config->getAppValue('downtranscoder', self::QUEUE_KEY, '[]');
        return json_decode($queueJson, true) ?: [];
    }

    /**
     * Add a file to the transcode queue
     *
     * @param int $fileId File ID to add
     * @return bool Success
     */
    public function addToQueue(int $fileId): bool {
        $file = $this->scannerService->getFileById($fileId);
        if ($file === null) {
            $this->logger->warning("Cannot add file {$fileId} to queue: file not found");
            return false;
        }

        $queue = $this->getQueue();

        // Check if already in queue
        foreach ($queue as $item) {
            if ($item['id'] === $fileId) {
                $this->logger->info("File {$fileId} already in queue");
                return true;
            }
        }

        // Add to queue
        $queue[] = [
            'id' => $fileId,
            'name' => $file->getName(),
            'path' => $file->getPath(),
            'size' => $file->getSize(),
            'added_at' => time(),
            'status' => 'pending',
        ];

        $this->config->setAppValue('downtranscoder', self::QUEUE_KEY, json_encode($queue));
        $this->logger->info("Added file {$fileId} ({$file->getName()}) to transcode queue");

        return true;
    }

    /**
     * Remove a file from the transcode queue
     *
     * @param int $fileId File ID to remove
     */
    public function removeFromQueue(int $fileId): void {
        $queue = $this->getQueue();
        $queue = array_filter($queue, function($item) use ($fileId) {
            return $item['id'] !== $fileId;
        });

        $this->config->setAppValue('downtranscoder', self::QUEUE_KEY, json_encode(array_values($queue)));
        $this->logger->info("Removed file {$fileId} from transcode queue");
    }

    /**
     * Start transcoding all queued items
     *
     * @return bool Success
     */
    public function startTranscoding(): bool {
        $queue = $this->getQueue();

        if (empty($queue)) {
            $this->logger->info("No items in transcode queue");
            return true;
        }

        $this->setStatus([
            'is_transcoding' => true,
            'current_index' => 0,
            'total_items' => count($queue),
            'started_at' => time(),
        ]);

        $this->logger->info("Starting transcoding of " . count($queue) . " items");

        foreach ($queue as $index => $item) {
            $this->setStatus([
                'is_transcoding' => true,
                'current_index' => $index + 1,
                'total_items' => count($queue),
                'current_file' => $item['name'],
            ]);

            $success = $this->transcodeFile($item['id']);

            if ($success) {
                $item['status'] = 'completed';
                $item['completed_at'] = time();
            } else {
                $item['status'] = 'failed';
                $item['error'] = 'Transcoding failed';
            }

            // Update queue with status
            $queue[$index] = $item;
            $this->config->setAppValue('downtranscoder', self::QUEUE_KEY, json_encode($queue));
        }

        $this->setStatus([
            'is_transcoding' => false,
            'completed_at' => time(),
        ]);

        $this->logger->info("Transcoding completed");
        return true;
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

        $queue = $this->getQueue();
        $status['queued_items'] = count($queue);
        $status['completed_items'] = count(array_filter($queue, fn($item) => ($item['status'] ?? '') === 'completed'));
        $status['failed_items'] = count(array_filter($queue, fn($item) => ($item['status'] ?? '') === 'failed'));

        return $status;
    }

    /**
     * Set transcoding status
     *
     * @param array $status Status data
     */
    private function setStatus(array $status): void {
        $currentStatus = $this->getStatus();
        $newStatus = array_merge($currentStatus, $status);
        $this->config->setAppValue('downtranscoder', self::STATUS_KEY, json_encode($newStatus));
    }

    /**
     * Delete original file after transcoding
     *
     * @param int $fileId File ID
     * @return bool Success
     */
    public function deleteOriginal(int $fileId): bool {
        $queue = $this->getQueue();

        // Find the item in queue
        $item = null;
        foreach ($queue as $queueItem) {
            if ($queueItem['id'] === $fileId) {
                $item = $queueItem;
                break;
            }
        }

        if ($item === null) {
            $this->logger->warning("File {$fileId} not found in queue");
            return false;
        }

        if (($item['status'] ?? '') !== 'completed') {
            $this->logger->warning("File {$fileId} has not been transcoded yet");
            return false;
        }

        $file = $this->scannerService->getFileById($fileId);
        if ($file === null) {
            $this->logger->warning("File {$fileId} not found");
            return false;
        }

        try {
            $file->delete();
            $this->removeFromQueue($fileId);
            $this->logger->info("Deleted original file {$fileId}");
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Error deleting file {$fileId}: {$e->getMessage()}");
            return false;
        }
    }
}
