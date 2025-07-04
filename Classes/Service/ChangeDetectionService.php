<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Service;

use Cpsit\T3hauler\Configuration\T3HaulerConfiguration;
use Cpsit\T3hauler\Domain\Model\DataSnapshot;
use Cpsit\T3hauler\Domain\Repository\DataSnapshotRepository;
use Cpsit\T3hauler\Utility\HashUtility;

/**
 * Service for detecting changes in database tables
 *
 * Compares current table state with stored snapshots to identify changes
 */
class ChangeDetectionService
{
    public function __construct(
        private readonly HashUtility $hashUtility,
        private readonly DataSnapshotRepository $snapshotRepository,
        private readonly T3HaulerConfiguration $configuration
    ) {}

    /**
     * Detect changes for all configured tables
     */
    public function detectChanges(?string $baselineIdentifier = null): array
    {
        $enabledTables = $this->configuration->getEnabledTables();
        $excludedFields = $this->configuration->getExcludedFields();

        $changes = [];

        foreach ($enabledTables as $tableName) {
            $tableChanges = $this->detectTableChanges($tableName, $excludedFields, $baselineIdentifier);
            if (!empty($tableChanges)) {
                $changes[$tableName] = $tableChanges;
            }
        }

        return $changes;
    }

    /**
     * Detect changes for a specific table
     */
    public function detectTableChanges(
        string $tableName,
        array $excludedFields = [],
        ?string $baselineIdentifier = null
    ): array {
        // Get current hash
        $currentHash = $this->hashUtility->calculateTableHash(
            $tableName,
            $excludedFields,
            [],
            $this->configuration->getHashAlgorithm()
        );

        // Get baseline snapshot
        $baselineSnapshot = $baselineIdentifier
            ? $this->snapshotRepository->findByIdentifier($baselineIdentifier)
            : $this->snapshotRepository->findLatestByTableName($tableName);

        if ($baselineSnapshot === null) {
            return [
                'status' => 'no_baseline',
                'message' => 'No baseline snapshot found for table ' . $tableName,
                'current_hash' => $currentHash,
                'baseline_hash' => null,
                'changed' => true,
            ];
        }

        $hasChanges = !$baselineSnapshot->matchesHash($currentHash);

        return [
            'status' => $hasChanges ? 'changed' : 'unchanged',
            'message' => $hasChanges
                ? 'Changes detected in table ' . $tableName
                : 'No changes detected in table ' . $tableName,
            'current_hash' => $currentHash,
            'baseline_hash' => $baselineSnapshot->getHash(),
            'baseline_created_at' => $baselineSnapshot->getCreatedAt(),
            'changed' => $hasChanges,
        ];
    }

    /**
     * Create snapshot for current state of all configured tables
     */
    public function createSnapshot(?string $identifier = null, ?string $migrationVersion = null): array
    {
        $enabledTables = $this->configuration->getEnabledTables();
        $excludedFields = $this->configuration->getExcludedFields();
        $snapshots = [];

        $hashes = [];
        foreach ($enabledTables as $tableName) {
            $snapshot = $this->createTableSnapshot($tableName, $excludedFields, $migrationVersion);
            $snapshots[] = $snapshot;
            $hashes[] = $snapshot->getHash();
        }

        $setHash = hash($this->configuration->getHashAlgorithm(), implode('|', $hashes));
        $snapshotSet = new DataSnapshot(
            $identifier,
            DataSnapshotRepository::TABLE_NAME,
            $setHash);
        $this->snapshotRepository->save($snapshotSet);
        return $snapshots;
    }

    /**
     * Create a snapshot for a specific table
     */
    public function createTableSnapshot(
        string $tableName,
        array $excludedFields = [],
        ?string $migrationVersion = null
    ): DataSnapshot {
        $identifier = DataSnapshot::generateIdentifier($tableName);

        // Calculate current hash
        $currentHash = $this->hashUtility->calculateTableHash(
            $tableName,
            $excludedFields,
            [],
            $this->configuration->getHashAlgorithm()
        );

        // Create snapshot
        $snapshot = new DataSnapshot($identifier, $tableName, $currentHash);

        if ($migrationVersion !== null) {
            $snapshot->setMigrationVersion($migrationVersion);
        }

        // Add metadata
        $snapshot->addMetadata('excluded_fields', $excludedFields);
        $snapshot->addMetadata('hash_algorithm', $this->configuration->getHashAlgorithm());

        // Save snapshot
        return $this->snapshotRepository->save($snapshot);
    }

    /**
     * Check if there are any changes since last snapshot
     */
    public function hasChanges(?string $baselineIdentifier = null): bool
    {
        $changes = $this->detectChanges($baselineIdentifier);

        foreach ($changes as $tableChanges) {
            if ($tableChanges['changed'] === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get a summary of changes
     */
    public function getChangesSummary(?string $baselineIdentifier = null): array
    {
        $changes = $this->detectChanges($baselineIdentifier);
        $changedTables = [];
        $unchangedTables = [];
        $noBaselineTables = [];

        foreach ($changes as $tableName => $tableChanges) {
            switch ($tableChanges['status']) {
                case 'changed':
                    $changedTables[] = $tableName;
                    break;
                case 'unchanged':
                    $unchangedTables[] = $tableName;
                    break;
                case 'no_baseline':
                    $noBaselineTables[] = $tableName;
                    break;
            }
        }

        return [
            'total_tables' => count($changes),
            'changed_tables' => $changedTables,
            'unchanged_tables' => $unchangedTables,
            'no_baseline_tables' => $noBaselineTables,
            'has_changes' => !empty($changedTables) || !empty($noBaselineTables),
        ];
    }

    /**
     * Compare two snapshots
     */
    public function compareSnapshots(DataSnapshot $snapshot1, DataSnapshot $snapshot2): array
    {
        if ($snapshot1->getTableName() !== $snapshot2->getTableName()) {
            throw new \InvalidArgumentException('Cannot compare snapshots of different tables', 1909055643);
        }

        $hasChanges = !$snapshot1->matchesHash($snapshot2->getHash());

        return [
            'table_name' => $snapshot1->getTableName(),
            'snapshot1_identifier' => $snapshot1->getIdentifier(),
            'snapshot2_identifier' => $snapshot2->getIdentifier(),
            'snapshot1_hash' => $snapshot1->getHash(),
            'snapshot2_hash' => $snapshot2->getHash(),
            'snapshot1_created_at' => $snapshot1->getCreatedAt(),
            'snapshot2_created_at' => $snapshot2->getCreatedAt(),
            'changed' => $hasChanges,
            'time_difference' => $snapshot2->getCreatedAt()->getTimestamp() - $snapshot1->getCreatedAt()->getTimestamp(),
        ];
    }

    /**
     * Clean up old snapshots
     */
    public function cleanupOldSnapshots(int $keepDays = 30): int
    {
        $cutoffDate = new \DateTimeImmutable('-' . $keepDays . ' days');
        return $this->snapshotRepository->deleteOlderThan($cutoffDate);
    }
}
