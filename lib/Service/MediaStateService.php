<?php

declare(strict_types=1);

namespace OCA\DownTranscoder\Service;

use OCA\DownTranscoder\Db\MediaItem;
use OCA\DownTranscoder\Db\MediaItemMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IUserSession;

/**
 * Service for managing media item states in the kanban board
 */
class MediaStateService {
    private MediaItemMapper $mapper;
    private IUserSession $userSession;

    public function __construct(
        MediaItemMapper $mapper,
        IUserSession $userSession
    ) {
        $this->mapper = $mapper;
        $this->userSession = $userSession;
    }

    /**
     * Get the current user ID
     */
    private function getUserId(): string {
        $user = $this->userSession->getUser();
        if ($user === null) {
            throw new \Exception('User not logged in');
        }
        return $user->getUID();
    }

    /**
     * Get all media items for the current user
     *
     * @return array
     */
    public function getAllMediaItems(): array {
        $userId = $this->getUserId();
        $items = $this->mapper->findAll($userId);

        return array_map(function (MediaItem $item) {
            return $item->jsonSerialize();
        }, $items);
    }

    /**
     * Add or update a media item
     *
     * @param int $fileId Nextcloud file ID
     * @param string $name File name
     * @param string $path File path
     * @param int $size File size in bytes
     * @param string $state Initial state (default: 'found')
     * @return MediaItem
     */
    public function addMediaItem(
        int $fileId,
        string $name,
        string $path,
        int $size,
        string $state = 'found'
    ): MediaItem {
        $userId = $this->getUserId();

        // Check if item already exists
        try {
            $item = $this->mapper->findByFileId($fileId, $userId);
            // Update existing item
            $item->setName($name);
            $item->setPath($path);
            $item->setSize($size);
            $item->setUpdatedAt(time());
            return $this->mapper->update($item);
        } catch (DoesNotExistException $e) {
            // Create new item
            $item = new MediaItem();
            $item->setFileId($fileId);
            $item->setUserId($userId);
            $item->setName($name);
            $item->setPath($path);
            $item->setSize($size);
            $item->setState($state);
            $item->setCreatedAt(time());
            $item->setUpdatedAt(time());
            return $this->mapper->insert($item);
        }
    }

    /**
     * Update the state of a media item by database record ID
     *
     * @param int $id Database record ID
     * @param string $state New state ('found', 'queued', 'transcoded', 'discarded')
     * @return MediaItem
     */
    public function updateMediaState(int $id, string $state): MediaItem {
        $userId = $this->getUserId();

        // Find by record ID
        $item = $this->mapper->findById($id);

        // Verify ownership
        if ($item->getUserId() !== $userId) {
            throw new \Exception('Access denied');
        }

        $item->setState($state);
        $item->setUpdatedAt(time());

        return $this->mapper->update($item);
    }

    /**
     * Get media items by state
     *
     * @param string $state
     * @return array
     */
    public function getMediaItemsByState(string $state): array {
        $userId = $this->getUserId();
        $items = $this->mapper->findByState($userId, $state);

        return array_map(function (MediaItem $item) {
            return $item->jsonSerialize();
        }, $items);
    }

    /**
     * Update the transcode preset for a media item
     *
     * @param int $id Database record ID
     * @param string|null $preset Preset name or null to use default
     * @return MediaItem
     */
    public function updateTranscodePreset(int $id, ?string $preset): MediaItem {
        $userId = $this->getUserId();

        // Find by record ID
        $item = $this->mapper->findById($id);

        // Verify ownership
        if ($item->getUserId() !== $userId) {
            throw new \Exception('Access denied');
        }

        $item->setTranscodePreset($preset);
        $item->setUpdatedAt(time());

        return $this->mapper->update($item);
    }

    /**
     * Clean up old discarded items
     */
    public function cleanupOldDiscarded(): int {
        $userId = $this->getUserId();
        return $this->mapper->deleteOldDiscarded($userId);
    }
}
