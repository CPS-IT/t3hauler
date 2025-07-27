<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Domain\Repository;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

/**
 * Abstract base repository providing common database operations
 */
abstract class AbstractRepository
{
    protected const string TABLE_NAME = '';

    public function __construct(
        protected readonly ConnectionPool $connectionPool
    ) {}

    /**
     * Get database connection for the repository's table
     */
    protected function getConnection(): Connection
    {
        return $this->connectionPool->getConnectionForTable(static::TABLE_NAME);
    }

    /**
     * Get query builder for the repository's table
     */
    protected function getQueryBuilder(): QueryBuilder
    {
        return $this->connectionPool->getQueryBuilderForTable(static::TABLE_NAME);
    }

    /**
     * Find records by conditions with optional ordering
     */
    protected function findByConditions(
        array $conditions = [],
        array $orderBy = [],
        ?int $limit = null
    ): array {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->select('*')->from(static::TABLE_NAME);

        foreach ($conditions as $field => $value) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->eq($field, $queryBuilder->createNamedParameter($value))
            );
        }

        $orderByAdded = false;
        foreach ($orderBy as $field => $direction) {
            if ($orderByAdded) {
                $queryBuilder->addOrderBy($field, $direction);
            } else {
                $queryBuilder->orderBy($field, $direction);
                $orderByAdded = true;
            }
        }

        if ($limit !== null) {
            $queryBuilder->setMaxResults($limit);
        }

        return $queryBuilder->executeQuery()->fetchAllAssociative();
    }

    /**
     * Find single record by conditions
     */
    protected function findOneByConditions(array $conditions = []): ?array
    {
        $results = $this->findByConditions($conditions, [], 1);
        return $results[0] ?? null;
    }

    /**
     * Count records by conditions
     */
    protected function countByConditions(array $conditions = []): int
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->count('uid')->from(static::TABLE_NAME);

        foreach ($conditions as $field => $value) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->eq($field, $queryBuilder->createNamedParameter($value))
            );
        }

        return (int)$queryBuilder->executeQuery()->fetchOne();
    }

    /**
     * Check if record exists by conditions
     */
    protected function existsByConditions(array $conditions = []): bool
    {
        return $this->countByConditions($conditions) > 0;
    }

    /**
     * Delete records by conditions
     */
    protected function deleteByConditions(array $conditions = []): int
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->delete(static::TABLE_NAME);

        foreach ($conditions as $field => $value) {
            if ($value instanceof \DateTimeInterface) {
                $queryBuilder->andWhere(
                    $queryBuilder->expr()->eq($field, $queryBuilder->createNamedParameter($value->getTimestamp(), Connection::PARAM_INT))
                );
            } else {
                $queryBuilder->andWhere(
                    $queryBuilder->expr()->eq($field, $queryBuilder->createNamedParameter($value))
                );
            }
        }

        return $queryBuilder->executeStatement();
    }

    /**
     * Delete records older than specified date
     */
    protected function deleteOlderThan(string $dateField, \DateTimeInterface $cutoffDate): int
    {
        $queryBuilder = $this->getQueryBuilder();

        return $queryBuilder->delete(static::TABLE_NAME)
            ->where($queryBuilder->expr()->lt(
                $dateField,
                $queryBuilder->createNamedParameter($cutoffDate->getTimestamp(), Connection::PARAM_INT)
            ))
            ->executeStatement();
    }

    /**
     * Insert record data
     */
    protected function insertRecord(array $data): int
    {
        $connection = $this->getConnection();
        unset($data['uid']);
        $connection->insert(static::TABLE_NAME, $data);
        return (int)$connection->lastInsertId();
    }

    /**
     * Update record data by UID
     */
    protected function updateRecord(int $uid, array $data): bool
    {
        $connection = $this->getConnection();
        unset($data['uid']);
        $affectedRows = $connection->update(
            static::TABLE_NAME,
            $data,
            ['uid' => $uid],
            ['uid' => Connection::PARAM_INT]
        );
        return $affectedRows > 0;
    }

    /**
     * Delete record by UID
     */
    protected function deleteRecord(int $uid): bool
    {
        $connection = $this->getConnection();
        $affectedRows = $connection->delete(
            static::TABLE_NAME,
            ['uid' => $uid],
            ['uid' => Connection::PARAM_INT]
        );
        return $affectedRows > 0;
    }

    /**
     * Map array of rows to objects using a callback
     */
    protected function mapRowsToObjects(array $rows, callable $mapper): array
    {
        return array_map($mapper, $rows);
    }
}
