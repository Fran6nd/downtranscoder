<?php

declare(strict_types=1);

namespace OCA\DownTranscoder\Util;

/**
 * Helper class for formatting values for display
 */
class FormatHelper {
    /**
     * Format bytes to human-readable size with GB precision
     *
     * @param int|float $bytes Size in bytes
     * @param int $decimals Number of decimal places (default: 2)
     * @return string Formatted size string (e.g., "10.45")
     * @throws \InvalidArgumentException if bytes is negative
     */
    public static function formatSizeGB(int|float $bytes, int $decimals = 2): string {
        if ($bytes < 0) {
            throw new \InvalidArgumentException('Bytes cannot be negative');
        }

        $sizeGB = $bytes / (1024 * 1024 * 1024);
        return number_format($sizeGB, $decimals);
    }

    /**
     * Convert bytes to GB as a float
     *
     * @param int|float $bytes Size in bytes
     * @return float Size in GB
     * @throws \InvalidArgumentException if bytes is negative
     */
    public static function bytesToGB(int|float $bytes): float {
        if ($bytes < 0) {
            throw new \InvalidArgumentException('Bytes cannot be negative');
        }

        return $bytes / (1024 * 1024 * 1024);
    }
}
