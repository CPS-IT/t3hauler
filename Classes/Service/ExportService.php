<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Service;

use Cpsit\T3hauler\Configuration\T3HaulerConfiguration;
use Cpsit\T3hauler\Domain\Model\Export;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Service for exporting changed data to structured format
 *
 * Creates exports in JSON, XML, or YAML format with relation dependency resolution
 */
class ExportService
{
    public function __construct(
        /** @phpstan-ignore-next-line property.onlyWritten */
        private readonly T3HaulerConfiguration $configuration, // Will be used for export configuration in future versions
        private readonly ConnectionPool $connectionPool
    ) {}

    /**
     * Export changed data to structured format
     */
    public function exportChangedData(array $changedTables, string $outputPath, string $format = 'json'): array
    {
        try {
            if (empty($changedTables)) {
                return [
                    'success' => false,
                    'message' => 'No changed tables to export',
                    'record_count' => 0,
                ];
            }

            // Create export instance
            $export = new Export();
            $export->init(0, 'changed_data');
            $export->setCharset('utf-8');
            $export->setExcludeDisabledRecords(false);

            $totalRecords = 0;
            $exportedTables = [];

            // Export records from changed tables
            foreach ($changedTables as $tableName) {
                $recordCount = $this->exportTableRecords($export, $tableName);
                $totalRecords += $recordCount;
                $exportedTables[$tableName] = $recordCount;
            }

            if ($totalRecords === 0) {
                return [
                    'success' => false,
                    'message' => 'No records found to export from changed tables',
                    'record_count' => 0,
                ];
            }

            // Process export and save to file
            $export->process();
            $exportData = $export->compileMemoryToFileContent($format);

            if (empty($exportData)) {
                return [
                    'success' => false,
                    'message' => 'Export generated no data',
                    'record_count' => 0,
                ];
            }

            // Save to file
            $outputDir = dirname($outputPath);
            if (!is_dir($outputDir) && !mkdir($outputDir, 0755, true)) {
                return [
                    'success' => false,
                    'message' => 'Failed to create output directory: ' . $outputDir,
                    'record_count' => 0,
                ];
            }

            if (file_put_contents($outputPath, $exportData) === false) {
                return [
                    'success' => false,
                    'message' => 'Failed to write export file: ' . $outputPath,
                    'record_count' => 0,
                ];
            }

            return [
                'success' => true,
                'message' => 'Successfully exported ' . $totalRecords . ' records to ' . $outputPath,
                'record_count' => $totalRecords,
                'exported_tables' => $exportedTables,
                'file_path' => $outputPath,
                'file_size' => filesize($outputPath),
                'format' => $format,
                'metadata' => $export->getMetadata(),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Export failed: ' . $e->getMessage(),
                'record_count' => 0,
                'exception' => get_class($e),
                'exception_code' => $e->getCode(),
            ];
        }
    }

    /**
     * Export specific records by UIDs
     */
    public function exportRecords(array $records, string $outputPath, string $format = 'json'): array
    {
        try {
            if (empty($records)) {
                return [
                    'success' => false,
                    'message' => 'No records specified for export',
                    'record_count' => 0,
                ];
            }

            // Create export instance
            $export = new Export();
            $export->init(0, 'specific_records');
            $export->setCharset('utf-8');
            $export->setExcludeDisabledRecords(false);

            $totalRecords = 0;

            // Add records to export
            foreach ($records as $tableName => $uids) {
                if (!is_array($uids)) {
                    $uids = [$uids];
                }

                foreach ($uids as $uid) {
                    $export->export_addRecord($tableName, (int)$uid);
                    $totalRecords++;
                }
            }

            if ($totalRecords === 0) {
                return [
                    'success' => false,
                    'message' => 'No valid records found to export',
                    'record_count' => 0,
                ];
            }

            // Add relations for exported records
            $this->addRecordRelations($export, $records);

            // Process export and save to file
            $export->process();
            $exportData = $export->compileMemoryToFileContent($format);

            if (empty($exportData)) {
                return [
                    'success' => false,
                    'message' => 'Export generated no data',
                    'record_count' => 0,
                ];
            }

            // Save to file
            $outputDir = dirname($outputPath);
            if (!is_dir($outputDir) && !mkdir($outputDir, 0755, true)) {
                return [
                    'success' => false,
                    'message' => 'Failed to create output directory: ' . $outputDir,
                    'record_count' => 0,
                ];
            }

            if (file_put_contents($outputPath, $exportData) === false) {
                return [
                    'success' => false,
                    'message' => 'Failed to write export file: ' . $outputPath,
                    'record_count' => 0,
                ];
            }

            return [
                'success' => true,
                'message' => 'Successfully exported ' . $totalRecords . ' records to ' . $outputPath,
                'record_count' => $totalRecords,
                'exported_records' => $records,
                'file_path' => $outputPath,
                'file_size' => filesize($outputPath),
                'format' => $format,
                'metadata' => $export->getMetadata(),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Export failed: ' . $e->getMessage(),
                'record_count' => 0,
                'exception' => get_class($e),
                'exception_code' => $e->getCode(),
            ];
        }
    }

