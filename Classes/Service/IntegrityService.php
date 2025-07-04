<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Service;

use Cpsit\T3hauler\Configuration\T3HaulerConfiguration;
use Cpsit\T3hauler\Utility\HashUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Service for data integrity validation and conflict detection
 */
class IntegrityService
{
    public function __construct(
        private readonly T3HaulerConfiguration $configuration,
        private readonly HashUtility $hashUtility,
        private readonly ConnectionPool $connectionPool
    ) {}

    /**
     * Validate migration integrity against current database state
     */
    public function validateMigrationIntegrity(string $migrationHash, array $affectedTables): array
    {
        try {
            $excludeFields = $this->configuration->get('detection.excludeFields', []);
            $currentHash = $this->calculateCurrentTableHash($affectedTables, $excludeFields);

            if ($currentHash !== $migrationHash) {
                $conflicts = $this->detectConflicts($affectedTables, $migrationHash);
                
                return [
                    'valid' => false,
                    'message' => 'Database has been modified since migration creation',
                    'expected_hash' => $migrationHash,
                    'current_hash' => $currentHash,
                    'affected_tables' => $affectedTables,
                    'conflicts' => $conflicts,
                ];
            }

            return [
                'valid' => true,
                'message' => 'Migration integrity validated successfully',
                'hash' => $currentHash,
                'affected_tables' => $affectedTables,
            ];

        } catch (\Exception $e) {
            return [
                'valid' => false,
                'message' => 'Integrity validation failed: ' . $e->getMessage(),
                'exception' => get_class($e),
                'exception_code' => $e->getCode(),
            ];
        }
    }

    /**
     * Check for conflicts between export data and current state
     */
    public function checkExportConflicts(array $exportData, string $baselineHash): array
    {
        try {
            if (!isset($exportData['records'])) {
                return [
                    'has_conflicts' => false,
                    'message' => 'No records in export data to check',
                    'conflicts' => [],
                ];
            }

            $tables = array_keys($exportData['records']);
            $excludeFields = $this->configuration->get('detection.excludeFields', []);
            $currentHash = $this->calculateCurrentTableHash($tables, $excludeFields);

            if ($currentHash === $baselineHash) {
                return [
                    'has_conflicts' => false,
                    'message' => 'No conflicts detected',
                    'baseline_hash' => $baselineHash,
                    'current_hash' => $currentHash,
                    'conflicts' => [],
                ];
            }

            $conflicts = $this->detectDetailedConflicts($exportData, $baselineHash);

            return [
                'has_conflicts' => true,
                'message' => 'Conflicts detected between export and current state',
                'baseline_hash' => $baselineHash,
                'current_hash' => $currentHash,
                'conflicts' => $conflicts,
                'resolution_suggestions' => $this->generateResolutionSuggestions($conflicts),
            ];

        } catch (\Exception $e) {
            return [
                'has_conflicts' => true,
                'message' => 'Conflict detection failed: ' . $e->getMessage(),
                'exception' => get_class($e),
                'conflicts' => [],
            ];
        }
    }

    /**
     * Validate pre-import requirements
     */
    public function validatePreImportRequirements(array $exportData): array
    {
        $issues = [];
        $warnings = [];

        try {
            // Check required tables exist
            if (isset($exportData['records'])) {
                foreach (array_keys($exportData['records']) as $tableName) {
                    if (!$this->tableExists($tableName)) {
                        $issues[] = "Table '{$tableName}' does not exist in target database";
                    }
                }
            }

            // Check disk space for import operations
            $diskSpaceCheck = $this->checkDiskSpace();
            if (!$diskSpaceCheck['sufficient']) {
                $warnings[] = $diskSpaceCheck['message'];
            }

            // Check database constraints
            $constraintCheck = $this->checkDatabaseConstraints($exportData);
            if (!$constraintCheck['valid']) {
                $issues = array_merge($issues, $constraintCheck['issues']);
            }

            // Check for potential dependency issues
            if (isset($exportData['relations'])) {
                $dependencyCheck = $this->checkDependencyIntegrity($exportData['relations']);
                if (!$dependencyCheck['valid']) {
                    $warnings = array_merge($warnings, $dependencyCheck['warnings']);
                }
            }

            return [
                'valid' => empty($issues),
                'message' => empty($issues) ? 'Pre-import validation passed' : 'Pre-import validation failed',
                'issues' => $issues,
                'warnings' => $warnings,
                'can_proceed' => empty($issues),
            ];

        } catch (\Exception $e) {
            return [
                'valid' => false,
                'message' => 'Pre-import validation failed: ' . $e->getMessage(),
                'issues' => ['Validation process failed: ' . $e->getMessage()],
                'warnings' => [],
                'can_proceed' => false,
            ];
        }
    }

