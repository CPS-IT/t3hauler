<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Domain\Repository;

use Cpsit\T3hauler\Domain\Model\Migration;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Repository for Migration domain objects
 */
class MigrationRepository
{
    private const TABLE_NAME = 'tx_t3hauler_migrations';

    public function __construct(
        private readonly ConnectionPool $connectionPool
    ) {}

    /**
     * Find migration by migration ID
     */
    public function findByMigrationId(string $migrationId): ?Migration
    {
        $connection = $this->getConnection();
        $queryBuilder = $connection->createQueryBuilder();

        $row = $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->eq('migration_id', $queryBuilder->createNamedParameter($migrationId))
            )
            ->executeQuery()
            ->fetchAssociative();

        return $row ? Migration::fromArray($row) : null;
    }

    /**
     * Find migration by UID
     */
    public function findByUid(int $uid): ?Migration
    {
        $connection = $this->getConnection();
        $queryBuilder = $connection->createQueryBuilder();

        $row = $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchAssociative();

        return $row ? Migration::fromArray($row) : null;
    }

    /**
     * Find all migrations
     */
    public function findAll(string $orderBy = 'created_at', string $direction = 'DESC'): array
    {
        $connection = $this->getConnection();
        $queryBuilder = $connection->createQueryBuilder();

        $rows = $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME)
            ->orderBy($orderBy, $direction)
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(fn($row) => Migration::fromArray($row), $rows);
    }

    /**
     * Find migrations by status
     */
    public function findByStatus(string $status): array
    {
        $connection = $this->getConnection();
        $queryBuilder = $connection->createQueryBuilder();

        $rows = $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->eq('status', $queryBuilder->createNamedParameter($status))
            )
            ->orderBy('created_at', 'DESC')
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(fn($row) => Migration::fromArray($row), $rows);
    }

    /**
     * Find pending migrations
     */
    public function findPending(): array
    {
        return $this->findByStatus('pending');
    }

    /**
     * Find applied migrations
     */
    public function findApplied(): array
    {
        return $this->findByStatus('applied');
    }

    /**
     * Find failed migrations
     */
    public function findFailed(): array
    {
        return $this->findByStatus('failed');
    }

    /**
     * Find latest migration
     */
    public function findLatest(): ?Migration
    {
        $connection = $this->getConnection();
        $queryBuilder = $connection->createQueryBuilder();

        $row = $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME)
            ->orderBy('created_at', 'DESC')
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        return $row ? Migration::fromArray($row) : null;
    }

    /**
     * Save migration
     */
    public function save(Migration $migration): Migration
    {
        $connection = $this->getConnection();
        $data = $migration->toArray();

        if ($migration->getUid() === null) {
            // Insert new migration
            unset($data['uid']);
            $connection->insert(self::TABLE_NAME, $data);
            $migration->setUid((int)$connection->lastInsertId());
        } else {
            // Update existing migration
            $connection->update(
                self::TABLE_NAME,
                $data,
                ['uid' => $migration->getUid()]
            );
        }

        return $migration;
    }

    /**
     * Delete migration
     */
    public function delete(Migration $migration): bool
    {
        if ($migration->getUid() === null) {
            return false;
        }

        $connection = $this->getConnection();
        $affectedRows = $connection->delete(
            self::TABLE_NAME,
            ['uid' => $migration->getUid()]
        );

        return $affectedRows > 0;
    }

    /**
     * Delete migration by migration ID
     */
    public function deleteByMigrationId(string $migrationId): bool
    {
        $connection = $this->getConnection();
        $affectedRows = $connection->delete(
            self::TABLE_NAME,
            ['migration_id' => $migrationId]
        );

        return $affectedRows > 0;
    }

    /**
     * Check if migration exists
     */
    public function exists(string $migrationId): bool
    {
        $connection = $this->getConnection();
        $queryBuilder = $connection->createQueryBuilder();

        $count = $queryBuilder
            ->count('uid')
            ->from(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->eq('migration_id', $queryBuilder->createNamedParameter($migrationId))
            )
            ->executeQuery()
            ->fetchOne();

        return $count > 0;
    }

    /**
     * Count migrations by status
     */
    public function countByStatus(string $status): int
    {
        $connection = $this->getConnection();
        $queryBuilder = $connection->createQueryBuilder();

        return (int)$queryBuilder
            ->count('uid')
            ->from(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->eq('status', $queryBuilder->createNamedParameter($status))
            )
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * Get migrations summary
     */
    public function getSummary(): array
    {
        return [
            'total' => $this->count(),
            'pending' => $this->countByStatus('pending'),
            'applied' => $this->countByStatus('applied'),
            'failed' => $this->countByStatus('failed'),
            'rolled_back' => $this->countByStatus('rolled_back'),
        ];
    }

    /**
     * Count all migrations
     */
    public function count(): int
    {
        $connection = $this->getConnection();
        $queryBuilder = $connection->createQueryBuilder();

        return (int)$queryBuilder
            ->count('uid')
            ->from(self::TABLE_NAME)
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * Delete old migrations
     */
    public function deleteOlderThan(\DateTimeInterface $date): int
    {
        $connection = $this->getConnection();
        $queryBuilder = $connection->createQueryBuilder();

        return $queryBuilder
            ->delete(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->lt(
                    'created_at',
                    $queryBuilder->createNamedParameter($date->getTimestamp(), Connection::PARAM_INT)
                )
            )
            ->executeStatement();
    }

    /**
     * Get database connection
     */
    private function getConnection(): Connection
    {
        return $this->connectionPool->getConnectionForTable(self::TABLE_NAME);
    }
}
