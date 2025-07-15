<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Domain\Model;

/**
 * Custom export model for t3hauler data exports
 *
 * Handles structured data export with relation dependency resolution
 */
class Export
{
    private array $records = [];
    private array $relations = [];
    private array $metadata = [];
    private string $charset = 'utf-8';
    /** @phpstan-ignore-next-line property.onlyWritten */
    private bool $excludeDisabledRecords = false; // Will be used for filtering in future versions
    private array $exportedTables = [];

    public function __construct()
    {
        $this->metadata = [
            'created_at' => time(),
            'created_by' => 't3hauler',
            'format_version' => '1.0',
            'charset' => $this->charset,
        ];
    }

    /**
     * Set character encoding
     */
    public function setCharset(string $charset): void
    {
        $this->charset = $charset;
        $this->metadata['charset'] = $charset;
    }

    /**
     * Set whether to exclude disabled records
     */
    public function setExcludeDisabledRecords(bool $exclude): void
    {
        $this->excludeDisabledRecords = $exclude;
        $this->metadata['exclude_disabled'] = $exclude;
    }

    /**
     * Add a record to the export
     */
    public function addSingleRecord(string $tableName, string $identifier, array $record): void
    {
        if (!isset($this->records[$tableName])) {
            $this->records[$tableName] = [];
            $this->exportedTables[] = $tableName;
        }

        if (!in_array($identifier, $this->records[$tableName], true)) {
            $this->records[$tableName][$identifier] = $record;
        }
    }

    /**
     * Add multiple records from a table
     */
    public function addTableRecords(string $tableName, array $records): void
    {
        foreach ($records as $record) {
            if (empty($record['uid'])) {
                continue;
            }
            $this->addSingleRecord($tableName, (string)$record['uid'], $record);
        }
    }

    /**
     * Add relation information
     */
    public function addRelation(string $fromTable, int $fromUid, string $field, string $toTable, int $toUid): void
    {
        $relationKey = $fromTable . ':' . $fromUid;

        if (!isset($this->relations[$relationKey])) {
            $this->relations[$relationKey] = [];
        }

        $this->relations[$relationKey][] = [
            'field' => $field,
            'to_table' => $toTable,
            'to_uid' => $toUid,
        ];
    }

    /**
     * Process the export (resolve dependencies, order records)
     */
    public function process(): string
    {
        // Resolve dependencies and order tables/records appropriately
        $this->resolveDependencies();
        $this->metadata['processed_at'] = time();
        $this->metadata['total_records'] = $this->getTotalRecordCount();
        $this->metadata['exported_tables'] = $this->exportedTables;
        return $this->compileMemoryToFileContent();
    }

    /**
     * Compile export to JSON file content
     */
    public function compileMemoryToFileContent(): string
    {
        $exportData = [
            'metadata' => $this->metadata,
            'records' => $this->records,
            'relations' => $this->relations,
        ];

        return $this->compileToJson($exportData);
    }

    /**
     * Get export metadata
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Get exported records
     */
    public function getRecords(): array
    {
        return $this->records;
    }

    /**
     * Get relations
     */
    public function getRelations(): array
    {
        return $this->relations;
    }

    /**
     * Get total record count
     */
    public function getTotalRecordCount(): int
    {
        $count = 0;
        foreach ($this->records as $uids) {
            $count += count($uids);
        }
        return $count;
    }

    /**
     * Get exported table names
     */
    public function getExportedTables(): array
    {
        return $this->exportedTables;
    }

    /**
     * Compile export data to JSON format
     */
    private function compileToJson(array $exportData): string
    {
        return json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Resolve dependencies between records
     */
    private function resolveDependencies(): void
    {
        // Simple dependency resolution - order tables by common dependency patterns
        $orderedTables = [];
        $remainingTables = $this->exportedTables;

        // Define typical TYPO3 table dependency order
        $dependencyOrder = [
            'be_groups', 'be_users', 'pages',
            'tt_content', 'sys_file_storage', 'sys_file', 'sys_file_reference',
            // Add other tables as needed
        ];

        // First, add tables in dependency order
        foreach ($dependencyOrder as $table) {
            if (in_array($table, $remainingTables, true)) {
                $orderedTables[] = $table;
                $remainingTables = array_filter($remainingTables, fn($t) => $t !== $table);
            }
        }

        // Add remaining tables
        $orderedTables = array_merge($orderedTables, $remainingTables);

        // Reorder records array based on dependency order
        $orderedRecords = [];
        foreach ($orderedTables as $table) {
            if (isset($this->records[$table])) {
                $orderedRecords[$table] = $this->records[$table];
            }
        }
        $this->records = $orderedRecords;
        $this->exportedTables = $orderedTables;
    }
}