    /**
     * Create integrity checkpoint before import
     */
    public function createIntegrityCheckpoint(array $tables, string $checkpointId): array
    {
        try {
            $excludeFields = $this->configuration->get('detection.excludeFields', []);
            $checkpoint = [
                'id' => $checkpointId,
                'created_at' => time(),
                'tables' => [],
            ];

            foreach ($tables as $tableName) {
                if ($this->tableExists($tableName)) {
                    $hash = $this->hashUtility->calculateTableHash($tableName, $excludeFields);
                    $recordCount = $this->getTableRecordCount($tableName);
                    
                    $checkpoint['tables'][$tableName] = [
                        'hash' => $hash,
                        'record_count' => $recordCount,
                        'timestamp' => time(),
                    ];
                }
            }

            // Store checkpoint (in real implementation, this would be persisted)
            return [
                'success' => true,
                'message' => 'Integrity checkpoint created successfully',
                'checkpoint_id' => $checkpointId,
                'checkpoint_data' => $checkpoint,
                'tables_included' => count($checkpoint['tables']),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to create integrity checkpoint: ' . $e->getMessage(),
                'checkpoint_id' => $checkpointId,
                'exception' => get_class($e),
            ];
        }
    }

    /**
     * Calculate current hash for multiple tables
     */
    private function calculateCurrentTableHash(array $tables, array $excludeFields): string
    {
        $tableHashes = [];
        
        foreach ($tables as $tableName) {
            if ($this->tableExists($tableName)) {
                $tableHashes[$tableName] = $this->hashUtility->calculateTableHash($tableName, $excludeFields);
            }
        }
        
        return hash('sha256', serialize($tableHashes));
    }

    /**
     * Detect conflicts between migration and current state
     */
    private function detectConflicts(array $tables, string $expectedHash): array
    {
        $conflicts = [];
        $excludeFields = $this->configuration->get('detection.excludeFields', []);

        foreach ($tables as $tableName) {
            if (!$this->tableExists($tableName)) {
                $conflicts[] = [
                    'type' => 'missing_table',
                    'table' => $tableName,
                    'message' => "Table '{$tableName}' does not exist",
                ];
                continue;
            }

            $currentHash = $this->hashUtility->calculateTableHash($tableName, $excludeFields);
            // In a real implementation, we would compare with stored baseline hash per table
            // For now, we just report potential modifications
            $conflicts[] = [
                'type' => 'table_modified',
                'table' => $tableName,
                'message' => "Table '{$tableName}' may have been modified",
                'current_hash' => $currentHash,
            ];
        }

        return $conflicts;
    }

    /**
     * Detect detailed conflicts for export data
     */
    private function detectDetailedConflicts(array $exportData, string $baselineHash): array
    {
        $conflicts = [];

        if (!isset($exportData['records'])) {
            return $conflicts;
        }

        foreach ($exportData['records'] as $tableName => $uids) {
            if (!$this->tableExists($tableName)) {
                $conflicts[] = [
                    'type' => 'missing_table',
                    'table' => $tableName,
                    'severity' => 'error',
                    'message' => "Target table '{$tableName}' does not exist",
                ];
                continue;
            }

            // Check for record conflicts
            $recordConflicts = $this->checkRecordConflicts($tableName, $uids);
            $conflicts = array_merge($conflicts, $recordConflicts);
        }

        return $conflicts;
    }

    /**
     * Check for conflicts in specific records
     */
    private function checkRecordConflicts(string $tableName, array $uids): array
    {
        $conflicts = [];
        $connection = $this->connectionPool->getConnectionForTable($tableName);

        try {
            foreach ($uids as $uid) {
                $queryBuilder = $connection->createQueryBuilder();
                $record = $queryBuilder
                    ->select('*')
                    ->from($tableName)
                    ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid)))
                    ->executeQuery()
                    ->fetchAssociative();