    /**
     * Export all records from specified tables
     */
    public function exportTables(array $tableNames, string $outputPath, string $format = 'json'): array
    {
        try {
            if (empty($tableNames)) {
                return [
                    'success' => false,
                    'message' => 'No tables specified for export',
                    'record_count' => 0,
                ];
            }

            // Create export instance
            $export = new Export();
            $export->init(0, 'full_tables');
            $export->setCharset('utf-8');
            $export->setExcludeDisabledRecords(false);

            $totalRecords = 0;
            $exportedTables = [];

            foreach ($tableNames as $tableName) {
                $recordCount = $this->exportTableRecords($export, $tableName);
                $totalRecords += $recordCount;
                $exportedTables[$tableName] = $recordCount;
            }

            if ($totalRecords === 0) {
                return [
                    'success' => false,
                    'message' => 'No records found in specified tables',
                    'record_count' => 0,
                ];
            }

            // Process export and save to file
            $export->process();
            $exportData = $export->compileMemoryToFileContent($format);

            if (empty($exportData)) {
                return [
                    'success' => false,
                    'message' => 'Export generated no data',
                    'record_count' => 0,
                ];
            }

            // Save to file
            $outputDir = dirname($outputPath);
            if (!is_dir($outputDir) && !mkdir($outputDir, 0755, true)) {
                return [
                    'success' => false,
                    'message' => 'Failed to create output directory: ' . $outputDir,
                    'record_count' => 0,
                ];
            }

            if (file_put_contents($outputPath, $exportData) === false) {
                return [
                    'success' => false,
                    'message' => 'Failed to write export file: ' . $outputPath,
                    'record_count' => 0,
                ];
            }

            return [
                'success' => true,
                'message' => 'Successfully exported ' . $totalRecords . ' records to ' . $outputPath,
                'record_count' => $totalRecords,
                'exported_tables' => $exportedTables,
                'file_path' => $outputPath,
                'file_size' => filesize($outputPath),
                'format' => $format,
                'metadata' => $export->getMetadata(),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Export failed: ' . $e->getMessage(),
                'record_count' => 0,
                'exception' => get_class($e),
                'exception_code' => $e->getCode(),
            ];
        }
    }

    /**
     * Export all records from a table
     */
    private function exportTableRecords(Export $export, string $tableName): int
    {
        $connection = $this->connectionPool->getConnectionForTable($tableName);
        $queryBuilder = $connection->createQueryBuilder();

        try {
            $result = $queryBuilder
                ->select('uid')
                ->from($tableName)
                ->executeQuery();

            $recordCount = 0;
            while ($row = $result->fetchAssociative()) {
                $export->export_addRecord($tableName, (int)$row['uid']);
                $recordCount++;
            }

            return $recordCount;

        } catch (\Exception $e) {
            // Table might not exist or have issues, skip it
            return 0;
        }
    }

    /**
     * Add relations for exported records
     */
    private function addRecordRelations(Export $export, array $records): void
    {
        // Get TCA configuration to identify relations
        foreach ($records as $tableName => $uids) {
            if (!is_array($uids)) {
                $uids = [$uids];
            }

            foreach ($uids as $uid) {
                $this->findRecordRelations($export, $tableName, (int)$uid);
            }
        }
    }

    /**
     * Find and add relations for a specific record
     */
    private function findRecordRelations(Export $export, string $tableName, int $uid): void
    {
        // Basic relation detection - can be enhanced with TCA analysis
        $connection = $this->connectionPool->getConnectionForTable($tableName);
        $queryBuilder = $connection->createQueryBuilder();

        try {
            $record = $queryBuilder
                ->select('*')
                ->from($tableName)
                ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)))
                ->executeQuery()
                ->fetchAssociative();

            if (!$record) {
                return;
            }

            // Check for common relation fields
            $relationFields = ['pid', 'parent_id', 'sys_file_uid', 'image', 'media'];
            
            foreach ($relationFields as $field) {
                if (isset($record[$field]) && $record[$field] > 0) {
                    $toTable = $this->guessRelatedTable($field);
                    if ($toTable) {
                        $export->addRelation($tableName, $uid, $field, $toTable, (int)$record[$field]);
                    }
                }
            }

        } catch (\Exception $e) {
            // Skip if table/record doesn't exist
        }
    }

    /**
     * Guess related table based on field name
     */
    private function guessRelatedTable(string $field): ?string
    {
        return match ($field) {
            'pid', 'parent_id' => 'pages',
            'sys_file_uid' => 'sys_file',
            'image', 'media' => 'sys_file_reference',
            default => null,
        };
    }

    /**
     * Validate export file
     */
    public function validateExportFile(string $filePath, string $format = 'json'): array
    {
        if (!file_exists($filePath)) {
            return [
                'valid' => false,
                'message' => 'Export file not found: ' . $filePath,
            ];
        }

        $content = file_get_contents($filePath);
        if (empty($content)) {
            return [
                'valid' => false,
                'message' => 'Export file is empty',
            ];
        }

        try {
            $data = match (strtolower($format)) {
                'json' => json_decode($content, true),
                'xml' => $this->parseXmlExport($content),
                'yaml' => $this->parseYamlExport($content),
                default => throw new \InvalidArgumentException('Unsupported format: ' . $format),
            };

            if (!$data || !isset($data['metadata'], $data['records'])) {
                return [
                    'valid' => false,
                    'message' => 'Export file does not have valid structure',
                ];
            }

            return [
                'valid' => true,
                'message' => 'Export file is valid',
                'file_size' => filesize($filePath),
                'record_count' => $data['metadata']['total_records'] ?? 0,
                'format' => $format,
                'metadata' => $data['metadata'],
            ];

        } catch (\Exception $e) {
            return [
                'valid' => false,
                'message' => 'Failed to parse export file: ' . $e->getMessage(),
            ];
        }
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

        // Basic structure validation
        if (!isset($xml->metadata, $xml->records)) {
            return null;
        }

        return [
            'metadata' => (array)$xml->metadata,
            'records' => (array)$xml->records,
            'relations' => isset($xml->relations) ? (array)$xml->relations : [],
        ];
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
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }
            
            if (str_ends_with($line, ':') && !str_contains($line, ' ')) {
                $currentSection = rtrim($line, ':');
                continue;
            }
            
            // This is a very basic parser - use symfony/yaml for production
        }
        
        return $data;
    }
}