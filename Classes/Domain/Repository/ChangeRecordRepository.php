<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Domain\Repository;

use Cpsit\T3hauler\Domain\Enumeration\RecordChangeType;
use Cpsit\T3hauler\Domain\Model\ChangeRecord;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\SingletonInterface;

/**
 * Repository for ChangeRecord domain model
 */
class ChangeRecordRepository implements SingletonInterface
{
    private const TABLE_NAME = 'tx_t3hauler_change_records';

    private ConnectionPool $connectionPool;

    public function __construct(ConnectionPool $connectionPool)
    {
        $this->connectionPool = $connectionPool;
    }

    /**
     * Add a change record
     */
    public function add(ChangeRecord $changeRecord): void
    {
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE_NAME);

        $data = [
            'snapshot_uid' => $changeRecord->getSnapshotUid(),
            'table_name' => $changeRecord->getTableName(),
            'record_uid' => $changeRecord->getRecordUid(),
            'record_pid' => $changeRecord->getRecordPid(),
            'change_type' => $changeRecord->getChangeType()->value,
            'field_changes' => $changeRecord->getFieldChanges(),
            'record_hash' => $changeRecord->getRecordHash(),
            'previous_hash' => $changeRecord->getPreviousHash(),
            'detected_at' => $changeRecord->getDetectedAt(),
            'be_user' => $changeRecord->getBeUser(),
            'workspace' => $changeRecord->getWorkspace(),
            'language_uid' => $changeRecord->getLanguageUid(),
            'correlation_id' => $changeRecord->getCorrelationId(),
        ];

        $connection->insert(self::TABLE_NAME, $data);

        // Set the UID from the inserted record
        $changeRecord->setUid((int)$connection->lastInsertId());
    }

    /**
     * Find all change records for a snapshot
     */
    public function findBySnapshotUid(int $snapshotUid): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME);

        $result = $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->eq('snapshot_uid', $queryBuilder->createNamedParameter($snapshotUid))
            )
            ->orderBy('detected_at', 'ASC')
            ->orderBy('uid', 'ASC')
            ->executeQuery();

        $changes = [];
        while ($row = $result->fetchAssociative()) {
            $changes[] = $this->mapRowToChangeRecord($row);
        }

        return $changes;
    }

    /**
     * Find changes for a specific table and record
     */
    public function findByTableAndRecord(string $tableName, int $recordUid, int $snapshotUid): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME);

        $result = $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->eq('snapshot_uid', $queryBuilder->createNamedParameter($snapshotUid)),
                $queryBuilder->expr()->eq('table_name', $queryBuilder->createNamedParameter($tableName)),
                $queryBuilder->expr()->eq('record_uid', $queryBuilder->createNamedParameter($recordUid))
            )
            ->orderBy('detected_at', 'ASC')
            ->executeQuery();

        $changes = [];
        while ($row = $result->fetchAssociative()) {
            $changes[] = $this->mapRowToChangeRecord($row);
        }

        return $changes;
    }

    /**
     * Find changes by table name
     */
    public function findByTableName(string $tableName, int $snapshotUid): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME);

        $result = $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->eq('snapshot_uid', $queryBuilder->createNamedParameter($snapshotUid)),
                $queryBuilder->expr()->eq('table_name', $queryBuilder->createNamedParameter($tableName))
            )
            ->orderBy('detected_at', 'ASC')
            ->executeQuery();

        $changes = [];
        while ($row = $result->fetchAssociative()) {
            $changes[] = $this->mapRowToChangeRecord($row);
        }

        return $changes;
    }

    /**
     * Find changes by change type
     */
    public function findByChangeType(string $changeType, int $snapshotUid): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME);

        $result = $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->eq('snapshot_uid', $queryBuilder->createNamedParameter($snapshotUid)),
                $queryBuilder->expr()->eq('change_type', $queryBuilder->createNamedParameter($changeType))
            )
            ->orderBy('detected_at', 'ASC')
            ->executeQuery();

        $changes = [];
        while ($row = $result->fetchAssociative()) {
            $changes[] = $this->mapRowToChangeRecord($row);
        }

        return $changes;
    }

    /**
     * Find changes by correlation ID
     */
    public function findByCorrelationId(string $correlationId): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME);

        $result = $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->eq('correlation_id', $queryBuilder->createNamedParameter($correlationId))
            )
            ->orderBy('detected_at', 'ASC')
            ->executeQuery();

        $changes = [];
        while ($row = $result->fetchAssociative()) {
            $changes[] = $this->mapRowToChangeRecord($row);
        }

        return $changes;
    }

    /**
     * Get statistics for a snapshot
     */
    public function getStatistics(int $snapshotUid): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME);

        $result = $queryBuilder
            ->select('change_type')
            ->addSelectLiteral('COUNT(*) as count')
            ->from(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->eq('snapshot_uid', $queryBuilder->createNamedParameter($snapshotUid))
            )
            ->groupBy('change_type')
            ->executeQuery();

        $statistics = [
            'insert' => 0,
            'update' => 0,
            'delete' => 0,
            'move' => 0,
            'total' => 0,
        ];

        while ($row = $result->fetchAssociative()) {
            $statistics[$row['change_type']] = (int)$row['count'];
            $statistics['total'] += (int)$row['count'];
        }

        return $statistics;
    }

    /**
     * Delete all change records for a snapshot
     */
    public function deleteBySnapshotUid(int $snapshotUid): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME);

        $queryBuilder
            ->delete(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->eq('snapshot_uid', $queryBuilder->createNamedParameter($snapshotUid))
            )
            ->executeStatement();
    }

    /**
     * Delete old change records (older than specified days)
     */
    public function deleteOldRecords(int $days): void
    {
        $threshold = time() - ($days * 24 * 60 * 60);

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME);

        $queryBuilder
            ->delete(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->lt('detected_at', $queryBuilder->createNamedParameter($threshold))
            )
            ->executeStatement();
    }

    /**
     * Count total records for a snapshot
     */
    public function countBySnapshotUid(int $snapshotUid): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME);

        return (int)$queryBuilder
            ->count('*')
            ->from(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->eq('snapshot_uid', $queryBuilder->createNamedParameter($snapshotUid))
            )
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * Map database row to ChangeRecord object
     */
    private function mapRowToChangeRecord(array $row): ChangeRecord
    {
        $changeRecord = new ChangeRecord();
        $changeRecord->setUid((int)$row['uid']);
        $changeRecord->setSnapshotUid((int)$row['snapshot_uid']);
        $changeRecord->setTableName($row['table_name']);
        $changeRecord->setRecordUid((int)$row['record_uid']);
        $changeRecord->setRecordPid((int)$row['record_pid']);
        $changeRecord->setChangeType(RecordChangeType::from($row['change_type']));
        $changeRecord->setFieldChanges($row['field_changes']);
        $changeRecord->setRecordHash($row['record_hash']);
        $changeRecord->setPreviousHash($row['previous_hash']);
        $changeRecord->setDetectedAt((int)$row['detected_at']);
        $changeRecord->setBeUser((int)$row['be_user']);
        $changeRecord->setWorkspace((int)$row['workspace']);
        $changeRecord->setLanguageUid((int)$row['language_uid']);
        $changeRecord->setCorrelationId($row['correlation_id']);

        return $changeRecord;
    }
}
