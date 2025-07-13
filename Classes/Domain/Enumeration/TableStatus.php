<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Domain\Enumeration;

/**
 * Enumeration for table change status
 */
enum TableStatus: string
{
    case CHANGED = 'changed';
    case UNCHANGED = 'unchanged';
    case NO_BASELINE = 'no_baseline';

    /**
     * Get human-readable description
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::CHANGED => 'Changes detected',
            self::UNCHANGED => 'No changes detected',
            self::NO_BASELINE => 'No baseline snapshot found',
        };
    }

    /**
     * Check if this status indicates changes
     */
    public function hasChanges(): bool
    {
        return match ($this) {
            self::CHANGED => true,
            self::UNCHANGED => false,
            self::NO_BASELINE => true, // Treated as changes for migration purposes
        };
    }
}
