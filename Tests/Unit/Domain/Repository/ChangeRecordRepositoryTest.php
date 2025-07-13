<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Tests\Unit\Domain\Repository;

use Cpsit\T3hauler\Domain\Enumeration\RecordChangeType;
use Cpsit\T3hauler\Domain\Model\ChangeRecord;
use Cpsit\T3hauler\Domain\Repository\ChangeRecordRepository;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Expression\ExpressionBuilder;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

/**
 * Unit tests for ChangeRecordRepository
 */
final class ChangeRecordRepositoryTest extends TestCase
{
    private ChangeRecordRepository $subject;
    private ConnectionPool&MockObject $connectionPool;
    private Connection&MockObject $connection;
    private QueryBuilder&MockObject $queryBuilder;
    private ExpressionBuilder&MockObject $expressionBuilder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connectionPool = $this->createMock(ConnectionPool::class);
        $this->connection = $this->createMock(Connection::class);
        $this->queryBuilder = $this->createMock(QueryBuilder::class);
        $this->expressionBuilder = $this->createMock(ExpressionBuilder::class);

        $this->connectionPool->method('getConnectionForTable')
            ->willReturn($this->connection);

        $this->connectionPool->method('getQueryBuilderForTable')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->method('expr')
            ->willReturn($this->expressionBuilder);

