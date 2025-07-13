<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Service;

use Cpsit\T3hauler\Configuration\T3HaulerConfiguration;
use Cpsit\T3hauler\Domain\Enumeration\RecordChangeType;
use Cpsit\T3hauler\Domain\Model\ChangeRecord;
use Cpsit\T3hauler\Domain\Repository\ChangeRecordRepository;
use Cpsit\T3hauler\Utility\HashUtility;
use TYPO3\CMS\Core\SingletonInterface;

/**
 * Service for tracking individual record changes
 */
class ChangeTrackingService implements SingletonInterface
{
    private ChangeRecordRepository $changeRecordRepository;
    private T3HaulerConfiguration $configuration;

    public function __construct(
        ChangeRecordRepository $changeRecordRepository,
        T3HaulerConfiguration $configuration
    ) {
        $this->changeRecordRepository = $changeRecordRepository;
        $this->configuration = $configuration;
    }

    /**
     * Track a record change
     */
    public function trackChange(array $changeData): void
    {
        $tableName = $changeData['table_name'];
        $recordUid = $changeData['record_uid'];
        $changeTypeString = $changeData['change_type'];
        $changeType = RecordChangeType::from($changeTypeString);
        $newData = $changeData['new_data'] ?? [];
        $previousData = $changeData['previous_data'] ?? [];

        // Calculate field changes
        $fieldChanges = $this->calculateFieldChanges($tableName, $newData, $previousData);

        // Skip if no significant changes detected
        if (empty($fieldChanges) && $changeType === RecordChangeType::UPDATE) {
            return;
        }

        // Generate hashes
        $recordHash = $this->generateRecordHash($tableName, $recordUid, $newData);
        $previousHash = $previousData ? $this->generateRecordHash($tableName, $recordUid, $previousData) : null;

        // Create change record
        $changeRecord = new ChangeRecord();
        $changeRecord->setSnapshotUid($changeData['snapshot_uid']);
        $changeRecord->setTableName($tableName);
        $changeRecord->setRecordUid($recordUid);
        $changeRecord->setRecordPid($newData['pid'] ?? 0);
        $changeRecord->setChangeType($changeType);
        $changeRecord->setFieldChanges(json_encode($fieldChanges, JSON_THROW_ON_ERROR));
        $changeRecord->setRecordHash($recordHash);
        $changeRecord->setPreviousHash($previousHash);
        $changeRecord->setDetectedAt(time());
        $changeRecord->setBeUser($changeData['be_user'] ?? 0);
        $changeRecord->setWorkspace($changeData['workspace'] ?? 0);
        $changeRecord->setLanguageUid($newData['sys_language_uid'] ?? 0);
        $changeRecord->setCorrelationId($changeData['correlation_id'] ?? '');

        // Persist change record
        $this->changeRecordRepository->add($changeRecord);
    }

    /**
     * Get all changes for a snapshot
     */
    public function getChangesForSnapshot(int $snapshotUid): array
    {
        return $this->changeRecordRepository->findBySnapshotUid($snapshotUid);
    }

    /**
     * Get changes grouped by table
     */
    public function getChangesGroupedByTable(int $snapshotUid): array
    {
        $changes = $this->getChangesForSnapshot($snapshotUid);
        $grouped = [];

        foreach ($changes as $change) {
            $tableName = $change->getTableName();
            if (!isset($grouped[$tableName])) {
                $grouped[$tableName] = [];
            }
            $grouped[$tableName][] = $change;
        }

        return $grouped;
    }

    /**
     * Get changes for specific table and record
     */
    public function getChangesForRecord(string $tableName, int $recordUid, int $snapshotUid): array
    {
        return $this->changeRecordRepository->findByTableAndRecord($tableName, $recordUid, $snapshotUid);
    }

    /**
     * Clear all changes for a snapshot
     */
    public function clearChangesForSnapshot(int $snapshotUid): void
    {
        $this->changeRecordRepository->deleteBySnapshotUid($snapshotUid);
    }

    /**
     * Calculate field changes between old and new data
     */
    private function calculateFieldChanges(string $tableName, array $newData, array $previousData): array
    {
        $excludeFields = $this->configuration->get('detection.excludeFields', []);
        $changes = [];

        // Add table-specific exclude fields
        $tableExcludeFields = $this->configuration->get("detection.tables.{$tableName}.excludeFields", []);
        $excludeFields = array_merge($excludeFields, $tableExcludeFields);

        // Compare fields
        $allFields = array_unique(array_merge(array_keys($newData), array_keys($previousData)));

        foreach ($allFields as $fieldName) {
            if (in_array($fieldName, $excludeFields, true)) {
                continue;
            }

            $oldValue = $previousData[$fieldName] ?? null;
            $newValue = $newData[$fieldName] ?? null;

            // Skip if values are identical
            if ($oldValue === $newValue) {
                continue;
            }

            // Handle special cases for TYPO3 fields
            if ($this->isSignificantChange($fieldName, $oldValue, $newValue)) {
                $changes[$fieldName] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        return $changes;
    }

    /**
     * Check if a field change is significant
     */
    private function isSignificantChange(string $fieldName, $oldValue, $newValue): bool
    {
        // Handle timestamps - only consider significant if difference > 1 second
        if (in_array($fieldName, ['tstamp', 'crdate'], true)) {
            return abs((int)$oldValue - (int)$newValue) > 1;
        }

        // Handle deleted field
        if ($fieldName === 'deleted') {
            return (bool)$oldValue !== (bool)$newValue;
        }

        // Handle hidden field
        if ($fieldName === 'hidden') {
            return (bool)$oldValue !== (bool)$newValue;
        }

        // Default comparison
        return $oldValue !== $newValue;
    }

    /**
     * Generate hash for a record
     */
    private function generateRecordHash(string $tableName, int $recordUid, array $data): string
    {
        // Remove excluded fields before hashing
        $excludeFields = $this->configuration->get('detection.excludeFields', []);
        $tableExcludeFields = $this->configuration->get("detection.tables.{$tableName}.excludeFields", []);
        $allExcludeFields = array_merge($excludeFields, $tableExcludeFields);

        $filteredData = array_filter(
            $data,
            static fn($key) => !in_array($key, $allExcludeFields, true),
            ARRAY_FILTER_USE_KEY
        );

        // Sort for consistent hashing
        ksort($filteredData);

        return HashUtility::hashArray($filteredData);
    }
}
