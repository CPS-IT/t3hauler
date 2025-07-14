<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Domain\Model;

/**
 * Enumeration for migration status values
 */
enum MigrationStatus: string
{
    case PENDING = 'pending';
    case APPLIED = 'applied';
    case FAILED = 'failed';
    case ROLLED_BACK = 'rolled_back';
    case INCOMPLETE = 'incomplete';
    case INVALID = 'invalid';

    /**
     * Get all possible status values
     *
     * @return array<string>
     */
    public static function getValues(): array
    {
        return array_map(static fn(self $status) => $status->value, self::cases());
    }

    /**
     * Check if status represents a successful state
     */
    public function isSuccessful(): bool
    {
        return $this === self::APPLIED;
    }

    /**
     * Check if status represents a failed state
     */
    public function isFailed(): bool
    {
        return match ($this) {
            self::FAILED, self::INVALID, self::INCOMPLETE => true,
            default => false,
        };
    }

    /**
     * Check if status represents a pending state
     */
    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    /**
     * Check if status represents a rolled back state
     */
    public function isRolledBack(): bool
    {
        return $this === self::ROLLED_BACK;
    }

    /**
     * Get the display name for the status
     */
    public function getDisplayName(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::APPLIED => 'Applied',
            self::FAILED => 'Failed',
            self::ROLLED_BACK => 'Rolled Back',
            self::INCOMPLETE => 'Incomplete',
            self::INVALID => 'Invalid',
        };
    }

    /**
     * Get the status with the appropriate console coloring
     */
    public function getColoredStatus(): string
    {
        return match ($this) {
            self::APPLIED => '<fg=green>applied</fg=green>',
            self::PENDING => '<fg=yellow>pending</fg=yellow>',
            self::FAILED => '<fg=red>failed</fg=red>',
            self::INVALID => '<fg=red>invalid</fg=red>',
            self::INCOMPLETE => '<fg=red>incomplete</fg=red>',
            self::ROLLED_BACK => '<fg=blue>rolled_back</fg=blue>',
        };
    }
}
