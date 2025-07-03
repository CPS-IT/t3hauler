<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Tests\Unit\Utility;

use Cpsit\T3hauler\Utility\HashUtility;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

class HashUtilityTest extends TestCase
{
    private HashUtility $subject;
    /** @var ConnectionPool&\PHPUnit\Framework\MockObject\MockObject */
    private ConnectionPool $connectionPool;
    /** @var Connection&\PHPUnit\Framework\MockObject\MockObject */
    private Connection $connection;
    /** @var QueryBuilder&\PHPUnit\Framework\MockObject\MockObject */
    private QueryBuilder $queryBuilder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connectionPool = $this->createMock(ConnectionPool::class);
        $this->connection = $this->createMock(Connection::class);
        $this->queryBuilder = $this->createMock(QueryBuilder::class);

        $this->connectionPool->method('getConnectionForTable')
            ->willReturn($this->connection);

        $this->connection->method('createQueryBuilder')
            ->willReturn($this->queryBuilder);

        $this->subject = new HashUtility($this->connectionPool);
    }

    #[Test]
    public function calculateRowsHashReturnsConsistentHash(): void
    {
        $rows = [
            ['uid' => 1, 'title' => 'Test 1', 'tstamp' => 1234567890],
            ['uid' => 2, 'title' => 'Test 2', 'tstamp' => 1234567891],
        ];

        $hash1 = $this->subject->calculateRowsHash($rows);
        $hash2 = $this->subject->calculateRowsHash($rows);

        self::assertSame($hash1, $hash2);
        self::assertSame(64, strlen($hash1)); // SHA256 length
    }

    #[Test]
    public function calculateRowsHashExcludesSpecifiedFields(): void
    {
        $rows = [
            ['uid' => 1, 'title' => 'Test', 'tstamp' => 1234567890],
        ];

        $hashWithTstamp = $this->subject->calculateRowsHash($rows);
        $hashWithoutTstamp = $this->subject->calculateRowsHash($rows, ['tstamp']);

        self::assertNotSame($hashWithTstamp, $hashWithoutTstamp);
    }

    #[Test]
    public function calculateRowsHashIgnoresRowOrder(): void
    {
        $rows1 = [
            ['uid' => 1, 'title' => 'Test 1'],
            ['uid' => 2, 'title' => 'Test 2'],
        ];

        $rows2 = [
            ['uid' => 2, 'title' => 'Test 2'],
            ['uid' => 1, 'title' => 'Test 1'],
        ];

        $hash1 = $this->subject->calculateRowsHash($rows1);
        $hash2 = $this->subject->calculateRowsHash($rows2);

        self::assertSame($hash1, $hash2);
    }

    #[Test]
    public function calculateRowsHashIsSensitiveToContent(): void
    {
        $rows1 = [['uid' => 1, 'title' => 'Test 1']];
        $rows2 = [['uid' => 1, 'title' => 'Test 2']];

        $hash1 = $this->subject->calculateRowsHash($rows1);
        $hash2 = $this->subject->calculateRowsHash($rows2);

        self::assertNotSame($hash1, $hash2);
    }

    #[Test]
    public function compareHashesReturnsTrueForIdenticalHashes(): void
    {
        $hash1 = hash('sha256', 'test');
        $hash2 = hash('sha256', 'test');

        $result = $this->subject->compareHashes($hash1, $hash2);

        self::assertTrue($result);
    }

    #[Test]
    public function compareHashesReturnsFalseForDifferentHashes(): void
    {
        $hash1 = hash('sha256', 'test1');
        $hash2 = hash('sha256', 'test2');

        $result = $this->subject->compareHashes($hash1, $hash2);

        self::assertFalse($result);
    }

    #[Test]
    public function isValidAlgorithmReturnsTrueForValidAlgorithm(): void
    {
        $result = $this->subject->isValidAlgorithm('sha256');

        self::assertTrue($result);
    }

    #[Test]
    public function isValidAlgorithmReturnsFalseForInvalidAlgorithm(): void
    {
        $result = $this->subject->isValidAlgorithm('invalid-algorithm');

        self::assertFalse($result);
    }

    #[Test]
    public function calculateMultiTableHashCombinesTableHashes(): void
    {
        // Mock the query builder chain for multiple calls (2 tables)
        $this->queryBuilder->expects(self::exactly(2))->method('select')->willReturnSelf();
        $this->queryBuilder->expects(self::exactly(2))->method('from')->willReturnSelf();
        // orderBy is only called if getPrimaryKeyColumn returns a non-null value
        // Since we're mocking the connection, it will likely return null, so orderBy won't be called

        // Mock query result
        $result = $this->createMock(\Doctrine\DBAL\Result::class);
        $this->queryBuilder->expects(self::exactly(2))->method('executeQuery')->willReturn($result);
        $result->expects(self::exactly(2))->method('fetchAllAssociative')->willReturn([]);

        $tableConfigs = [
            'pages' => ['excludeFields' => ['tstamp']],
            'tt_content' => ['excludeFields' => ['crdate']],
        ];

        $hash = $this->subject->calculateMultiTableHash($tableConfigs);

        self::assertSame(64, strlen($hash)); // SHA256 length
    }
}
