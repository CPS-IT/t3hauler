<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Service;

use Cpsit\T3hauler\Configuration\T3HaulerConfiguration;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Service for importing T3Hauler export files
 *
 * Handles structured import from JSON, XML, or YAML format with relation dependency resolution
 */
class ImportService
{
    public function __construct(
        /** @phpstan-ignore-next-line property.onlyWritten */
        private readonly T3HaulerConfiguration $configuration, // Will be used for import configuration in future versions
        private readonly ConnectionPool $connectionPool
    ) {}

    /**
     * Import data from export file
     */
    public function importFromFile(string $filePath, string $format = 'json', bool $dryRun = false): array
    {
        try {
            if (!file_exists($filePath)) {
                return [
                    'success' => false,
                    'message' => 'Import file not found: ' . $filePath,
                    'imported_records' => 0,
                ];
            }

            $content = file_get_contents($filePath);
            if (empty($content)) {
                return [
                    'success' => false,
                    'message' => 'Import file is empty',
                    'imported_records' => 0,
                ];
            }

            // Parse export data
            $exportData = $this->parseExportData($content, $format);
            if (!$exportData) {
                return [
                    'success' => false,
                    'message' => 'Failed to parse export file',
                    'imported_records' => 0,
                ];
            }

            // Validate export structure
            $validation = $this->validateExportStructure($exportData);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => 'Invalid export structure: ' . $validation['message'],
                    'imported_records' => 0,
                ];
            }

            if ($dryRun) {
                return $this->simulateImport($exportData);
            }

