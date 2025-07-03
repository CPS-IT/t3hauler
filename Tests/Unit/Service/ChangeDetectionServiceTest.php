<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Tests\Unit\Service;

use Cpsit\T3hauler\Configuration\T3HaulerConfiguration;
use Cpsit\T3hauler\Domain\Model\DataSnapshot;
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->hashUtility = $this->createMock(HashUtility::class);
        $this->snapshotRepository = $this->createMock(DataSnapshotRepository::class);
        $this->configuration = $this->createMock(T3HaulerConfiguration::class);

        $this->configuration->method('getEnabledTables')
            ->willReturn(['pages', 'tt_content']);
        $this->configuration->method('getExcludedFields')
            ->willReturn(['tstamp', 'crdate']);
        $this->configuration->method('getHashAlgorithm')
            ->willReturn('sha256');

        $this->subject = new ChangeDetectionService(
            $this->hashUtility,
            $this->snapshotRepository,
            $this->configuration
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

        self::assertSame('no_baseline', $result['status']);
        self::assertTrue($result['changed']);
        self::assertSame('current_hash', $result['current_hash']);
        self::assertNull($result['baseline_hash']);
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

        self::assertSame('unchanged', $result['status']);
        self::assertFalse($result['changed']);
        self::assertSame($currentHash, $result['current_hash']);
        self::assertSame($currentHash, $result['baseline_hash']);
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

        self::assertSame('changed', $result['status']);
        self::assertTrue($result['changed']);
        self::assertSame($currentHash, $result['current_hash']);
        self::assertSame($baselineHash, $result['baseline_hash']);
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

        self::assertSame(2, $result['total_tables']);
        self::assertSame(['pages'], $result['changed_tables']);
        self::assertSame(['tt_content'], $result['unchanged_tables']);
        self::assertSame([], $result['no_baseline_tables']);
        self::assertTrue($result['has_changes']);
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
}
