<?php

declare(strict_types=1);

namespace OCA\DownTranscoder\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class MediaItemMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'downtranscoder_media', MediaItem::class);
    }

    /**
     * Find a media item by database record ID
     *
     * @param int $id Database record ID
     * @return MediaItem
     * @throws DoesNotExistException
     */
    public function findById(int $id): MediaItem {
        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

        return $this->findEntity($qb);
    }

    /**
     * Find a media item by file ID
     *
     * @param int $fileId
     * @param string $userId
     * @return MediaItem
     * @throws DoesNotExistException
     */
    public function findByFileId(int $fileId, string $userId): MediaItem {
        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

        return $this->findEntity($qb);
    }

    /**
     * Find all media items for a user
     *
     * @param string $userId
     * @return MediaItem[]
     */
    public function findAll(string $userId): array {
        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->orderBy('updated_at', 'DESC');

        return $this->findEntities($qb);
    }

    /**
     * Find media items by state
     *
     * @param string $userId
     * @param string $state
     * @return MediaItem[]
     */
    public function findByState(string $userId, string $state): array {
        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->andWhere($qb->expr()->eq('state', $qb->createNamedParameter($state)))
            ->orderBy('updated_at', 'DESC');

        return $this->findEntities($qb);
    }

    /**
     * Update the state of a media item
     *
     * @param int $fileId
     * @param string $userId
     * @param string $state
     * @return MediaItem
     */
    public function updateState(int $fileId, string $userId, string $state): MediaItem {
        try {
            $item = $this->findByFileId($fileId, $userId);
        } catch (DoesNotExistException $e) {
            // If item doesn't exist, we can't update it
            throw $e;
        }

        $item->setState($state);
        $item->setUpdatedAt(time());

        return $this->update($item);
    }

    /**
     * Delete old discarded items (older than 30 days)
     *
     * @param string $userId
     * @return int Number of deleted items
     */
    public function deleteOldDiscarded(string $userId): int {
        $qb = $this->db->getQueryBuilder();
        $thirtyDaysAgo = time() - (30 * 24 * 60 * 60);

        $qb->delete($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->andWhere($qb->expr()->eq('state', $qb->createNamedParameter('discarded')))
            ->andWhere($qb->expr()->lt('updated_at', $qb->createNamedParameter($thirtyDaysAgo, IQueryBuilder::PARAM_INT)));

        return $qb->execute();
    }

    /**
     * Delete all items with a specific state for a user
     *
     * @param string|null $userId User ID (null = all users)
     * @param string $state State to delete
     * @return int Number of deleted items
     */
    public function deleteByState(?string $userId, string $state): int {
        $qb = $this->db->getQueryBuilder();

        $qb->delete($this->getTableName())
            ->where($qb->expr()->eq('state', $qb->createNamedParameter($state)));

        // If userId is specified, filter by user
        if ($userId !== null) {
            $qb->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
        }

        return $qb->executeStatement();
    }

    /**
     * Delete all items for a user (reset database)
     *
     * @param string $userId
     * @return int Number of deleted items
     */
    public function deleteAllForUser(string $userId): int {
        $qb = $this->db->getQueryBuilder();

        $qb->delete($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

        return $qb->executeStatement();
    }
}
