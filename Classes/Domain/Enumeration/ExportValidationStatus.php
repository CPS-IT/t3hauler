<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Domain\Enumeration;

/**
 * Enumeration for file validation status
 */
enum ExportValidationStatus: string
{
    case VALID = 'valid';
    case INVALID = 'invalid';
    case FILE_NOT_FOUND = 'file_not_found';
    case FILE_EMPTY = 'file_empty';
    case INVALID_JSON = 'invalid_json';
    case INVALID_STRUCTURE = 'invalid_structure';
    case PARSE_ERROR = 'parse_error';

    public function isValid(): bool
    {
        return $this === self::VALID;
    }

    public function isInvalid(): bool
    {
        return !$this->isValid();
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::VALID => 'File is valid',
            self::INVALID => 'File is invalid',
            self::FILE_NOT_FOUND => 'File not found',
            self::FILE_EMPTY => 'File is empty',
            self::INVALID_JSON => 'Invalid JSON format',
            self::INVALID_STRUCTURE => 'Invalid file structure',
            self::PARSE_ERROR => 'Failed to parse file',
        };
    }
}
