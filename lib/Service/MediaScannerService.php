<?php

declare(strict_types=1);

namespace OCA\DownTranscoder\Service;

use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

class MediaScannerService {
    private IRootFolder $rootFolder;
    private IConfig $config;
    private LoggerInterface $logger;

    // Supported video extensions
    private const VIDEO_EXTENSIONS = ['mp4', 'mkv', 'avi', 'mov', 'wmv', 'flv', 'webm', 'm4v', 'mpg', 'mpeg', 'ts', 'vob'];

    // Supported image extensions
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'webp'];

    public function __construct(
        IRootFolder $rootFolder,
        IConfig $config,
        LoggerInterface $logger
    ) {
        $this->rootFolder = $rootFolder;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Scan all user files for large media files
     *
     * @return array Array of large media files with metadata
     */
    public function scanForLargeFiles(): array {
        $triggerSizeGB = (int) $this->config->getAppValue('downtranscoder', 'trigger_size_gb', '10');
        $triggerSizeBytes = $triggerSizeGB * 1024 * 1024 * 1024;

        $this->logger->info("Scanning for media files larger than {$triggerSizeGB} GB");

        $largeFiles = [];
        $totalScanned = 0;

        // Get all user folders
        $userFolders = $this->rootFolder->getDirectoryListing();

        foreach ($userFolders as $userFolder) {
            if (!$userFolder instanceof Folder) {
                continue;
            }

            $userName = $userFolder->getName();
            $this->logger->debug("Scanning user folder: {$userName}");

            $files = $this->scanFolder($userFolder, $triggerSizeBytes);
            $largeFiles = array_merge($largeFiles, $files);
            $totalScanned += count($files);
        }

        $this->logger->info("Scan complete. Found " . count($largeFiles) . " large media files (scanned {$totalScanned} total items)");

        return $largeFiles;
    }

    /**
     * Recursively scan a folder for large media files
     *
     * @param Folder $folder Folder to scan
     * @param int $triggerSizeBytes Minimum file size in bytes
     * @return array Array of large media files
     */
    private function scanFolder(Folder $folder, int $triggerSizeBytes): array {
        $largeFiles = [];

        try {
            $nodes = $folder->getDirectoryListing();

            foreach ($nodes as $node) {
                if ($node instanceof Folder) {
                    // Recursively scan subfolders
                    $largeFiles = array_merge($largeFiles, $this->scanFolder($node, $triggerSizeBytes));
                } elseif ($node instanceof File) {
                    // Check if it's a media file and exceeds size threshold
                    $fileInfo = $this->analyzeFile($node, $triggerSizeBytes);
                    if ($fileInfo !== null) {
                        $largeFiles[] = $fileInfo;
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->warning("Error scanning folder {$folder->getPath()}: {$e->getMessage()}");
        }

        return $largeFiles;
    }

    /**
     * Analyze a file to determine if it's a large media file
     *
     * @param File $file File to analyze
     * @param int $triggerSizeBytes Minimum file size in bytes
     * @return array|null File info or null if not a large media file
     */
    private function analyzeFile(File $file, int $triggerSizeBytes): ?array {
        try {
            $size = $file->getSize();

            // Skip if below threshold
            if ($size <= $triggerSizeBytes) {
                return null;
            }

            $extension = strtolower(pathinfo($file->getName(), PATHINFO_EXTENSION));

            // Determine media type
            $mediaType = null;
            if (in_array($extension, self::VIDEO_EXTENSIONS)) {
                $mediaType = 'Video';
            } elseif (in_array($extension, self::IMAGE_EXTENSIONS)) {
                $mediaType = 'Image';
            } else {
                // Not a media file we care about
                return null;
            }

            $sizeGB = $size / (1024 * 1024 * 1024);

            $this->logger->debug("Found large {$mediaType}: {$file->getName()} ({$sizeGB} GB)");

            return [
                'id' => $file->getId(),
                'name' => $file->getName(),
                'path' => $file->getPath(),
                'size' => $size,
                'sizeGB' => round($sizeGB, 2),
                'type' => $mediaType,
                'extension' => $extension,
                'mimetype' => $file->getMimeType(),
                'owner' => $file->getOwner()->getUID(),
                'mtime' => $file->getMTime(),
            ];
        } catch (\Exception $e) {
            $this->logger->warning("Error analyzing file {$file->getName()}: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Get file by ID
     *
     * @param int $fileId File ID
     * @return File|null
     */
    public function getFileById(int $fileId): ?File {
        try {
            $nodes = $this->rootFolder->getById($fileId);
            if (empty($nodes)) {
                return null;
            }

            $node = $nodes[0];
            if ($node instanceof File) {
                return $node;
            }
        } catch (\Exception $e) {
            $this->logger->error("Error getting file {$fileId}: {$e->getMessage()}");
        }

        return null;
    }
}
