<?php

declare(strict_types=1);

namespace OCA\DownTranscoder\Service;

use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\IConfig;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class MediaScannerService {
    private IRootFolder $rootFolder;
    private IConfig $config;
    private IUserManager $userManager;
    private LoggerInterface $logger;
    private MediaStateService $stateService;

    // Supported video extensions
    private const VIDEO_EXTENSIONS = ['mp4', 'mkv', 'avi', 'mov', 'wmv', 'flv', 'webm', 'm4v', 'mpg', 'mpeg', 'ts', 'vob'];

    // Supported image extensions
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'webp'];

    private const SCAN_STATUS_KEY = 'scan_status';

    public function __construct(
        IRootFolder $rootFolder,
        IConfig $config,
        IUserManager $userManager,
        LoggerInterface $logger,
        MediaStateService $stateService
    ) {
        $this->rootFolder = $rootFolder;
        $this->config = $config;
        $this->userManager = $userManager;
        $this->logger = $logger;
        $this->stateService = $stateService;
    }

    /**
     * Scan all user files for large media files
     *
     * @return array Array of large media files with metadata
     */
    public function scanForLargeFiles(): array {
        $this->logger->info('>>> scanForLargeFiles() called');

        // Set scan status to scanning
        $this->setScanStatus([
            'is_scanning' => true,
            'started_at' => time(),
            'files_found' => 0,
        ]);
        $this->logger->info('>>> Scan status set to: is_scanning=true');

        // Clear ONLY 'found' items before starting a new scan
        // Keep queued, transcoding, aborted, transcoded, and discarded items
        try {
            $clearedCount = $this->stateService->clearItemsByState('found');
            $this->logger->info(">>> Cleared {$clearedCount} items in 'found' state before scan");
        } catch (\Exception $e) {
            $this->logger->error(">>> Failed to clear 'found' items: " . $e->getMessage());
        }

        $triggerSizeGB = (int) $this->config->getAppValue('downtranscoder', 'trigger_size_gb', '10');
        $triggerSizeBytes = $triggerSizeGB * 1024 * 1024 * 1024;

        // Get configured scan paths
        $scanPathsJson = $this->config->getAppValue('downtranscoder', 'scan_paths', '[]');
        $scanPaths = json_decode($scanPathsJson, true) ?: [];

        // Get include external storages setting
        $includeExternal = $this->config->getAppValue('downtranscoder', 'include_external_storage', 'true') === 'true';

        $this->logger->info("=== SCAN STARTING ===");
        $this->logger->info("Trigger size: {$triggerSizeGB} GB ({$triggerSizeBytes} bytes)");
        if (!empty($scanPaths)) {
            $this->logger->info("Scan paths configured: " . implode(', ', $scanPaths));
        } else {
            $this->logger->info("No specific paths configured, scanning all user files");
        }
        $this->logger->info("Include external storages: " . ($includeExternal ? 'Yes' : 'No'));

        $largeFiles = [];
        $totalScanned = 0;

        // If specific paths are configured, scan only those
        if (!empty($scanPaths)) {
            foreach ($scanPaths as $scanPath) {
                $this->logger->debug("Scanning configured path: {$scanPath}");
                $files = $this->scanPath($scanPath, $triggerSizeBytes, $includeExternal);
                $largeFiles = array_merge($largeFiles, $files);
                $totalScanned += count($files);
            }
        } else {
            // Otherwise, scan all users
            $this->logger->info(">>> No specific scan paths configured - scanning ALL USERS");
            $userCount = 0;

            try {
                $this->userManager->callForAllUsers(function ($user) use ($triggerSizeBytes, &$largeFiles, &$totalScanned, &$userCount) {
                    $userCount++;
                    $userId = $user->getUID();
                    $this->logger->info(">>> [User #{$userCount}] Processing user: {$userId}");

                    try {
                        // Get the user's folder
                        $userFolder = $this->rootFolder->getUserFolder($userId);
                        $folderPath = $userFolder->getPath();
                        $this->logger->info(">>> [User {$userId}] Got user folder: {$folderPath}");

                        if ($userFolder instanceof Folder) {
                            $this->logger->info(">>> [User {$userId}] Starting folder scan (includeExternal: " . ($includeExternal ? 'YES' : 'NO') . ")...");
                            $files = $this->scanFolder($userFolder, $triggerSizeBytes, $includeExternal);
                            $largeFiles = array_merge($largeFiles, $files);
                            $totalScanned += count($files);
                            $this->logger->info(">>> [User {$userId}] RESULT: Found " . count($files) . " large files (total so far: " . count($largeFiles) . ")");
                        } else {
                            $this->logger->error(">>> [User {$userId}] ERROR: User folder is not a Folder instance!");
                        }
                    } catch (\Exception $e) {
                        $this->logger->error(">>> [User {$userId}] EXCEPTION: " . $e->getMessage());
                        $this->logger->error(">>> [User {$userId}] Stack trace: " . $e->getTraceAsString());
                    }
                });

                $this->logger->info(">>> Finished scanning {$userCount} total users");
            } catch (\Exception $e) {
                $this->logger->error(">>> FATAL ERROR in callForAllUsers: " . $e->getMessage());
                $this->logger->error(">>> Stack trace: " . $e->getTraceAsString());
            }
        }

        $this->logger->info("Scan complete. Found " . count($largeFiles) . " large media files (scanned {$totalScanned} total items)");

        // Persist scanned files to the kanban board database
        $this->logger->info(">>> Persisting " . count($largeFiles) . " files to database...");
        $addedCount = 0;
        $errorCount = 0;

        foreach ($largeFiles as $fileInfo) {
            try {
                $this->logger->debug(">>> Adding to DB: {$fileInfo['name']} (owner: {$fileInfo['owner']})");
                $this->stateService->addMediaItem(
                    $fileInfo['id'],
                    $fileInfo['name'],
                    $fileInfo['path'],
                    $fileInfo['size'],
                    'found', // Initial state is "found"
                    $fileInfo['owner'] // Pass the file owner for background job context
                );
                $addedCount++;
            } catch (\Exception $e) {
                $errorCount++;
                $this->logger->error(">>> FAILED to add '{$fileInfo['name']}' to database: {$e->getMessage()}");
                $this->logger->error(">>> Stack trace: " . $e->getTraceAsString());
            }
        }

        $this->logger->info(">>> Database persistence complete: {$addedCount} added, {$errorCount} errors");

        // Set scan status to complete
        $this->setScanStatus([
            'is_scanning' => false,
            'completed_at' => time(),
            'files_found' => count($largeFiles),
        ]);

        return $largeFiles;
    }

    /**
     * Scan a specific path
     *
     * @param string $path Path to scan (e.g., "username/files/Movies")
     * @param int $triggerSizeBytes Minimum file size in bytes
     * @param bool $includeExternal Include external storages
     * @return array Array of large media files
     */
    private function scanPath(string $path, int $triggerSizeBytes, bool $includeExternal): array {
        try {
            // Remove leading/trailing slashes
            $path = trim($path, '/');

            // Try to get the folder by path
            $nodes = $this->rootFolder->getByPath('/' . $path);

            if (empty($nodes)) {
                $this->logger->warning("Path not found: {$path}");
                return [];
            }

            $node = $nodes[0] ?? $nodes;

            if (!$node instanceof Folder) {
                $this->logger->warning("Path is not a folder: {$path}");
                return [];
            }

            return $this->scanFolder($node, $triggerSizeBytes, $includeExternal);
        } catch (\Exception $e) {
            $this->logger->error("Error scanning path {$path}: {$e->getMessage()}");
            return [];
        }
    }

    /**
     * Recursively scan a folder for large media files
     *
     * @param Folder $folder Folder to scan
     * @param int $triggerSizeBytes Minimum file size in bytes
     * @param bool $includeExternal Include external storage files (default: true)
     * @return array Array of large media files
     */
    private function scanFolder(Folder $folder, int $triggerSizeBytes, bool $includeExternal = true): array {
        $largeFiles = [];

        try {
            // Check if this is external storage
            $storage = $folder->getStorage();
            $isExternal = $storage && method_exists($storage, 'isLocal') && !$storage->isLocal();

            if ($isExternal) {
                $this->logger->info(">>> External storage detected: {$folder->getPath()}");
                if (!$includeExternal) {
                    $this->logger->info(">>> Skipping external storage (includeExternal=false)");
                    return [];
                }
            }

            $nodes = $folder->getDirectoryListing();
            $this->logger->debug(">>> Scanning folder {$folder->getPath()}: " . count($nodes) . " items" . ($isExternal ? " [EXTERNAL]" : ""));

            foreach ($nodes as $node) {
                if ($node instanceof Folder) {
                    // Recursively scan subfolders
                    $largeFiles = array_merge($largeFiles, $this->scanFolder($node, $triggerSizeBytes, $includeExternal));
                } elseif ($node instanceof File) {
                    // Check if it's a media file and exceeds size threshold
                    $fileInfo = $this->analyzeFile($node, $triggerSizeBytes);
                    if ($fileInfo !== null) {
                        $largeFiles[] = $fileInfo;
                        $this->logger->info(">>> Found large file: {$fileInfo['name']} ({$fileInfo['sizeGB']} GB)" . ($isExternal ? " [EXTERNAL]" : ""));
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->error(">>> Error scanning folder {$folder->getPath()}: {$e->getMessage()}", ['exception' => $e]);
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
            $fileName = $file->getName();
            $size = $file->getSize();
            $sizeGB = $size / (1024 * 1024 * 1024);

            // Skip if below threshold
            if ($size <= $triggerSizeBytes) {
                // Only log files close to threshold for debugging
                if ($size > $triggerSizeBytes * 0.8) {
                    $this->logger->debug(">>> File '{$fileName}' is {$sizeGB} GB - below threshold");
                }
                return null;
            }

            $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            // Determine media type
            $mediaType = null;
            if (in_array($extension, self::VIDEO_EXTENSIONS)) {
                $mediaType = 'Video';
            } elseif (in_array($extension, self::IMAGE_EXTENSIONS)) {
                $mediaType = 'Image';
            } else {
                // Not a media file we care about
                $this->logger->debug(">>> File '{$fileName}' is {$sizeGB} GB but extension '{$extension}' is not a supported media type");
                return null;
            }

            $this->logger->info(">>> ✓ MATCH: {$mediaType} file '{$fileName}' ({$sizeGB} GB) - ADDING TO RESULTS");

            try {
                $owner = $file->getOwner();
                $ownerId = $owner ? $owner->getUID() : 'unknown';
            } catch (\Exception $e) {
                $this->logger->warning(">>> Could not get owner for file '{$fileName}': " . $e->getMessage());
                $ownerId = 'unknown';
            }

            return [
                'id' => $file->getId(),
                'name' => $fileName,
                'path' => $file->getPath(),
                'size' => $size,
                'sizeGB' => round($sizeGB, 2),
                'type' => $mediaType,
                'extension' => $extension,
                'mimetype' => $file->getMimeType(),
                'owner' => $ownerId,
                'mtime' => $file->getMTime(),
            ];
        } catch (\Exception $e) {
            $this->logger->error(">>> ERROR analyzing file: " . $e->getMessage());
            $this->logger->error(">>> Stack trace: " . $e->getTraceAsString());
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

    /**
     * Get scan status
     *
     * @return array Status information
     */
    public function getScanStatus(): array {
        $statusJson = $this->config->getAppValue('downtranscoder', self::SCAN_STATUS_KEY, '{}');
        $status = json_decode($statusJson, true) ?: [];

        // Provide default values
        if (!isset($status['is_scanning'])) {
            $status['is_scanning'] = false;
        }

        return $status;
    }

    /**
     * Set scan status
     *
     * @param array $status Status data
     */
    private function setScanStatus(array $status): void {
        $statusJson = $this->config->getAppValue('downtranscoder', self::SCAN_STATUS_KEY, '{}');
        $currentStatus = json_decode($statusJson, true) ?: [];
        $newStatus = array_merge($currentStatus, $status);
        $this->config->setAppValue('downtranscoder', self::SCAN_STATUS_KEY, json_encode($newStatus));
    }
}
