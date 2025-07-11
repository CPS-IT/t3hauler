<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Tests\Unit\Service;

use Cpsit\T3hauler\Configuration\T3HaulerConfiguration;
use Cpsit\T3hauler\Domain\Dto\ChangesSummary;
use Cpsit\T3hauler\Domain\Dto\TableChanges;
use Cpsit\T3hauler\Domain\Enumeration\TableStatus;
use Cpsit\T3hauler\Domain\Model\ChangeRecord;
use Cpsit\T3hauler\Domain\Model\DataSnapshot;
use Cpsit\T3hauler\Domain\Repository\ChangeRecordRepository;
use Cpsit\T3hauler\Domain\Repository\DataSnapshotRepository;
use Cpsit\T3hauler\Service\ChangeDetectionService;
use Cpsit\T3hauler\Utility\HashUtility;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ChangeDetectionServiceTest extends TestCase
{
    private ChangeDetectionService $subject;
    /** @var HashUtility&\PHPUnit\Framework\MockObject\MockObject */
    private HashUtility $hashUtility;
    /** @var DataSnapshotRepository&\PHPUnit\Framework\MockObject\MockObject */
    private DataSnapshotRepository $snapshotRepository;
    /** @var T3HaulerConfiguration&\PHPUnit\Framework\MockObject\MockObject */
    private T3HaulerConfiguration $configuration;
    /** @var ChangeRecordRepository&\PHPUnit\Framework\MockObject\MockObject */
    private ChangeRecordRepository $changeRecordRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hashUtility = $this->createMock(HashUtility::class);
        $this->snapshotRepository = $this->createMock(DataSnapshotRepository::class);
        $this->configuration = $this->createMock(T3HaulerConfiguration::class);
        $this->changeRecordRepository = $this->createMock(ChangeRecordRepository::class);

        $this->configuration->method('getEnabledTables')
            ->willReturn(['pages', 'tt_content']);
        $this->configuration->method('getExcludedFields')
            ->willReturn(['tstamp', 'crdate']);
        $this->configuration->method('getHashAlgorithm')
            ->willReturn('sha256');

        $this->subject = new ChangeDetectionService(
            $this->hashUtility,
            $this->snapshotRepository,
            $this->configuration,
            $this->changeRecordRepository
        );
    }

    #[Test]
    public function detectTableChangesReturnsNoBaselineWhenSnapshotNotFound(): void
    {
        $this->hashUtility->expects(self::once())->method('calculateTableHash')
            ->willReturn('current_hash');
        $this->snapshotRepository->expects(self::once())->method('findLatestByTableName')
            ->willReturn(null);

        $result = $this->subject->detectTableChanges('pages');

        self::assertInstanceOf(TableChanges::class, $result);
        self::assertSame(TableStatus::NO_BASELINE, $result->status);
        self::assertTrue($result->hasChanges);
        self::assertSame('current_hash', $result->currentHash);
        self::assertNull($result->baselineHash);
        self::assertSame('pages', $result->tableName);
    }

    #[Test]
    public function detectTableChangesReturnsUnchangedWhenHashesMatch(): void
    {
        $currentHash = 'same_hash';
        $snapshot = new DataSnapshot('test_id', 'pages', $currentHash);

        $this->hashUtility->expects(self::once())->method('calculateTableHash')
            ->willReturn($currentHash);
        $this->snapshotRepository->expects(self::once())->method('findLatestByTableName')
            ->willReturn($snapshot);

        $result = $this->subject->detectTableChanges('pages');

        self::assertInstanceOf(TableChanges::class, $result);
        self::assertSame(TableStatus::UNCHANGED, $result->status);
        self::assertFalse($result->hasChanges);
        self::assertSame($currentHash, $result->currentHash);
        self::assertSame($currentHash, $result->baselineHash);
        self::assertSame('pages', $result->tableName);
    }

    #[Test]
    public function detectTableChangesReturnsChangedWhenHashesDiffer(): void
    {
        $currentHash = 'new_hash';
        $baselineHash = 'old_hash';
        $snapshot = new DataSnapshot('test_id', 'pages', $baselineHash);

        $this->hashUtility->expects(self::once())->method('calculateTableHash')
            ->willReturn($currentHash);
        $this->snapshotRepository->expects(self::once())->method('findLatestByTableName')
            ->willReturn($snapshot);

        $result = $this->subject->detectTableChanges('pages');

        self::assertInstanceOf(TableChanges::class, $result);
        self::assertSame(TableStatus::CHANGED, $result->status);
        self::assertTrue($result->hasChanges);
        self::assertSame($currentHash, $result->currentHash);
        self::assertSame($baselineHash, $result->baselineHash);
        self::assertSame('pages', $result->tableName);
    }

    #[Test]
    public function hasChangesReturnsTrueWhenTablesHaveChanges(): void
    {
        $this->hashUtility->expects(self::exactly(2))->method('calculateTableHash')
            ->willReturnOnConsecutiveCalls('hash1', 'hash2');
        $this->snapshotRepository->expects(self::exactly(2))->method('findLatestByTableName')
            ->willReturnOnConsecutiveCalls(
                new DataSnapshot('id1', 'pages', 'old_hash1'),
                new DataSnapshot('id2', 'tt_content', 'hash2') // This one matches
            );

        $result = $this->subject->hasChanges();

        self::assertTrue($result);
    }

    #[Test]
    public function hasChangesReturnsFalseWhenNoTablesHaveChanges(): void
    {
        $this->hashUtility->expects(self::exactly(2))->method('calculateTableHash')
            ->willReturnOnConsecutiveCalls('hash1', 'hash2');
        $this->snapshotRepository->expects(self::exactly(2))->method('findLatestByTableName')
            ->willReturnOnConsecutiveCalls(
                new DataSnapshot('id1', 'pages', 'hash1'),
                new DataSnapshot('id2', 'tt_content', 'hash2')
            );

        $result = $this->subject->hasChanges();

        self::assertFalse($result);
    }

    #[Test]
    public function createTableSnapshotCreatesAndSavesSnapshot(): void
    {
        $currentHash = 'test_hash';
        $this->hashUtility->expects(self::once())->method('calculateTableHash')
            ->willReturn($currentHash);

        $savedSnapshot = new DataSnapshot('test_id', 'pages', $currentHash);
        $this->snapshotRepository->expects(self::once())->method('save')
            ->willReturn($savedSnapshot);

        $result = $this->subject->createTableSnapshot('pages');

        self::assertInstanceOf(DataSnapshot::class, $result);
        self::assertSame('pages', $result->getTableName());
        self::assertSame($currentHash, $result->getHash());
    }

    #[Test]
    public function getChangesSummaryReturnsCorrectCounts(): void
    {
        $this->hashUtility->expects(self::exactly(2))->method('calculateTableHash')
            ->willReturnOnConsecutiveCalls('new_hash', 'same_hash');
        $this->snapshotRepository->expects(self::exactly(2))->method('findLatestByTableName')
            ->willReturnOnConsecutiveCalls(
                new DataSnapshot('id1', 'pages', 'old_hash'), // Changed
                new DataSnapshot('id2', 'tt_content', 'same_hash') // Unchanged
            );

        $result = $this->subject->getChangesSummary();

        self::assertInstanceOf(ChangesSummary::class, $result);
        self::assertSame(2, $result->totalTables);
        self::assertSame(['pages'], $result->changedTables);
        self::assertSame(['tt_content'], $result->unchangedTables);
        self::assertSame([], $result->noBaselineTables);
        self::assertTrue($result->hasChanges);
    }

    #[Test]
    public function compareSnapshotsReturnsCorrectComparison(): void
    {
        $snapshot1 = new DataSnapshot('id1', 'pages', 'hash1', new \DateTimeImmutable('2024-01-01 10:00:00'));
        $snapshot2 = new DataSnapshot('id2', 'pages', 'hash2', new \DateTimeImmutable('2024-01-01 11:00:00'));

        $result = $this->subject->compareSnapshots($snapshot1, $snapshot2);

        self::assertSame('pages', $result['table_name']);
        self::assertSame('id1', $result['snapshot1_identifier']);
        self::assertSame('id2', $result['snapshot2_identifier']);
        self::assertSame('hash1', $result['snapshot1_hash']);
        self::assertSame('hash2', $result['snapshot2_hash']);
        self::assertTrue($result['changed']);
        self::assertSame(3600, $result['time_difference']); // 1 hour
    }

    #[Test]
    public function compareSnapshotsThrowsExceptionForDifferentTables(): void
    {
        $snapshot1 = new DataSnapshot('id1', 'pages', 'hash1');
        $snapshot2 = new DataSnapshot('id2', 'tt_content', 'hash2');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot compare snapshots of different tables');

        $this->subject->compareSnapshots($snapshot1, $snapshot2);
    }

    #[Test]
    public function cleanupOldSnapshotsCallsRepositoryWithCorrectDate(): void
    {
        $this->snapshotRepository->expects(self::once())
            ->method('deleteOlderThan')
            ->with(self::callback(function (\DateTimeInterface $date) {
                $expected = new \DateTimeImmutable('-30 days');
                // Allow 1 second difference for test execution time
                return abs($date->getTimestamp() - $expected->getTimestamp()) <= 1;
            }))
            ->willReturn(5);

        $result = $this->subject->cleanupOldSnapshots(30);

        self::assertSame(5, $result);
    }

    #[Test]
    public function getDetailedChangesReturnsStructuredChangeData(): void
    {
        $snapshotUid = 123;
        $changeRecord = new ChangeRecord();
        $changeRecord->setUid(1);
        $changeRecord->setTableName('pages');
        $changeRecord->setRecordUid(456);
        $changeRecord->setChangeType('insert');
        $changeRecord->setDetectedAt(1701432000);

        $this->changeRecordRepository->expects(self::once())
            ->method('findBySnapshotUid')
            ->with($snapshotUid)
            ->willReturn([$changeRecord]);

        $this->changeRecordRepository->expects(self::once())
            ->method('getStatistics')
            ->with($snapshotUid)
            ->willReturn(['insert' => 1, 'update' => 0, 'delete' => 0, 'move' => 0, 'total' => 1]);

        $result = $this->subject->getDetailedChanges($snapshotUid);

        self::assertArrayHasKey('summary', $result);
        self::assertArrayHasKey('records', $result);
        self::assertArrayHasKey('by_table', $result);
        self::assertArrayHasKey('by_type', $result);
        self::assertCount(1, $result['records']);
        self::assertArrayHasKey('pages', $result['by_table']);
        self::assertArrayHasKey('insert', $result['by_type']);
    }

    #[Test]
    public function getTableChangesReturnsChangesForSpecificTable(): void
    {
        $tableName = 'pages';
        $snapshotUid = 123;
        $expectedChanges = [new ChangeRecord()];

        $this->changeRecordRepository->expects(self::once())
            ->method('findByTableName')
            ->with($tableName, $snapshotUid)
            ->willReturn($expectedChanges);

        $result = $this->subject->getTableChanges($tableName, $snapshotUid);

        self::assertSame($expectedChanges, $result);
    }

    #[Test]
    public function getRecordChangesReturnsChangesForSpecificRecord(): void
    {
        $tableName = 'pages';
        $recordUid = 456;
        $snapshotUid = 123;
        $expectedChanges = [new ChangeRecord()];

        $this->changeRecordRepository->expects(self::once())
            ->method('findByTableAndRecord')
            ->with($tableName, $recordUid, $snapshotUid)
            ->willReturn($expectedChanges);

        $result = $this->subject->getRecordChanges($tableName, $recordUid, $snapshotUid);

        self::assertSame($expectedChanges, $result);
    }

    #[Test]
    public function hasTrackedChangesReturnsTrueWhenChangesExist(): void
    {
        $snapshotUid = 123;

        $this->changeRecordRepository->expects(self::once())
            ->method('countBySnapshotUid')
            ->with($snapshotUid)
            ->willReturn(5);

        $result = $this->subject->hasTrackedChanges($snapshotUid);

        self::assertTrue($result);
    }

    #[Test]
    public function hasTrackedChangesReturnsFalseWhenNoChangesExist(): void
    {
        $snapshotUid = 123;

        $this->changeRecordRepository->expects(self::once())
            ->method('countBySnapshotUid')
            ->with($snapshotUid)
            ->willReturn(0);

        $result = $this->subject->hasTrackedChanges($snapshotUid);

        self::assertFalse($result);
    }

    #[Test]
    public function getCurrentSnapshotReturnsCurrentSnapshot(): void
    {
        $expectedSnapshot = new DataSnapshot('current', 'tx_t3hauler_snapshots', 'hash123');

        $this->snapshotRepository->expects(self::once())
            ->method('findCurrentSnapshot')
            ->willReturn($expectedSnapshot);

        $result = $this->subject->getCurrentSnapshot();

        self::assertSame($expectedSnapshot, $result);
    }

    #[Test]
    public function getCurrentSnapshotReturnsNullWhenNoCurrentSnapshot(): void
    {
        $this->snapshotRepository->expects(self::once())
            ->method('findCurrentSnapshot')
            ->willReturn(null);

        $result = $this->subject->getCurrentSnapshot();

        self::assertNull($result);
    }
}
