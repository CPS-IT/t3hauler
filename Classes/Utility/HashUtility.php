<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Utility;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Utility for generating hashes of database table content
 *
 * Used for change detection and integrity validation
 */
class HashUtility
{
    public function __construct(
        private readonly ConnectionPool $connectionPool
    ) {}

    /**
     * Calculate hash for a table's content
     */
    public function calculateTableHash(
        string $tableName,
        array $excludeFields = [],
        array $whereConditions = [],
        string $algorithm = 'sha256'
    ): string {
        $connection = $this->connectionPool->getConnectionForTable($tableName);

        $queryBuilder = $connection->createQueryBuilder();
        $queryBuilder->select('*')
            ->from($tableName);

        // Apply where conditions
        foreach ($whereConditions as $field => $value) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->eq($field, $queryBuilder->createNamedParameter($value))
            );
        }

        // Order by primary key for consistent results
        $primaryKey = $this->getPrimaryKeyColumn($connection, $tableName);
        if ($primaryKey !== null) {
            $queryBuilder->orderBy($primaryKey);
        }

        $result = $queryBuilder->executeQuery();
        $rows = $result->fetchAllAssociative();

        return $this->calculateRowsHash($rows, $excludeFields, $algorithm);
    }

    /**
     * Calculate hash for multiple tables
     */
    public function calculateMultiTableHash(
        array $tableConfigs,
        string $algorithm = 'sha256'
    ): string {
        $allHashes = [];

        foreach ($tableConfigs as $tableName => $config) {
            $excludeFields = $config['excludeFields'] ?? [];
            $whereConditions = $config['whereConditions'] ?? [];

            $tableHash = $this->calculateTableHash(
                $tableName,
                $excludeFields,
                $whereConditions,
                $algorithm
            );

            $allHashes[] = $tableName . ':' . $tableHash;
        }

        return hash($algorithm, implode('|', $allHashes));
    }

    /**
     * Calculate hash for array of database rows
     */
    public function calculateRowsHash(
        array $rows,
        array $excludeFields = [],
        string $algorithm = 'sha256'
    ): string {
        $normalizedRows = [];

        foreach ($rows as $row) {
            // Remove excluded fields
            foreach ($excludeFields as $excludeField) {
                unset($row[$excludeField]);
            }

            // Sort keys for consistent ordering
            ksort($row);
            $normalizedRows[] = $row;
        }

        // Sort rows by their serialized content for consistent ordering
        usort($normalizedRows, function ($a, $b) {
            return serialize($a) <=> serialize($b);
        });

        return hash($algorithm, serialize($normalizedRows));
    }

    /**
     * Compare two hashes
     */
    public function compareHashes(string $hash1, string $hash2): bool
    {
        return hash_equals($hash1, $hash2);
    }

    /**
     * Generate hash for specific record
     */
    public function calculateRecordHash(
        string $tableName,
        int $uid,
        array $excludeFields = [],
        string $algorithm = 'sha256'
    ): ?string {
        $connection = $this->connectionPool->getConnectionForTable($tableName);

        $queryBuilder = $connection->createQueryBuilder();
        $row = $queryBuilder->select('*')
            ->from($tableName)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative();

        if ($row === false) {
            return null;
        }

        return $this->calculateRowsHash([$row], $excludeFields, $algorithm);
    }

    /**
     * Validate hash algorithm
     */
    public function isValidAlgorithm(string $algorithm): bool
    {
        return in_array($algorithm, hash_algos(), true);
    }

    /**
     * Get primary key column for a table
     */
    private function getPrimaryKeyColumn(Connection $connection, string $tableName): ?string
    {
        try {
            $schemaManager = $connection->createSchemaManager();
            $table = $schemaManager->introspectTable($tableName);
            $primaryKey = $table->getPrimaryKey();

            if ($primaryKey !== null) {
                $columns = $primaryKey->getColumns();
                return $columns[0] ?? null;
            }
        } catch (\Throwable $e) {
            // Fallback to 'uid' which is standard in TYPO3
            return 'uid';
        }

        return null;
    }
}
