<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Domain\Repository;

use Cpsit\T3hauler\Domain\Model\DataSnapshot;

/**
 * Repository for DataSnapshot domain objects
 */
class DataSnapshotRepository extends AbstractRepository
{
    public const string TABLE_NAME = 'tx_t3hauler_snapshots';

    /**
     * Find snapshot by identifier
     */
    public function findByIdentifier(string $identifier): ?DataSnapshot
    {
        $row = $this->findOneByConditions(['identifier' => $identifier]);
        return $row ? DataSnapshot::fromArray($row) : null;
    }

    /**
     * Find latest snapshot for a table
     */
    public function findLatestByTableName(string $tableName): ?DataSnapshot
    {
        $rows = $this->findByConditions(
            ['table_name' => $tableName],
            ['created_at' => 'DESC'],
            1
        );
        return $rows ? DataSnapshot::fromArray($rows[0]) : null;
    }

    /**
     * Find all snapshots for a table
     */
    public function findByTableName(string $tableName): array
    {
        $rows = $this->findByConditions(
            ['table_name' => $tableName],
            ['created_at' => 'DESC']
        );
        return $this->mapRowsToObjects($rows, [DataSnapshot::class, 'fromArray']);
    }

    /**
     * Find snapshots by migration version
     * @param string $migrationVersion
     * @return DataSnapshot[]
     * @throws \Doctrine\DBAL\Exception
     */
    public function findByMigrationVersion(string $migrationVersion): array
    {
        $rows = $this->findByConditions(
            ['migration_version' => $migrationVersion],
            ['created_at' => 'ASC']
        );
        return $this->mapRowsToObjects($rows, [DataSnapshot::class, 'fromArray']);
    }

    /**
     * Find all snapshots
     */
    public function findAll(): array
    {
        $rows = $this->findByConditions([], ['created_at' => 'DESC']);
        return $this->mapRowsToObjects($rows, [DataSnapshot::class, 'fromArray']);
    }

    /**
     * Save snapshot
     */
    public function save(DataSnapshot $snapshot): DataSnapshot
    {
        $data = $snapshot->toArray();

        if ($snapshot->getUid() === null) {
            $uid = $this->insertRecord($data);
            $snapshot->setUid($uid);
        } else {
            $this->updateRecord($snapshot->getUid(), $data);
        }

        return $snapshot;
    }

    /**
     * Delete snapshot
     */
    public function delete(DataSnapshot $snapshot): void
    {
        if ($snapshot->getUid() === null) {
            return;
        }

        $this->deleteRecord($snapshot->getUid());
    }

    /**
     * Delete snapshots older than specified timestamp
     */
    public function deleteSnapshotsOlderThan(\DateTimeInterface $cutoffDate): int
    {
        return parent::deleteOlderThan('created_at', $cutoffDate);
    }

    /**
     * Delete snapshots by table name
     */
    public function deleteByTableName(string $tableName): int
    {
        return $this->deleteByConditions(['table_name' => $tableName]);
    }

    /**
     * Check if snapshot exists with identifier
     */
    public function existsByIdentifier(string $identifier): bool
    {
        return $this->existsByConditions(['identifier' => $identifier]);
    }

    /**
     * Get count of snapshots for table
     */
    public function countByTableName(string $tableName): int
    {
        return $this->countByConditions(['table_name' => $tableName]);
    }

    /**
     * Find the current snapshot for change tracking
     */
    public function findCurrentSnapshot(): ?DataSnapshot
    {
        $rows = $this->findByConditions(
            ['table_name' => self::TABLE_NAME],
            ['created_at' => 'DESC'],
            1
        );
        return $rows ? DataSnapshot::fromArray($rows[0]) : null;
    }

}