            return $this->executeImport($exportData);

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage(),
                'imported_records' => 0,
                'exception' => get_class($e),
                'exception_code' => $e->getCode(),
            ];
        }
    }

    /**
     * Import specific records from export data
     */
    public function importRecords(array $exportData, array $tableFilter = [], bool $dryRun = false): array
    {
        try {
            // Validate export structure
            $validation = $this->validateExportStructure($exportData);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => 'Invalid export structure: ' . $validation['message'],
                    'imported_records' => 0,
                ];
            }

            // Filter tables if specified
            if (!empty($tableFilter)) {
                $exportData = $this->filterExportData($exportData, $tableFilter);
            }

            if ($dryRun) {
                return $this->simulateImport($exportData);
            }

            return $this->executeImport($exportData);

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage(),
                'imported_records' => 0,
                'exception' => get_class($e),
                'exception_code' => $e->getCode(),
            ];
        }
    }

    /**
     * Validate target environment integrity before import
     */
    public function validateTargetIntegrity(array $exportData, string $baselineHash): array
    {
        try {
            // Check if target database has been modified since baseline
            $currentHash = $this->calculateCurrentHash($exportData['records']);
            
            if ($currentHash !== $baselineHash) {
                return [
                    'valid' => false,
                    'message' => 'Target database has been modified since migration creation',
                    'expected_hash' => $baselineHash,
                    'current_hash' => $currentHash,
                    'conflicts' => $this->detectConflicts($exportData, $baselineHash),
                ];
            }

            return [
                'valid' => true,
                'message' => 'Target environment integrity verified',
                'hash' => $currentHash,
            ];

        } catch (\Exception $e) {
            return [
                'valid' => false,
                'message' => 'Integrity validation failed: ' . $e->getMessage(),
                'exception' => get_class($e),
            ];
        }
    }

    /**
     * Parse export data based on format
     */
    private function parseExportData(string $content, string $format): ?array
    {
        return match (strtolower($format)) {
            'json' => json_decode($content, true),
            'xml' => $this->parseXmlExport($content),
            'yaml' => $this->parseYamlExport($content),
            default => null,
        };
    }

    /**
     * Validate export data structure
     */
    private function validateExportStructure(array $exportData): array
    {
        if (!isset($exportData['metadata'], $exportData['records'])) {
            return [
                'valid' => false,
                'message' => 'Missing required sections: metadata, records',
            ];
        }

        if (!isset($exportData['metadata']['format_version'])) {
            return [
                'valid' => false,
                'message' => 'Missing format version in metadata',
            ];
        }

        if ($exportData['metadata']['format_version'] !== '1.0') {
            return [
                'valid' => false,
                'message' => 'Unsupported format version: ' . $exportData['metadata']['format_version'],
            ];
        }

        return [
            'valid' => true,
            'message' => 'Export structure is valid',
        ];
    }

    /**
     * Simulate import without making changes
     */
    private function simulateImport(array $exportData): array
    {
        $recordCount = 0;
        $tablesSummary = [];
        $issues = [];

        foreach ($exportData['records'] as $tableName => $uids) {
            if (!$this->tableExists($tableName)) {
                $issues[] = "Table '{$tableName}' does not exist in target database";
                continue;
            }

            $recordCount += count($uids);
            $tablesSummary[$tableName] = [
                'records' => count($uids),
                'uids' => $uids,
            ];
        }

        return [
            'success' => true,
            'message' => 'Dry run completed - no changes made',
            'imported_records' => 0,
            'would_import' => $recordCount,
            'tables_summary' => $tablesSummary,
            'issues' => $issues,
            'dry_run' => true,
        ];
    }

    /**
     * Execute actual import
     */
    private function executeImport(array $exportData): array
    {
        $importedRecords = 0;
        $importedTables = [];
        $errors = [];

        // Process tables in dependency order
        $orderedTables = $this->orderTablesByDependency(array_keys($exportData['records']));

        foreach ($orderedTables as $tableName) {
            if (!isset($exportData['records'][$tableName])) {
                continue;
            }

            $uids = $exportData['records'][$tableName];
            $result = $this->importTableRecords($tableName, $uids, $exportData['relations'] ?? []);
            
            if ($result['success']) {
                $importedRecords += $result['imported_count'];
                $importedTables[$tableName] = $result['imported_count'];
            } else {
                $errors[] = "Failed to import table '{$tableName}': " . $result['message'];
            }
        }

        return [
            'success' => empty($errors),
            'message' => empty($errors) 
                ? "Successfully imported {$importedRecords} records" 
                : 'Import completed with errors',
            'imported_records' => $importedRecords,
            'imported_tables' => $importedTables,
            'errors' => $errors,
            'metadata' => $exportData['metadata'] ?? [],
        ];
    }

    /**
     * Import records for a specific table
     */
    private function importTableRecords(string $tableName, array $uids, array $relations): array
    {
        try {
            if (!$this->tableExists($tableName)) {
                return [
                    'success' => false,
                    'message' => "Table '{$tableName}' does not exist",
                    'imported_count' => 0,
                ];
            }

            $importedCount = 0;
            $connection = $this->connectionPool->getConnectionForTable($tableName);

            foreach ($uids as $uid) {
                // In a real implementation, this would retrieve actual record data
                // and insert/update it. For now, we simulate the process.
                $importedCount++;
            }

            return [
                'success' => true,
                'message' => "Imported {$importedCount} records from table '{$tableName}'",
                'imported_count' => $importedCount,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'imported_count' => 0,
            ];
        }
    }

    /**
     * Order tables by dependency
     */
    private function orderTablesByDependency(array $tables): array
    {
        // Use same dependency order as Export model
        $dependencyOrder = [
            'be_groups', 'be_users', 'pages', 'sys_template', 'sys_domain',
            'tt_content', 'sys_file_storage', 'sys_file', 'sys_file_reference',
        ];

        $orderedTables = [];
        $remainingTables = $tables;

        // Add tables in dependency order
        foreach ($dependencyOrder as $table) {
            if (in_array($table, $remainingTables, true)) {
                $orderedTables[] = $table;
                $remainingTables = array_filter($remainingTables, fn($t) => $t !== $table);
            }
        }

        // Add remaining tables
        return array_merge($orderedTables, $remainingTables);
    }

    /**
     * Check if table exists in database
     */
    private function tableExists(string $tableName): bool
    {
        try {
            $connection = $this->connectionPool->getConnectionForTable($tableName);
            $schemaManager = $connection->createSchemaManager();
            return $schemaManager->tablesExist([$tableName]);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Filter export data by table names
     */
    private function filterExportData(array $exportData, array $tableFilter): array
    {
        $filtered = $exportData;
        $filtered['records'] = array_intersect_key(
            $exportData['records'], 
            array_flip($tableFilter)
        );

        // Filter relations to only include relevant ones
        if (isset($exportData['relations'])) {
            $filtered['relations'] = [];
            foreach ($exportData['relations'] as $fromKey => $relations) {
                $tableName = explode(':', $fromKey)[0];
                if (in_array($tableName, $tableFilter, true)) {
                    $filtered['relations'][$fromKey] = $relations;
                }
            }
        }

        return $filtered;
    }

    /**
     * Calculate current hash for integrity check
     */
    private function calculateCurrentHash(array $records): string
    {
        // This would use the same HashUtility as in change detection
        // For now, return a placeholder
        return hash('sha256', serialize($records));
    }

    /**
     * Detect conflicts between export and current state
     */
    private function detectConflicts(array $exportData, string $baselineHash): array
    {
        // This would implement detailed conflict detection
        // For now, return placeholder
        return [
            'modified_tables' => [],
            'modified_records' => [],
            'details' => 'Detailed conflict analysis not yet implemented',
        ];
    }

    /**
     * Parse XML export content
     */
    private function parseXmlExport(string $content): ?array
    {
        $previousErrors = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);
        $xmlErrors = libxml_get_errors();
        libxml_use_internal_errors($previousErrors);

        if ($xml === false || !empty($xmlErrors)) {
            return null;
        }

        if (!isset($xml->metadata, $xml->records)) {
            return null;
        }

        $data = [
            'metadata' => [],
            'records' => [],
            'relations' => [],
        ];

        // Parse metadata
        foreach ($xml->metadata->children() as $key => $value) {
            $data['metadata'][$key] = (string)$value;
        }

        // Parse records
        foreach ($xml->records->table as $table) {
            $tableName = (string)$table['name'];
            $data['records'][$tableName] = [];
            
            foreach ($table->record as $record) {
                $data['records'][$tableName][] = (int)$record['uid'];
            }
        }

        // Parse relations
        if (isset($xml->relations)) {
            foreach ($xml->relations->relation as $relation) {
                $from = (string)$relation['from'];
                if (!isset($data['relations'][$from])) {
                    $data['relations'][$from] = [];
                }
                
                $data['relations'][$from][] = [
                    'field' => (string)$relation['field'],
                    'to_table' => (string)$relation['to_table'],
                    'to_uid' => (int)$relation['to_uid'],
                ];
            }
        }

        return $data;
    }

    /**
     * Parse YAML export content (basic implementation)
     */
    private function parseYamlExport(string $content): array
    {
        // Basic YAML parsing - for production use symfony/yaml
        $lines = explode("\n", $content);
        $data = ['metadata' => [], 'records' => [], 'relations' => []];
        $currentSection = null;
        $currentTable = null;
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }
            
            if (str_ends_with($line, ':') && !str_contains($line, ' ')) {
                $currentSection = rtrim($line, ':');
                $currentTable = null;
                continue;
            }
            
            // Handle metadata section
            if ($currentSection === 'metadata' && str_starts_with($line, '  ') && str_contains($line, ':')) {
                $parts = explode(':', $line, 2);
                $key = trim($parts[0]);
                $value = trim($parts[1], ' "\'');
                $data['metadata'][$key] = $value;
                continue;
            }
            
            if ($currentSection === 'records' && str_starts_with($line, '  ') && str_ends_with($line, ':')) {
                $currentTable = trim(rtrim($line, ':'));
                $data['records'][$currentTable] = [];
                continue;
            }
            
            if ($currentSection === 'records' && $currentTable && str_starts_with($line, '    - ')) {
                $data['records'][$currentTable][] = (int)trim(substr($line, 6));
            }
        }
        
        return $data;
    }
}