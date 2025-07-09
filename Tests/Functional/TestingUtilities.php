<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Tests\Functional;

use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\UserAspect;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Trait with testing utilities for T3Hauler functional tests
 */
trait TestingUtilities
{
    /**
     * Set up backend user for testing
     */
    protected function setUpBackendUser(int $userId): \TYPO3\CMS\Core\Authentication\BackendUserAuthentication
    {
        $backendUser = parent::setUpBackendUser($userId);
        Bootstrap::initializeBackendAuthentication();
        $GLOBALS['BE_USER'] = $backendUser;

        // Set up context
        $context = GeneralUtility::makeInstance(Context::class);
        $userAspect = GeneralUtility::makeInstance(UserAspect::class, $backendUser);
        $context->setAspect('backend.user', $userAspect);
        
        return $backendUser;
    }

    /**
     * Clean up T3Hauler specific tables
     */
    protected function cleanupDatabase(): void
    {
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);

        $tables = [
            'tx_t3hauler_change_records',
            'tx_t3hauler_snapshots',
            'tx_t3hauler_migrations',
        ];

        foreach ($tables as $table) {
            try {
                $connection = $connectionPool->getConnectionForTable($table);
                $connection->truncate($table);
            } catch (\Exception $e) {
                // Table might not exist, ignore
            }
        }
    }

    /**
     * Assert that a table has specific number of records
     */
    protected function assertTableCount(string $table, int $expectedCount, string $message = ''): void
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable($table);

        $count = $connection->count('*', $table, []);

        if ($message === '') {
            $message = "Expected table '{$table}' to have {$expectedCount} records, got {$count}";
        }

        self::assertSame($expectedCount, $count, $message);
    }

    /**
     * Assert that a specific record exists in table
     */
    protected function assertRecordExists(string $table, array $where, string $message = ''): void
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable($table);

        $count = $connection->count('*', $table, $where);

        if ($message === '') {
            $whereString = implode(' AND ', array_map(
                fn($key, $value) => "{$key} = {$value}",
                array_keys($where),
                array_values($where)
            ));
            $message = "Expected record in table '{$table}' with conditions: {$whereString}";
        }

        self::assertGreaterThan(0, $count, $message);
    }

    /**
     * Get connection for table
     */
    protected function getConnectionForTable(string $table): \TYPO3\CMS\Core\Database\Connection
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable($table);
    }

    /**
     * Insert test data into table
     */
    protected function insertTestData(string $table, array $data): int
    {
        $connection = $this->getConnectionForTable($table);
        $connection->insert($table, $data);
        return (int)$connection->lastInsertId();
    }

    /**
     * Update test data in table
     */
    protected function updateTestData(string $table, array $data, array $where): int
    {
        $connection = $this->getConnectionForTable($table);
        return $connection->update($table, $data, $where);
    }

    /**
     * Delete test data from table
     */
    protected function deleteTestData(string $table, array $where): int
    {
        $connection = $this->getConnectionForTable($table);
        return $connection->delete($table, $where);
    }

    /**
     * Setup method for T3Hauler tests
     */
    protected function setUpT3HaulerTests(): void
    {
        // Import base fixtures
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/be_users.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/tt_content.csv');

        // Set up backend user context
        $this->setUpBackendUser(1);
    }

    /**
     * Tear down method for T3Hauler tests
     */
    protected function tearDownT3HaulerTests(): void
    {
        $this->cleanupDatabase();
    }
}