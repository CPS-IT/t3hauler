<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Domain\Repository;

use Cpsit\T3hauler\Domain\Enumeration\RecordChangeType;
use Cpsit\T3hauler\Domain\Model\ChangeRecord;
use TYPO3\CMS\Core\SingletonInterface;

/**
 * Repository for ChangeRecord domain model
 */
class ChangeRecordRepository extends AbstractRepository implements SingletonInterface
{
    protected const string TABLE_NAME = 'tx_t3hauler_change_records';

    /**
     * Add a change record
     */
    public function add(ChangeRecord $changeRecord): void
    {
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

        $uid = $this->insertRecord($data);
        $changeRecord->setUid($uid);
    }

    /**
     * Find all change records for a snapshot
     */
    public function findBySnapshotUid(int $snapshotUid): array
    {
        $rows = $this->findByConditions(
            ['snapshot_uid' => $snapshotUid],
            ['detected_at' => 'ASC', 'uid' => 'ASC']
        );
        return $this->mapRowsToObjects($rows, [$this, 'mapRowToChangeRecord']);
    }

    /**
     * Find changes for a specific table and record
     */
    public function findByTableAndRecord(string $tableName, int $recordUid, int $snapshotUid): array
    {
        $rows = $this->findByConditions(
            [
                'snapshot_uid' => $snapshotUid,
                'table_name' => $tableName,
                'record_uid' => $recordUid,
            ],
            ['detected_at' => 'ASC']
        );
        return $this->mapRowsToObjects($rows, [$this, 'mapRowToChangeRecord']);
    }

    /**
     * Find changes by table name
     */
    public function findByTableName(string $tableName, int $snapshotUid): array
    {
        $rows = $this->findByConditions(
            [
                'snapshot_uid' => $snapshotUid,
                'table_name' => $tableName,
            ],
            ['detected_at' => 'ASC']
        );
        return $this->mapRowsToObjects($rows, [$this, 'mapRowToChangeRecord']);
    }

    /**
     * Find changes by change type
     */
    public function findByChangeType(string $changeType, int $snapshotUid): array
    {
        $rows = $this->findByConditions(
            [
                'snapshot_uid' => $snapshotUid,
                'change_type' => $changeType,
            ],
            ['detected_at' => 'ASC']
        );
        return $this->mapRowsToObjects($rows, [$this, 'mapRowToChangeRecord']);
    }

    /**
     * Find changes by correlation ID
     */
    public function findByCorrelationId(string $correlationId): array
    {
        $rows = $this->findByConditions(
            ['correlation_id' => $correlationId],
            ['detected_at' => 'ASC']
        );
        return $this->mapRowsToObjects($rows, [$this, 'mapRowToChangeRecord']);
    }

    /**
     * Get statistics for a snapshot
     */
    public function getStatistics(int $snapshotUid): array
    {
        $queryBuilder = $this->getQueryBuilder();

        $result = $queryBuilder
            ->select('change_type')
            ->addSelectLiteral('COUNT(*) as count')
            ->from(static::TABLE_NAME)
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
        $this->deleteByConditions(['snapshot_uid' => $snapshotUid]);
    }

    /**
     * Delete old change records (older than specified days)
     */
    public function deleteOldRecords(int $days): void
    {
        $threshold = new \DateTimeImmutable('@' . (time() - ($days * 24 * 60 * 60)));
        parent::deleteOlderThan('detected_at', $threshold);
    }

    /**
     * Count total records for a snapshot
     */
    public function countBySnapshotUid(int $snapshotUid): int
    {
        return $this->countByConditions(['snapshot_uid' => $snapshotUid]);
    }

    /**
     * Map database row to ChangeRecord object
     */
    protected function mapRowToChangeRecord(array $row): ChangeRecord
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
