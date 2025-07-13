<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Domain\Enumeration;

/**
 * Enumeration for file validation status
 */
enum RecordChangeType: string
{
    case INSERT = 'insert';
    case UPDATE = 'update';
    case DELETE = 'delete';
    case MOVE = 'move';

    public function getDescription(): string
    {
        return match ($this) {
            self::INSERT => 'new record inserted',
            self::UPDATE => 'record fields updated',
            self::DELETE => 'record deleted',
            self::MOVE => 'record moved',
        };
    }
}
