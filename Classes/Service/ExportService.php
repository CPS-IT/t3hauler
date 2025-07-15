<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Service;

use Cpsit\T3hauler\Configuration\SettingsInterface;
use Cpsit\T3hauler\Configuration\T3HaulerConfiguration;
use Cpsit\T3hauler\Domain\Dto\ExportResult;
use Cpsit\T3hauler\Domain\Dto\ExportValidationResult;
use Cpsit\T3hauler\Domain\Enumeration\ExportStatus;
use Cpsit\T3hauler\Domain\Enumeration\ExportValidationStatus;
use Cpsit\T3hauler\Domain\Model\DataSnapshot;
use Cpsit\T3hauler\Domain\Model\Export;
use Cpsit\T3hauler\Domain\Repository\DataSnapshotRepository;
use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;

/**
 * Service for exporting changed data to JSON format
 *
 * Creates exports in JSON format with relation dependency resolution
 */
readonly class ExportService
{
    // Message constants
    public const string MESSAGE_NO_TABLES = 'No changed tables to export';
    public const string MESSAGE_NO_RECORDS = 'No records found to export from changed tables';
    public const string MESSAGE_EXPORT_NO_DATA = 'Export generated no data';
    public const string MESSAGE_DIRECTORY_CREATION_FAILED = 'Failed to create output directory';
    public const string MESSAGE_FILE_WRITE_FAILED = 'Failed to write export file';
    public const string MESSAGE_EXPORT_FAILED = 'Export failed';
    public const string MESSAGE_EXPORT_SUCCESS = 'Successfully exported %d records to %s';
    public const string MESSAGE_FILE_NOT_FOUND = 'Export file not found';
    public const string MESSAGE_FILE_EMPTY = 'Export file is empty';
    public const string MESSAGE_INVALID_STRUCTURE = 'Export file does not have valid structure';
    public const string MESSAGE_PARSE_FAILED = 'Failed to parse export file';
    public const string MESSAGE_FILE_VALID = 'Export file is valid';

    // Default values
    public const string DEFAULT_CHARSET = 'utf-8';
    public const string DEFAULT_FORMAT = 'json';

    public function __construct(
        private T3HaulerConfiguration $configuration,
        private ConnectionPool $connectionPool,
        private DataSnapshotRepository $dataSnapshotRepository,
        private FilesystemInterface $filesystem,
    ) {}

    /**
     * Export changed data to JSON format
     */
    public function exportChangedData(array $changedTables, string $outputPath): ExportResult
    {
        try {
            if (empty($changedTables)) {
                return ExportResult::failure(
                    ExportStatus::NO_TABLES,
                    self::MESSAGE_NO_TABLES
                );
            }

            // Create export instance
            $export = new Export();
            $export->setCharset(self::DEFAULT_CHARSET);
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
                return ExportResult::failure(
                    ExportStatus::NO_RECORDS,
                    self::MESSAGE_NO_RECORDS
                );
            }

            // Process export and save to file
            $exportData = $export->process();

            if (empty($exportData)) {
                return ExportResult::failure(
                    ExportStatus::EXPORT_FAILED,
                    self::MESSAGE_EXPORT_NO_DATA
                );
            }

            // Save to file
            $outputDir = dirname($outputPath);
            if (!$this->filesystem->isDirectory($outputDir) && !mkdir($outputDir, 0755, true)) {
                return ExportResult::failure(
                    ExportStatus::DIRECTORY_CREATION_FAILED,
                    self::MESSAGE_DIRECTORY_CREATION_FAILED . ': ' . $outputDir
                );
            }

            if ($this->filesystem->putFileContents($outputPath, $exportData) === false) {
                return ExportResult::failure(
                    ExportStatus::FILE_WRITE_FAILED,
                    self::MESSAGE_FILE_WRITE_FAILED . ': ' . $outputPath
                );
            }

            return ExportResult::success(
                message: sprintf(self::MESSAGE_EXPORT_SUCCESS, $totalRecords, $outputPath),
                recordCount: $totalRecords,
                exportedTables: $exportedTables,
                filePath: $outputPath,
                fileSize: $this->filesystem->getFileSize($outputPath) ?: 0,
                format: self::DEFAULT_FORMAT,
                metadata: $export->getMetadata()
            );

        } catch (\Exception $e) {
            return ExportResult::failure(
                ExportStatus::EXPORT_FAILED,
                self::MESSAGE_EXPORT_FAILED . ': ' . $e->getMessage(),
                get_class($e),
                $e->getCode()
            );
        }
    }

    /**
     * Export records from a table with filtering and field selection
     */
    private function exportTableRecords(Export $export, string $tableName): int
    {

        $connection = $this->connectionPool->getConnectionForTable($tableName);
        $queryBuilder = $connection->createQueryBuilder();
        if ($this->configuration->get('t3hauler.export.includeHidden', false)) {
            $queryBuilder->getRestrictions()->removeByType(HiddenRestriction::class);
        }
        if ($this->configuration->get('t3hauler.export.includeDeleted', false)) {
            $queryBuilder->getRestrictions()->removeByType(DeletedRestriction::class);
        }
        try {
            $recordCount = 0;

            // Get configuration for field filtering
            $excludeFields = $this->configuration->get(SettingsInterface::DETECTION_EXCLUDE_FIELDS, []);
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

            $sql = $result->getSQL();
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
    public function validateExportFile(string $filePath): ExportValidationResult
    {
        if (!$this->filesystem->exists($filePath)) {
            return ExportValidationResult::invalid(
                ExportValidationStatus::FILE_NOT_FOUND,
                self::MESSAGE_FILE_NOT_FOUND . ': ' . $filePath
            );
        }

        $content = $this->filesystem->getFileContents($filePath);
        if (empty($content)) {
            return ExportValidationResult::invalid(
                ExportValidationStatus::FILE_EMPTY,
                self::MESSAGE_FILE_EMPTY
            );
        }

        try {
            $data = json_decode($content, true);

            if (!$data || !isset($data['metadata'], $data['records'])) {
                return ExportValidationResult::invalid(
                    ExportValidationStatus::INVALID_STRUCTURE,
                    self::MESSAGE_INVALID_STRUCTURE
                );
            }

            return ExportValidationResult::valid(
                message: self::MESSAGE_FILE_VALID,
                fileSize: $this->filesystem->getFileSize($filePath) ?: 0,
                recordCount: $data['metadata']['total_records'] ?? 0,
                format: self::DEFAULT_FORMAT,
                metadata: $data['metadata']
            );

        } catch (\Exception $e) {
            return ExportValidationResult::invalid(
                ExportValidationStatus::PARSE_ERROR,
                self::MESSAGE_PARSE_FAILED . ': ' . $e->getMessage()
            );
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
