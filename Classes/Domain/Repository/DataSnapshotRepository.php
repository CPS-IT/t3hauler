<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Domain\Repository;

use Cpsit\T3hauler\Domain\Model\DataSnapshot;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Repository for DataSnapshot domain objects
 */
class DataSnapshotRepository
{
    private const TABLE_NAME = 'tx_t3hauler_snapshots';

    public function __construct(
        private readonly ConnectionPool $connectionPool
    ) {
    }

    /**
     * Find snapshot by identifier
     */
    public function findByIdentifier(string $identifier): ?DataSnapshot
    {
        $connection = $this->getConnection();
        $queryBuilder = $connection->createQueryBuilder();

        $row = $queryBuilder->select('*')
            ->from(self::TABLE_NAME)
            ->where($queryBuilder->expr()->eq('identifier', $queryBuilder->createNamedParameter($identifier)))
            ->executeQuery()
            ->fetchAssociative();

        return $row ? DataSnapshot::fromArray($row) : null;
    }

    /**
     * Find latest snapshot for a table
     */
    public function findLatestByTableName(string $tableName): ?DataSnapshot
    {
        $connection = $this->getConnection();
        $queryBuilder = $connection->createQueryBuilder();

        $row = $queryBuilder->select('*')
            ->from(self::TABLE_NAME)
            ->where($queryBuilder->expr()->eq('table_name', $queryBuilder->createNamedParameter($tableName)))
            ->orderBy('created_at', 'DESC')
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        return $row ? DataSnapshot::fromArray($row) : null;
    }

    /**
     * Find all snapshots for a table
     */
    public function findByTableName(string $tableName): array
    {
        $connection = $this->getConnection();
        $queryBuilder = $connection->createQueryBuilder();

        $rows = $queryBuilder->select('*')
            ->from(self::TABLE_NAME)
            ->where($queryBuilder->expr()->eq('table_name', $queryBuilder->createNamedParameter($tableName)))
            ->orderBy('created_at', 'DESC')
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map([DataSnapshot::class, 'fromArray'], $rows);
    }

    /**
     * Find snapshots by migration version
     */
    public function findByMigrationVersion(string $migrationVersion): array
    {
        $connection = $this->getConnection();
        $queryBuilder = $connection->createQueryBuilder();

        $rows = $queryBuilder->select('*')
            ->from(self::TABLE_NAME)
            ->where($queryBuilder->expr()->eq('migration_version', $queryBuilder->createNamedParameter($migrationVersion)))
            ->orderBy('created_at', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map([DataSnapshot::class, 'fromArray'], $rows);
    }

    /**
     * Find all snapshots
     */
    public function findAll(): array
    {
        $connection = $this->getConnection();
        $queryBuilder = $connection->createQueryBuilder();

        $rows = $queryBuilder->select('*')
            ->from(self::TABLE_NAME)
            ->orderBy('created_at', 'DESC')
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map([DataSnapshot::class, 'fromArray'], $rows);
    }

    /**
     * Save snapshot
     */
    public function save(DataSnapshot $snapshot): DataSnapshot
    {
        $connection = $this->getConnection();
        $data = $snapshot->toArray();

        if ($snapshot->getUid() === null) {
            // Insert new record
            unset($data['uid']);
            $connection->insert(self::TABLE_NAME, $data);
            $snapshot->setUid((int)$connection->lastInsertId());
        } else {
            // Update existing record
            $uid = $data['uid'];
            unset($data['uid']);
            $connection->update(
                self::TABLE_NAME,
                $data,
                ['uid' => $uid],
                ['uid' => Connection::PARAM_INT]
            );
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

        $connection = $this->getConnection();
        $connection->delete(
            self::TABLE_NAME,
            ['uid' => $snapshot->getUid()],
            ['uid' => Connection::PARAM_INT]
        );
    }

    /**
     * Delete snapshots older than specified timestamp
     */
    public function deleteOlderThan(\DateTimeInterface $cutoffDate): int
    {
        $connection = $this->getConnection();
        $queryBuilder = $connection->createQueryBuilder();

        return $queryBuilder->delete(self::TABLE_NAME)
            ->where($queryBuilder->expr()->lt('created_at', $queryBuilder->createNamedParameter($cutoffDate->getTimestamp(), Connection::PARAM_INT)))
            ->executeStatement();
    }

    /**
     * Delete snapshots by table name
     */
    public function deleteByTableName(string $tableName): int
    {
        $connection = $this->getConnection();
        $queryBuilder = $connection->createQueryBuilder();

        return $queryBuilder->delete(self::TABLE_NAME)
            ->where($queryBuilder->expr()->eq('table_name', $queryBuilder->createNamedParameter($tableName)))
            ->executeStatement();
    }

    /**
     * Check if snapshot exists with identifier
     */
    public function existsByIdentifier(string $identifier): bool
    {
        $connection = $this->getConnection();
        $queryBuilder = $connection->createQueryBuilder();

        $count = $queryBuilder->count('uid')
            ->from(self::TABLE_NAME)
            ->where($queryBuilder->expr()->eq('identifier', $queryBuilder->createNamedParameter($identifier)))
            ->executeQuery()
            ->fetchOne();

        return $count > 0;
    }

    /**
     * Get count of snapshots for table
     */
    public function countByTableName(string $tableName): int
    {
        $connection = $this->getConnection();
        $queryBuilder = $connection->createQueryBuilder();

        return $queryBuilder->count('uid')
            ->from(self::TABLE_NAME)
            ->where($queryBuilder->expr()->eq('table_name', $queryBuilder->createNamedParameter($tableName)))
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * Get database connection
     */
    private function getConnection(): Connection
    {
        return $this->connectionPool->getConnectionForTable(self::TABLE_NAME);
    }
}