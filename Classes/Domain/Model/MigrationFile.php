<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Domain\Model;

use Cpsit\T3hauler\Domain\Enumeration\MigrationStatus;

/**
 * Domain model for migration files
 *
 * Represents a migration file with its metadata, data, and filesystem information
 */
class MigrationFile
{
    private string $id;
    private string $filename;
    private string $relativePath;
    private string $absolutePath;
    private array $data;
    private ?array $metadata = null;
    private ?array $records = null;
    private MigrationStatus $status;

    public function __construct(
        string $id,
        string $filename,
        string $relativePath,
        string $absolutePath,
        array $data,
        MigrationStatus $status = MigrationStatus::PENDING
    ) {
        $this->id = $id;
        $this->filename = $filename;
        $this->relativePath = $relativePath;
        $this->absolutePath = $absolutePath;
        $this->data = $data;
        $this->status = $status;
        $this->extractDataSections();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getRelativePath(): string
    {
        return $this->relativePath;
    }

    public function getAbsolutePath(): string
    {
        return $this->absolutePath;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function getRecords(): ?array
    {
        return $this->records;
    }

    public function getStatus(): MigrationStatus
    {
        return $this->status;
    }

    public function setStatus(MigrationStatus $status): self
    {
        $this->status = $status;
        return $this;
    }

    /**
     * Get migration description from metadata
     */
    public function getDescription(): string
    {
        return $this->metadata['description'] ?? 'No description';
    }

    /**
     * Get migration author from metadata
     */
    public function getAuthor(): string
    {
        return $this->metadata['author'] ?? 'Unknown';
    }

    /**
     * Get migration creation timestamp from metadata
     */
    public function getCreatedAt(): int
    {
        return $this->metadata['created_at'] ?? 0;
    }

    /**
     * Get migration creation date as formatted string
     */
    public function getCreatedAtFormatted(): string
    {
        $timestamp = $this->getCreatedAt();
        return $timestamp > 0 ? date('Y-m-d H:i:s', $timestamp) : 'Unknown';
    }

    /**
     * Get migration name from metadata
     */
    public function getName(): string
    {
        return $this->metadata['name'] ?? $this->id;
    }

    /**
     * Get source hash from metadata
     */
    public function getSourceHash(): string
    {
        return $this->metadata['source_hash'] ?? '';
    }

    /**
     * Get format version from metadata
     */
    public function getFormatVersion(): string
    {
        return $this->metadata['format_version'] ?? 'unknown';
    }

    /**
     * Get count of exported tables from metadata
     */
    public function getTableCount(): int
    {
        return isset($this->metadata['exported_tables']) ? count($this->metadata['exported_tables']) : 0;
    }

    /**
     * Get total number of records from metadata
     */
    public function getRecordCount(): int
    {
        return $this->metadata['total_records'] ?? 0;
    }

    /**
     * Get list of exported tables from metadata
     */
    public function getExportedTables(): array
    {
        return $this->metadata['exported_tables'] ?? [];
    }

    /**
     * Check if migration file has valid structure
     */
    public function isValid(): bool
    {
        return $this->hasValidStructure() && $this->hasValidMetadata();
    }

    /**
     * Check if migration has required structure
     */
    public function hasValidStructure(): bool
    {
        return isset($this->data['metadata'], $this->data['records']);
    }

    /**
     * Check if migration has valid metadata
     */
    public function hasValidMetadata(): bool
    {
        if (!$this->metadata) {
            return false;
        }

        $requiredFields = ['format_version', 'migration_id'];
        foreach ($requiredFields as $field) {
            if (!isset($this->metadata[$field])) {
                return false;
            }
        }

        return $this->metadata['format_version'] === '1.0';
    }

    /**
     * Get records for a specific table
     */
    public function getRecordsForTable(string $tableName): array
    {
        return $this->records[$tableName] ?? [];
    }

    /**
     * Check if migration has records for a specific table
     */
    public function hasRecordsForTable(string $tableName): bool
    {
        return isset($this->records[$tableName]) && !empty($this->records[$tableName]);
    }

    /**
     * Get all table names that have records
     */
    public function getTablesWithRecords(): array
    {
        if (!$this->records) {
            return [];
        }

        return array_keys(array_filter($this->records, fn($records) => !empty($records)));
    }

    /**
     * Get summary statistics for this migration file
     */
    public function getSummary(): array
    {
        $tablesWithRecords = $this->getTablesWithRecords();
        $recordCounts = [];

        foreach ($tablesWithRecords as $tableName) {
            $recordCounts[$tableName] = count($this->getRecordsForTable($tableName));
        }

        return [
            'id' => $this->id,
            'filename' => $this->filename,
            'path' => $this->relativePath,
            'status' => $this->status->value,
            'description' => $this->getDescription(),
            'author' => $this->getAuthor(),
            'created_at' => $this->getCreatedAtFormatted(),
            'format_version' => $this->getFormatVersion(),
            'total_tables' => $this->getTableCount(),
            'total_records' => $this->getRecordCount(),
            'tables_with_records' => $tablesWithRecords,
            'record_counts' => $recordCounts,
            'valid' => $this->isValid(),
        ];
    }

    /**
     * Convert to array representation (for backward compatibility)
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'file' => $this->filename,
            'path' => $this->relativePath,
            'absolute_path' => $this->absolutePath,
            'data' => $this->data,
            'status' => $this->status,
        ];
    }

    /**
     * Create MigrationFile from array data (for backward compatibility)
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['id'],
            $data['file'],
            $data['path'],
            $data['absolute_path'],
            $data['data'],
            $data['status'] ?? MigrationStatus::PENDING
        );
    }

    /**
     * Extract metadata and records sections from data
     */
    private function extractDataSections(): void
    {
        $this->metadata = $this->data['metadata'] ?? null;
        $this->records = $this->data['records'] ?? null;
    }
}
