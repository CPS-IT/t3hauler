<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Domain\Dto;

use Cpsit\T3hauler\Domain\Enumeration\ExportValidationStatus;

/**
 * Data Transfer Object for export validation results
 */
readonly class ExportValidationResult
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public ExportValidationStatus $status,
        public string $message,
        public ?int $fileSize = null,
        public int $recordCount = 0,
        public string $format = 'json',
        public array $metadata = [],
    ) {}

    public function isValid(): bool
    {
        return $this->status->isValid();
    }

    public function isInvalid(): bool
    {
        return $this->status->isInvalid();
    }

    public function hasFile(): bool
    {
        return $this->fileSize !== null;
    }

    public function hasRecords(): bool
    {
        return $this->recordCount > 0;
    }

    public function hasMetadata(): bool
    {
        return !empty($this->metadata);
    }

    /**
     * Create a valid validation result
     */
    public static function valid(
        string $message,
        int $fileSize,
        int $recordCount = 0,
        string $format = 'json',
        array $metadata = []
    ): self {
        return new self(
            status: ExportValidationStatus::VALID,
            message: $message,
            fileSize: $fileSize,
            recordCount: $recordCount,
            format: $format,
            metadata: $metadata
        );
    }

    /**
     * Create an invalid validation result
     */
    public static function invalid(
        ExportValidationStatus $status,
        string $message
    ): self {
        return new self(
            status: $status,
            message: $message
        );
    }

    /**
     * Convert to array (for backward compatibility during transition)
     */
    public function toArray(): array
    {
        return [
            'valid' => $this->isValid(),
            'status' => $this->status->value,
            'message' => $this->message,
            'file_size' => $this->fileSize,
            'record_count' => $this->recordCount,
            'format' => $this->format,
            'metadata' => $this->metadata,
        ];
    }
}