                if ($record) {
                    // Record exists - check if it has been modified
                    $conflicts[] = [
                        'type' => 'record_exists',
                        'table' => $tableName,
                        'uid' => $uid,
                        'severity' => 'warning',
                        'message' => "Record {$tableName}:{$uid} already exists and may be overwritten",
                    ];
                }
            }

        } catch (\Exception $e) {
            $conflicts[] = [
                'type' => 'check_failed',
                'table' => $tableName,
                'severity' => 'error',
                'message' => "Failed to check records in table '{$tableName}': " . $e->getMessage(),
            ];
        }

        return $conflicts;
    }

    /**
     * Generate resolution suggestions for conflicts
     */
    private function generateResolutionSuggestions(array $conflicts): array
    {
        $suggestions = [];

        foreach ($conflicts as $conflict) {
            switch ($conflict['type']) {
                case 'missing_table':
                    $suggestions[] = "Create missing table '{$conflict['table']}' or exclude it from migration";
                    break;
                case 'record_exists':
                    $suggestions[] = "Use --force to overwrite existing record {$conflict['table']}:{$conflict['uid']}";
                    break;
                case 'table_modified':
                    $suggestions[] = "Review changes in table '{$conflict['table']}' and resolve manually";
                    break;
                default:
                    $suggestions[] = "Review conflict of type '{$conflict['type']}' manually";
            }
        }

        return array_unique($suggestions);
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
     * Get record count for a table
     */
    private function getTableRecordCount(string $tableName): int
    {
        try {
            $connection = $this->connectionPool->getConnectionForTable($tableName);
            $queryBuilder = $connection->createQueryBuilder();
            
            return (int)$queryBuilder
                ->count('*')
                ->from($tableName)
                ->executeQuery()
                ->fetchOne();

        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Check available disk space
     */
    private function checkDiskSpace(): array
    {
        try {
            $freeBytes = disk_free_space('/');
            $requiredBytes = 100 * 1024 * 1024; // 100MB minimum

            return [
                'sufficient' => $freeBytes > $requiredBytes,
                'message' => $freeBytes > $requiredBytes 
                    ? 'Sufficient disk space available' 
                    : 'Low disk space - less than 100MB available',
                'free_space' => $freeBytes,
                'required_space' => $requiredBytes,
            ];

        } catch (\Exception $e) {
            return [
                'sufficient' => true, // Assume sufficient if check fails
                'message' => 'Could not check disk space: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check database constraints that might affect import
     */
    private function checkDatabaseConstraints(array $exportData): array
    {
        $issues = [];

        try {
            // Check for foreign key constraints
            // This is a simplified check - production version would be more comprehensive
            if (isset($exportData['relations'])) {
                foreach ($exportData['relations'] as $fromKey => $relations) {
                    foreach ($relations as $relation) {
                        if (!$this->tableExists($relation['to_table'])) {
                            $issues[] = "Referenced table '{$relation['to_table']}' does not exist";
                        }
                    }
                }
            }

            return [
                'valid' => empty($issues),
                'message' => empty($issues) ? 'Database constraints validated' : 'Constraint issues found',
                'issues' => $issues,
            ];

        } catch (\Exception $e) {
            return [
                'valid' => false,
                'message' => 'Constraint validation failed: ' . $e->getMessage(),
                'issues' => ['Constraint validation failed'],
            ];
        }
    }

    /**
     * Check dependency integrity in relations
     */
    private function checkDependencyIntegrity(array $relations): array
    {
        $warnings = [];

        try {
            foreach ($relations as $fromKey => $relationList) {
                foreach ($relationList as $relation) {
                    // Check if referenced table exists
                    if (!$this->tableExists($relation['to_table'])) {
                        $warnings[] = "Relation references non-existent table: {$relation['to_table']}";
                    }
                }
            }

            return [
                'valid' => empty($warnings),
                'message' => empty($warnings) ? 'Dependency integrity validated' : 'Dependency issues found',
                'warnings' => $warnings,
            ];

        } catch (\Exception $e) {
            return [
                'valid' => false,
                'message' => 'Dependency check failed: ' . $e->getMessage(),
                'warnings' => ['Dependency validation failed'],
            ];
        }
    }
}