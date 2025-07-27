<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Traits\Command;

/**
 * Provides common utility methods for commands
 */
trait CommandUtilityTrait
{
    /**
     * Format file size in human-readable format
     */
    protected function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = min((int)floor(log($bytes, 1024)), count($units) - 1);

        return sprintf('%.1f %s', $bytes / (1024 ** $factor), $units[$factor]);
    }

    /**
     * Format timestamp consistently
     */
    protected function formatTimestamp(int $timestamp): string
    {
        return date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * Calculate total records from table data
     */
    protected function calculateTotalRecords(mixed $tableData): int
    {
        if (!is_array($tableData)) {
            return 0;
        }

        $total = 0;
        foreach ($tableData as $data) {
            if (is_array($data)) {
                $total += count($data);
            }
        }

        return $total;
    }

    /**
     * Truncate string with ellipsis
     */
    protected function truncateString(string $str, int $length = 50): string
    {
        return strlen($str) > $length ? substr($str, 0, $length - 3) . '...' : $str;
    }

    /**
     * Pluralize text based on count
     */
    protected function pluralize(string $singular, string $plural, int $count): string
    {
        return $count === 1 ? $singular : $plural;
    }
}
