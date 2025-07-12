<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Domain\Enumeration;

/**
 * Enumeration for export operation status
 */
enum ExportStatus: string
{
    case SUCCESS = 'success';
    case FAILURE = 'failure';
    case NO_TABLES = 'no_tables';
    case NO_RECORDS = 'no_records';
    case EXPORT_FAILED = 'export_failed';
    case DIRECTORY_CREATION_FAILED = 'directory_creation_failed';
    case FILE_WRITE_FAILED = 'file_write_failed';

    public function isSuccess(): bool
    {
        return $this === self::SUCCESS;
    }

    public function isFailure(): bool
    {
        return !$this->isSuccess();
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::SUCCESS => 'Export completed successfully',
            self::FAILURE => 'Export failed',
            self::NO_TABLES => 'No tables provided for export',
            self::NO_RECORDS => 'No records found to export',
            self::EXPORT_FAILED => 'Export generation failed',
            self::DIRECTORY_CREATION_FAILED => 'Failed to create output directory',
            self::FILE_WRITE_FAILED => 'Failed to write export file',
        };
    }
}
