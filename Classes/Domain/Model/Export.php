<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Domain\Model;

/**
 * Custom export model for T3Hauler data exports
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
     * Initialize export with page ID and type
     */
    public function init(int $pageId, string $type): void
    {
        $this->metadata['page_id'] = $pageId;
        $this->metadata['export_type'] = $type;
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
    public function export_addRecord(string $tableName, int $uid): void
    {
        if (!isset($this->records[$tableName])) {
            $this->records[$tableName] = [];
            $this->exportedTables[] = $tableName;
        }

        if (!in_array($uid, $this->records[$tableName], true)) {
            $this->records[$tableName][] = $uid;
        }
    }

    /**
     * Add multiple records from a table
     */
    public function addTableRecords(string $tableName, array $uids): void
    {
        foreach ($uids as $uid) {
            $this->export_addRecord($tableName, (int)$uid);
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
    public function process(): void
    {
        // Resolve dependencies and order tables/records appropriately
        $this->resolveDependencies();
        $this->metadata['processed_at'] = time();
        $this->metadata['total_records'] = $this->getTotalRecordCount();
        $this->metadata['exported_tables'] = $this->exportedTables;
    }

    /**
     * Compile export to file content
     */
    public function compileMemoryToFileContent(string $format = 'json'): string
    {
        $exportData = [
            'metadata' => $this->metadata,
            'records' => $this->records,
            'relations' => $this->relations,
        ];

        return match (strtolower($format)) {
            'json' => $this->compileToJson($exportData),
            'xml' => $this->compileToXml($exportData),
            'yaml' => $this->compileToYaml($exportData),
            default => throw new \InvalidArgumentException('Unsupported export format: ' . $format, 1909234567),
        };
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
     * Compile export data to XML format
     */
    private function compileToXml(array $exportData): string
    {
        $xml = new \DOMDocument('1.0', $this->charset);
        $xml->formatOutput = true;

        $root = $xml->createElement('t3hauler_export');
        $xml->appendChild($root);

        // Add metadata
        $metadataNode = $xml->createElement('metadata');
        $root->appendChild($metadataNode);
        foreach ($exportData['metadata'] as $key => $value) {
            $node = $xml->createElement($key, htmlspecialchars((string)$value));
            $metadataNode->appendChild($node);
        }

        // Add records
        $recordsNode = $xml->createElement('records');
        $root->appendChild($recordsNode);
        foreach ($exportData['records'] as $tableName => $uids) {
            $tableNode = $xml->createElement('table');
            $tableNode->setAttribute('name', $tableName);
            $recordsNode->appendChild($tableNode);
            
            foreach ($uids as $uid) {
                $recordNode = $xml->createElement('record');
                $recordNode->setAttribute('uid', (string)$uid);
                $tableNode->appendChild($recordNode);
            }
        }

        // Add relations
        $relationsNode = $xml->createElement('relations');
        $root->appendChild($relationsNode);
        foreach ($exportData['relations'] as $fromKey => $relations) {
            foreach ($relations as $relation) {
                $relationNode = $xml->createElement('relation');
                $relationNode->setAttribute('from', $fromKey);
                $relationNode->setAttribute('field', $relation['field']);
                $relationNode->setAttribute('to_table', $relation['to_table']);
                $relationNode->setAttribute('to_uid', (string)$relation['to_uid']);
                $relationsNode->appendChild($relationNode);
            }
        }

        return $xml->saveXML() ?: '';
    }

    /**
     * Compile export data to YAML format
     */
    private function compileToYaml(array $exportData): string
    {
        // Simple YAML serialization - for complex cases, use symfony/yaml
        $yaml = "# T3Hauler Export File\n";
        $yaml .= "# Generated: " . date('Y-m-d H:i:s') . "\n\n";
        
        $yaml .= "metadata:\n";
        foreach ($exportData['metadata'] as $key => $value) {
            $yaml .= "  {$key}: " . $this->yamlValue($value) . "\n";
        }
        
        $yaml .= "\nrecords:\n";
        foreach ($exportData['records'] as $tableName => $uids) {
            $yaml .= "  {$tableName}:\n";
            foreach ($uids as $uid) {
                $yaml .= "    - {$uid}\n";
            }
        }
        
        $yaml .= "\nrelations:\n";
        foreach ($exportData['relations'] as $fromKey => $relations) {
            $yaml .= "  \"{$fromKey}\":\n";
            foreach ($relations as $relation) {
                $yaml .= "    - field: \"{$relation['field']}\"\n";
                $yaml .= "      to_table: \"{$relation['to_table']}\"\n";
                $yaml .= "      to_uid: {$relation['to_uid']}\n";
            }
        }
        
        return $yaml;
    }

    /**
     * Format value for YAML output
     */
    private function yamlValue(mixed $value): string
    {
        if (is_string($value)) {
            return "\"{$value}\"";
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_array($value)) {
            return '[' . implode(', ', array_map([$this, 'yamlValue'], $value)) . ']';
        }
        return (string)$value;
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
            'be_groups', 'be_users', 'pages', 'sys_template', 'sys_domain',
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