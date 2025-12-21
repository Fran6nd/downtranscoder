<?php

declare(strict_types=1);

namespace OCA\DownTranscoder\Util;

/**
 * Constants for media item states
 */
class MediaState {
    /** Media file has been found by scanner */
    public const FOUND = 'found';

    /** Media file is queued for transcoding */
    public const QUEUED = 'queued';

    /** Media file is currently being transcoded */
    public const TRANSCODING = 'transcoding';

    /** Media file has been successfully transcoded */
    public const TRANSCODED = 'transcoded';

    /** Transcoding was aborted */
    public const ABORTED = 'aborted';

    /** Media file was discarded (user chose not to transcode) */
    public const DISCARDED = 'discarded';

    /**
     * Get all valid states
     *
     * @return array<string>
     */
    public static function getAllStates(): array {
        return [
            self::FOUND,
            self::QUEUED,
            self::TRANSCODING,
            self::TRANSCODED,
            self::ABORTED,
            self::DISCARDED,
        ];
    }

    /**
     * Check if a state is valid
     *
     * @param string $state State to validate
     * @return bool
     */
    public static function isValid(string $state): bool {
        return in_array($state, self::getAllStates(), true);
    }
}