        $this->subject = new ChangeRecordRepository($this->connectionPool);
    }

    #[Test]
    public function addInsertsChangeRecordAndSetsUid(): void
    {
        $changeRecord = new ChangeRecord();
        $changeRecord->setSnapshotUid(123);
        $changeRecord->setTableName('pages');
        $changeRecord->setRecordUid(456);
        $changeRecord->setChangeType(RecordChangeType::INSERT);

        $this->connection->expects(self::once())
            ->method('insert')
            ->with(
                'tx_t3hauler_change_records',
                [
                    'snapshot_uid' => 123,
                    'table_name' => 'pages',
                    'record_uid' => 456,
                    'record_pid' => 0,
                    'change_type' => 'insert',
                    'field_changes' => '',
                    'record_hash' => '',
                    'previous_hash' => null,
                    'detected_at' => 0,
                    'be_user' => 0,
                    'workspace' => 0,
                    'language_uid' => 0,
                    'correlation_id' => '',
                ]
            );

        $this->connection->expects(self::once())
            ->method('lastInsertId')
            ->willReturn('789');

        $this->subject->add($changeRecord);

        self::assertSame(789, $changeRecord->getUid());
    }

    #[Test]
    public function findBySnapshotUidReturnsChangeRecords(): void
    {
        $snapshotUid = 123;
        $rows = [
            [
                'uid' => 1,
                'snapshot_uid' => $snapshotUid,
                'table_name' => 'pages',
                'record_uid' => 456,
                'record_pid' => 0,
                'change_type' => 'insert',
                'field_changes' => '{}',
                'record_hash' => 'abc123',
                'previous_hash' => null,
                'detected_at' => 1701432000,
                'be_user' => 1,
                'workspace' => 0,
                'language_uid' => 0,
                'correlation_id' => 't3h_abc123',
            ],
        ];

        $result = $this->createMock(Result::class);
        $result->method('fetchAssociative')
            ->willReturnOnConsecutiveCalls($rows[0], false);

        $this->queryBuilder->method('select')
            ->with('*')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->method('from')
            ->with('tx_t3hauler_change_records')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->method('where')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->method('orderBy')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->method('executeQuery')
            ->willReturn($result);

        $this->queryBuilder->method('createNamedParameter')
            ->with($snapshotUid)
            ->willReturn(':snapshotUid');

        $this->expressionBuilder->method('eq')
            ->with('snapshot_uid', ':snapshotUid')
            ->willReturn('snapshot_uid = :snapshotUid');

        $changes = $this->subject->findBySnapshotUid($snapshotUid);

        self::assertCount(1, $changes);
        self::assertInstanceOf(ChangeRecord::class, $changes[0]);
        self::assertSame(1, $changes[0]->getUid());
        self::assertSame($snapshotUid, $changes[0]->getSnapshotUid());
        self::assertSame('pages', $changes[0]->getTableName());
    }

    #[Test]
    public function findByTableAndRecordReturnsChangeRecords(): void
    {
        $tableName = 'pages';
        $recordUid = 456;
        $snapshotUid = 123;

        $result = $this->createMock(Result::class);
        $result->method('fetchAssociative')
            ->willReturn(false);

        $this->queryBuilder->method('select')
            ->with('*')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->method('from')
            ->with('tx_t3hauler_change_records')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->method('where')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->method('orderBy')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->method('executeQuery')
            ->willReturn($result);

        $changes = $this->subject->findByTableAndRecord($tableName, $recordUid, $snapshotUid);

        self::assertEmpty($changes);
    }

    #[Test]
    public function getStatisticsReturnsStatisticsArray(): void
    {
        $snapshotUid = 123;
        $rows = [
            ['change_type' => 'insert', 'count' => 5],
            ['change_type' => 'update', 'count' => 10],
            ['change_type' => 'delete', 'count' => 2],
        ];

        $result = $this->createMock(Result::class);
        $result->method('fetchAssociative')
            ->willReturnOnConsecutiveCalls($rows[0], $rows[1], $rows[2], false);

        $this->queryBuilder->method('select')
            ->with('change_type')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->method('addSelectLiteral')
            ->with('COUNT(*) as count')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->method('from')
            ->with('tx_t3hauler_change_records')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->method('where')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->method('groupBy')
            ->with('change_type')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->method('executeQuery')
            ->willReturn($result);

        $statistics = $this->subject->getStatistics($snapshotUid);

        self::assertSame(5, $statistics['insert']);
        self::assertSame(10, $statistics['update']);
        self::assertSame(2, $statistics['delete']);
        self::assertSame(0, $statistics['move']);
        self::assertSame(17, $statistics['total']);
    }

    #[Test]
    public function deleteBySnapshotUidDeletesRecords(): void
    {
        $snapshotUid = 123;

        $this->queryBuilder->method('delete')
            ->with('tx_t3hauler_change_records')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->method('where')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->expects(self::once())
            ->method('executeStatement')
            ->willReturn(5);

        $this->subject->deleteBySnapshotUid($snapshotUid);
    }

    #[Test]
    public function deleteOldRecordsDeletesOldRecords(): void
    {
        $days = 30;

        $this->queryBuilder->method('delete')
            ->with('tx_t3hauler_change_records')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->method('where')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->expects(self::once())
            ->method('executeStatement')
            ->willReturn(10);

        $this->subject->deleteOldRecords($days);
    }

    #[Test]
    public function countBySnapshotUidReturnsCount(): void
    {
        $snapshotUid = 123;

        $result = $this->createMock(Result::class);
        $result->method('fetchOne')
            ->willReturn(42);

        $this->queryBuilder->method('count')
            ->with('*')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->method('from')
            ->with('tx_t3hauler_change_records')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->method('where')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->method('executeQuery')
            ->willReturn($result);

        $count = $this->subject->countBySnapshotUid($snapshotUid);

        self::assertSame(42, $count);
    }

    #[Test]
    public function findByChangeTypeReturnsChangeRecords(): void
    {
        $changeType = 'insert';
        $snapshotUid = 123;

        $result = $this->createMock(Result::class);
        $result->method('fetchAssociative')
            ->willReturn(false);

        $this->queryBuilder->method('select')
            ->with('*')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->method('from')
            ->with('tx_t3hauler_change_records')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->method('where')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->method('orderBy')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->method('executeQuery')
            ->willReturn($result);

        $changes = $this->subject->findByChangeType($changeType, $snapshotUid);

        self::assertEmpty($changes);
    }

    #[Test]
    public function findByCorrelationIdReturnsChangeRecords(): void
    {
        $correlationId = 't3h_abc123';

        $result = $this->createMock(Result::class);
        $result->method('fetchAssociative')
            ->willReturn(false);

        $this->queryBuilder->method('select')
            ->with('*')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->method('from')
            ->with('tx_t3hauler_change_records')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->method('where')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->method('orderBy')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->method('executeQuery')
            ->willReturn($result);

        $changes = $this->subject->findByCorrelationId($correlationId);

        self::assertEmpty($changes);
    }

    #[Test]
    public function findByTableNameReturnsChangeRecords(): void
    {
        $tableName = 'pages';
        $snapshotUid = 123;

        $result = $this->createMock(Result::class);
        $result->method('fetchAssociative')
            ->willReturn(false);

        $this->queryBuilder->method('select')
            ->with('*')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->method('from')
            ->with('tx_t3hauler_change_records')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->method('where')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->method('orderBy')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->method('executeQuery')
            ->willReturn($result);

        $changes = $this->subject->findByTableName($tableName, $snapshotUid);

        self::assertEmpty($changes);
    }
}
