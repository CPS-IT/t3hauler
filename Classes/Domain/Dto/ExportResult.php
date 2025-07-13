<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Domain\Dto;

use Cpsit\T3hauler\Domain\Enumeration\ExportStatus;

/**
 * Data Transfer Object for export operation results
 */
readonly class ExportResult
{
    /**
     * @param array<string, int> $exportedTables
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public ExportStatus $status,
        public string $message,
        public int $recordCount = 0,
        public array $exportedTables = [],
        public ?string $filePath = null,
        public ?int $fileSize = null,
        public string $format = 'json',
        public array $metadata = [],
        public ?string $exception = null,
        public ?int $exceptionCode = null,
    ) {}

    public function isSuccess(): bool
    {
        return $this->status->isSuccess();
    }

    public function isFailure(): bool
    {
        return $this->status->isFailure();
    }

    public function hasRecords(): bool
    {
        return $this->recordCount > 0;
    }

    public function hasFile(): bool
    {
        return $this->filePath !== null && $this->fileSize !== null;
    }

    public function hasException(): bool
    {
        return $this->exception !== null;
    }

    /**
     * Create a successful export result
     */
    public static function success(
        string $message,
        int $recordCount,
        array $exportedTables,
        string $filePath,
        int $fileSize,
        string $format = 'json',
        array $metadata = []
    ): self {
        return new self(
            status: ExportStatus::SUCCESS,
            message: $message,
            recordCount: $recordCount,
            exportedTables: $exportedTables,
            filePath: $filePath,
            fileSize: $fileSize,
            format: $format,
            metadata: $metadata
        );
    }

    /**
     * Create a failed export result
     */
    public static function failure(
        ExportStatus $status,
        string $message,
        ?string $exception = null,
        ?int $exceptionCode = null
    ): self {
        return new self(
            status: $status,
            message: $message,
            exception: $exception,
            exceptionCode: $exceptionCode
        );
    }

    /**
     * Convert to array (for backward compatibility during transition)
     */
    public function toArray(): array
    {
        return [
            'success' => $this->isSuccess(),
            'status' => $this->status->value,
            'message' => $this->message,
            'record_count' => $this->recordCount,
            'exported_tables' => $this->exportedTables,
            'file_path' => $this->filePath,
            'file_size' => $this->fileSize,
            'format' => $this->format,
            'metadata' => $this->metadata,
            'exception' => $this->exception,
            'exception_code' => $this->exceptionCode,
        ];
    }
}
