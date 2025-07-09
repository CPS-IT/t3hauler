<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Tests\Unit\Service;

use Cpsit\T3hauler\Configuration\T3HaulerConfiguration;
use Cpsit\T3hauler\Domain\Model\ChangeRecord;
use Cpsit\T3hauler\Domain\Repository\ChangeRecordRepository;
use Cpsit\T3hauler\Service\ChangeTrackingService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ChangeTrackingService
 */
final class ChangeTrackingServiceTest extends TestCase
{
    private ChangeTrackingService $subject;
    private ChangeRecordRepository&MockObject $changeRecordRepository;
    private T3HaulerConfiguration&MockObject $configuration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->changeRecordRepository = $this->createMock(ChangeRecordRepository::class);
        $this->configuration = $this->createMock(T3HaulerConfiguration::class);

        $this->subject = new ChangeTrackingService(
            $this->changeRecordRepository,
            $this->configuration
        );
    }

    #[Test]
    public function trackChangeCreatesAndPersistsChangeRecord(): void
    {
        $changeData = [
            'snapshot_uid' => 123,
            'table_name' => 'pages',
            'record_uid' => 456,
            'change_type' => 'insert',
            'new_data' => ['title' => 'New Page', 'hidden' => 0],
            'previous_data' => [],
            'be_user' => 1,
            'workspace' => 0,
            'correlation_id' => 't3h_abc123',
        ];

        $this->configuration->method('get')
            ->willReturnMap([
                ['detection.excludeFields', [], ['tstamp', 'crdate']],
                ['detection.tables.pages.excludeFields', [], []],
            ]);

        $this->changeRecordRepository->expects(self::once())
            ->method('add')
            ->with(self::callback(function (ChangeRecord $changeRecord) {
                return $changeRecord->getSnapshotUid() === 123
                    && $changeRecord->getTableName() === 'pages'
                    && $changeRecord->getRecordUid() === 456
                    && $changeRecord->getChangeType() === 'insert'
                    && $changeRecord->getBeUser() === 1
                    && $changeRecord->getWorkspace() === 0
                    && $changeRecord->getCorrelationId() === 't3h_abc123';
            }));

        $this->subject->trackChange($changeData);
    }

    #[Test]
    public function trackChangeSkipsEmptyUpdateChanges(): void
    {
        $changeData = [
            'snapshot_uid' => 123,
            'table_name' => 'pages',
            'record_uid' => 456,
            'change_type' => 'update',
            'new_data' => ['title' => 'Same Title'],
            'previous_data' => ['title' => 'Same Title'],
            'be_user' => 1,
            'workspace' => 0,
            'correlation_id' => 't3h_abc123',
        ];

        $this->configuration->method('get')
            ->willReturnMap([
                ['detection.excludeFields', [], []],
                ['detection.tables.pages.excludeFields', [], []],
            ]);

        $this->changeRecordRepository->expects(self::never())
            ->method('add');

        $this->subject->trackChange($changeData);
    }

    #[Test]
    public function trackChangeHandlesFieldChanges(): void
    {
        $changeData = [
            'snapshot_uid' => 123,
            'table_name' => 'pages',
            'record_uid' => 456,
            'change_type' => 'update',
            'new_data' => ['title' => 'New Title', 'hidden' => 1],
            'previous_data' => ['title' => 'Old Title', 'hidden' => 0],
            'be_user' => 1,
            'workspace' => 0,
            'correlation_id' => 't3h_abc123',
        ];

        $this->configuration->method('get')
            ->willReturnMap([
                ['detection.excludeFields', [], []],
                ['detection.tables.pages.excludeFields', [], []],
            ]);

        $this->changeRecordRepository->expects(self::once())
            ->method('add')
            ->with(self::callback(function (ChangeRecord $changeRecord) {
                $fieldChanges = $changeRecord->getFieldChangesArray();
                return isset($fieldChanges['title'])
                    && $fieldChanges['title']['old'] === 'Old Title'
                    && $fieldChanges['title']['new'] === 'New Title'
                    && isset($fieldChanges['hidden'])
                    && $fieldChanges['hidden']['old'] === 0
                    && $fieldChanges['hidden']['new'] === 1;
            }));

        $this->subject->trackChange($changeData);
    }

    #[Test]
    public function trackChangeExcludesConfiguredFields(): void
    {
        $changeData = [
            'snapshot_uid' => 123,
            'table_name' => 'pages',
            'record_uid' => 456,
            'change_type' => 'update',
            'new_data' => ['title' => 'New Title', 'tstamp' => time(), 'crdate' => time()],
            'previous_data' => ['title' => 'Old Title', 'tstamp' => time() - 100, 'crdate' => time() - 100],
            'be_user' => 1,
            'workspace' => 0,
            'correlation_id' => 't3h_abc123',
        ];

        $this->configuration->method('get')
            ->willReturnMap([
                ['detection.excludeFields', [], ['tstamp', 'crdate']],
                ['detection.tables.pages.excludeFields', [], []],
            ]);

        $this->changeRecordRepository->expects(self::once())
            ->method('add')
            ->with(self::callback(function (ChangeRecord $changeRecord) {
                $fieldChanges = $changeRecord->getFieldChangesArray();
                return isset($fieldChanges['title'])
                    && !isset($fieldChanges['tstamp'])
                    && !isset($fieldChanges['crdate']);
            }));

        $this->subject->trackChange($changeData);
    }

    #[Test]
    public function getChangesForSnapshotDelegatestoRepository(): void
    {
        $snapshotUid = 123;
        $expectedChanges = [new ChangeRecord()];

        $this->changeRecordRepository->expects(self::once())
            ->method('findBySnapshotUid')
            ->with($snapshotUid)
            ->willReturn($expectedChanges);

        $result = $this->subject->getChangesForSnapshot($snapshotUid);

        self::assertSame($expectedChanges, $result);
    }

    #[Test]
    public function getChangesGroupedByTableGroupsChangesByTableName(): void
    {
        $snapshotUid = 123;

        $change1 = new ChangeRecord();
        $change1->setTableName('pages');

        $change2 = new ChangeRecord();
        $change2->setTableName('tt_content');

        $change3 = new ChangeRecord();
        $change3->setTableName('pages');

        $changes = [$change1, $change2, $change3];

        $this->changeRecordRepository->method('findBySnapshotUid')
            ->with($snapshotUid)
            ->willReturn($changes);

        $result = $this->subject->getChangesGroupedByTable($snapshotUid);

        self::assertArrayHasKey('pages', $result);
        self::assertArrayHasKey('tt_content', $result);
        self::assertCount(2, $result['pages']);
        self::assertCount(1, $result['tt_content']);
        self::assertSame($change1, $result['pages'][0]);
        self::assertSame($change3, $result['pages'][1]);
        self::assertSame($change2, $result['tt_content'][0]);
    }

    #[Test]
    public function getChangesForRecordDelegatestoRepository(): void
    {
        $tableName = 'pages';
        $recordUid = 456;
        $snapshotUid = 123;
        $expectedChanges = [new ChangeRecord()];

        $this->changeRecordRepository->expects(self::once())
            ->method('findByTableAndRecord')
            ->with($tableName, $recordUid, $snapshotUid)
            ->willReturn($expectedChanges);

        $result = $this->subject->getChangesForRecord($tableName, $recordUid, $snapshotUid);

        self::assertSame($expectedChanges, $result);
    }

    #[Test]
    public function clearChangesForSnapshotDelegatestoRepository(): void
    {
        $snapshotUid = 123;

        $this->changeRecordRepository->expects(self::once())
            ->method('deleteBySnapshotUid')
            ->with($snapshotUid);

        $this->subject->clearChangesForSnapshot($snapshotUid);
    }

    #[Test]
    public function isSignificantChangeReturnsTrueForTitleChange(): void
    {
        $changeData = [
            'snapshot_uid' => 123,
            'table_name' => 'pages',
            'record_uid' => 456,
            'change_type' => 'update',
            'new_data' => ['title' => 'New Title'],
            'previous_data' => ['title' => 'Old Title'],
            'be_user' => 1,
            'workspace' => 0,
            'correlation_id' => 't3h_abc123',
        ];

        $this->configuration->method('get')
            ->willReturnMap([
                ['detection.excludeFields', [], []],
                ['detection.tables.pages.excludeFields', [], []],
            ]);

        $this->changeRecordRepository->expects(self::once())
            ->method('add');

        $this->subject->trackChange($changeData);
    }

    #[Test]
    public function isSignificantChangeReturnsFalseForSmallTimestampDifference(): void
    {
        $time = time();
        $changeData = [
            'snapshot_uid' => 123,
            'table_name' => 'pages',
            'record_uid' => 456,
            'change_type' => 'update',
            'new_data' => ['tstamp' => $time],
            'previous_data' => ['tstamp' => $time], // Same timestamp
            'be_user' => 1,
            'workspace' => 0,
            'correlation_id' => 't3h_abc123',
        ];

        $this->configuration->method('get')
            ->willReturnMap([
                ['detection.excludeFields', [], []],
                ['detection.tables.pages.excludeFields', [], []],
            ]);

        $this->changeRecordRepository->expects(self::never())
            ->method('add');

        $this->subject->trackChange($changeData);
    }

    #[Test]
    public function isSignificantChangeReturnsTrueForHiddenFieldChange(): void
    {
        $changeData = [
            'snapshot_uid' => 123,
            'table_name' => 'pages',
            'record_uid' => 456,
            'change_type' => 'update',
            'new_data' => ['hidden' => 1],
            'previous_data' => ['hidden' => 0],
            'be_user' => 1,
            'workspace' => 0,
            'correlation_id' => 't3h_abc123',
        ];

        $this->configuration->method('get')
            ->willReturnMap([
                ['detection.excludeFields', [], []],
                ['detection.tables.pages.excludeFields', [], []],
            ]);

        $this->changeRecordRepository->expects(self::once())
            ->method('add');

        $this->subject->trackChange($changeData);
    }

    #[Test]
    public function isSignificantChangeReturnsTrueForDeletedFieldChange(): void
    {
        $changeData = [
            'snapshot_uid' => 123,
            'table_name' => 'pages',
            'record_uid' => 456,
            'change_type' => 'update',
            'new_data' => ['deleted' => 1],
            'previous_data' => ['deleted' => 0],
            'be_user' => 1,
            'workspace' => 0,
            'correlation_id' => 't3h_abc123',
        ];

        $this->configuration->method('get')
            ->willReturnMap([
                ['detection.excludeFields', [], []],
                ['detection.tables.pages.excludeFields', [], []],
            ]);

        $this->changeRecordRepository->expects(self::once())
            ->method('add');

        $this->subject->trackChange($changeData);
    }
}
