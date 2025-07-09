<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Service;

use Cpsit\T3hauler\Configuration\T3HaulerConfiguration;
use Cpsit\T3hauler\Domain\Model\DataSnapshot;
use Cpsit\T3hauler\Domain\Model\Export;
use Cpsit\T3hauler\Domain\Repository\DataSnapshotRepository;
use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Service for exporting changed data to JSON format
 *
 * Creates exports in JSON format with relation dependency resolution
 */
class ExportService
{
    public function __construct(
        private readonly T3HaulerConfiguration $configuration, // Will be used for export configuration in future versions
        private readonly ConnectionPool $connectionPool,
        private readonly DataSnapshotRepository $dataSnapshotRepository,
    ) {}

    /**
     * Export changed data to JSON format
     */
    public function exportChangedData(array $changedTables, string $outputPath): array
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
            $exportData = $export->process();

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
                'format' => 'json',
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
     * Export records from a table with filtering and field selection
     */
    private function exportTableRecords(Export $export, string $tableName): int
    {

        $connection = $this->connectionPool->getConnectionForTable($tableName);
        $queryBuilder = $connection->createQueryBuilder();

        try {
            $recordCount = 0;

            // Get configuration for field filtering
            $excludeFields = $this->configuration->get('detection.excludeFields', []);
            $allowedFields = $this->getAllowedFields($tableName, $excludeFields);

            // Always include crdate if available for new record detection
            if (!in_array('crdate', $allowedFields, true) && $this->tableHasField($tableName, 'crdate')) {
                $allowedFields[] = 'crdate';
            }

            // Build a query with field selection
            $result = $queryBuilder
                ->select(...$allowedFields)
                ->from($tableName);

            // Find the latest snapshot timestamp and limit records to those changed after the snapshot
            $latestSnapshot = $this->dataSnapshotRepository->findLatestByTableName($tableName);
            if (!$latestSnapshot instanceof DataSnapshot) {
                return 0;
            }

            $snapshotTimestamp = $latestSnapshot->getCreatedAt()->getTimestamp();
            $result->where(
                $queryBuilder->expr()->gt(
                    'tstamp',
                    $queryBuilder->createNamedParameter($snapshotTimestamp, Connection::PARAM_INT)
                )
            );

            $queryResult = $result->executeQuery();

            while ($row = $queryResult->fetchAssociative()) {
                if (empty($row['uid'])) {
                    continue;
                }
                // Check if this is a new record created after snapshot
                $isNewRecord = $this->isNewRecord($row, $snapshotTimestamp);

                if ($isNewRecord) {
                    // replace uid with generated new uid
                    $row['uid'] = $this->generateNewRecordUid();
                }
                $export->addSingleRecord($tableName, (string)$row['uid'], $row);

                $recordCount++;
            }

            return $recordCount;

        } catch (Exception $e) {
            // Table might not exist or have issues, skip it
            return 0;
        }
    }

    /**
     * Add relations for exported records
     * @phpstan-ignore method.unused
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
     * Get allowed fields for a table, excluding configured excluded fields
     */
    private function getAllowedFields(string $tableName, array $excludeFields): array
    {
        $connection = $this->connectionPool->getConnectionForTable($tableName);

        try {
            // Get all columns from table schema
            $schemaManager = $connection->createSchemaManager();
            $columns = $schemaManager->listTableColumns($tableName);

            $allowedFields = [];
            foreach ($columns as $column) {
                $fieldName = $column->getName();

                // Always include uid field
                if ($fieldName === 'uid') {
                    $allowedFields[] = $fieldName;
                    continue;
                }

                // Exclude configured fields
                if (!in_array($fieldName, $excludeFields, true)) {
                    $allowedFields[] = $fieldName;
                }
            }

            // Ensure we have at least uid field
            if (empty($allowedFields) || !in_array('uid', $allowedFields, true)) {
                $allowedFields = ['uid'];
            }

            return $allowedFields;

        } catch (\Exception $e) {
            // If we can't get schema info, fall back to basic fields
            return ['*'];
        }
    }

    /**
     * Validate export file
     */
    public function validateExportFile(string $filePath): array
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
            $data = json_decode($content, true);

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
                'format' => 'json',
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
     * Check if table has a specific field
     */
    private function tableHasField(string $tableName, string $fieldName): bool
    {
        $connection = $this->connectionPool->getConnectionForTable($tableName);

        try {
            $schemaManager = $connection->createSchemaManager();
            $columns = $schemaManager->listTableColumns($tableName);

            foreach ($columns as $column) {
                if ($column->getName() === $fieldName) {
                    return true;
                }
            }

            return false;

        } catch (\Exception $e) {
            // If we can't check schema, assume common fields exist
            return in_array($fieldName, ['uid', 'pid', 'tstamp', 'crdate', 'cruser_id'], true);
        }
    }

    /**
     * Check if a record is new (created after snapshot timestamp)
     */
    private function isNewRecord(array $row, ?int $snapshotTimestamp): bool
    {
        // If no snapshot timestamp provided, treat all as existing records
        if ($snapshotTimestamp === null) {
            return false;
        }

        // Check if record has crdate field and was created after snapshot
        if (isset($row['crdate']) && (int)$row['crdate'] > $snapshotTimestamp) {
            return true;
        }

        return false;
    }

    /**
     * Generate a unique identifier for new records
     */
    private function generateNewRecordUid(): string
    {
        return uniqid('NEW', false);
    }

    /**
     * Add a new record with NEW<uniqueid> to export
     * @phpstan-ignore method.unused
     */
    private function addNewRecord(Export $export, string $tableName, string $newUid, array $recordData): void
    {
        // Create a modified record data with NEW UID
        $modifiedRecord = $recordData;
        $modifiedRecord['uid'] = $newUid;

        // Add the record with the new UID
        // Note: This is a conceptual implementation - the actual Export model
        // may need modifications to support string UIDs properly
        $export->addSingleRecord($tableName, $newUid, record: $modifiedRecord);
    }
}
