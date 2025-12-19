<?php

declare(strict_types=1);

namespace OCA\DownTranscoder\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Represents a media item in the transcoding workflow
 *
 * @method int getFileId()
 * @method void setFileId(int $fileId)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getName()
 * @method void setName(string $name)
 * @method string getPath()
 * @method void setPath(string $path)
 * @method int getSize()
 * @method void setSize(int $size)
 * @method string getState()
 * @method void setState(string $state)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class MediaItem extends Entity {
    protected $fileId;
    protected $userId;
    protected $name;
    protected $path;
    protected $size;
    protected $state; // 'found', 'queued', 'transcoded', 'discarded'
    protected $createdAt;
    protected $updatedAt;

    public function __construct() {
        $this->addType('fileId', 'integer');
        $this->addType('userId', 'string');
        $this->addType('name', 'string');
        $this->addType('path', 'string');
        $this->addType('size', 'integer');
        $this->addType('state', 'string');
        $this->addType('createdAt', 'integer');
        $this->addType('updatedAt', 'integer');
    }

    public function jsonSerialize(): array {
        return [
            'id' => $this->id,
            'fileId' => $this->fileId,
            'userId' => $this->userId,
            'name' => $this->name,
            'path' => $this->path,
            'size' => $this->size,
            'state' => $this->state,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
