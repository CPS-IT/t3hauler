<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Tests\Functional\Service;

use Cpsit\T3hauler\Configuration\T3HaulerConfiguration;
use Cpsit\T3hauler\Domain\Model\DataSnapshot;
use Cpsit\T3hauler\Domain\Repository\ChangeRecordRepository;
use Cpsit\T3hauler\Domain\Repository\DataSnapshotRepository;
use Cpsit\T3hauler\Service\ChangeDetectionService;
use Cpsit\T3hauler\Tests\Functional\TestingUtilities;
use Cpsit\T3hauler\Utility\HashUtility;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Functional tests for ChangeDetectionService
 */
final class ChangeDetectionServiceTest extends FunctionalTestCase
{
    use TestingUtilities;
    private ChangeDetectionService $subject;
    private DataSnapshotRepository $snapshotRepository;
    private ChangeRecordRepository $changeRecordRepository;
    private T3HaulerConfiguration $configuration;

    /**
     * @var array<non-empty-string>
     */
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/t3hauler',
    ];

    /**
     * @var array<non-empty-string>
     */
    protected array $coreExtensionsToLoad = [
        'core',
        'backend',
        'frontend',
    ];

    /**
     * @var array<string, non-empty-string>
     */
    protected array $pathsToLinkInTestInstance = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpT3HaulerTests();

        $this->snapshotRepository = GeneralUtility::makeInstance(DataSnapshotRepository::class);
        $this->changeRecordRepository = GeneralUtility::makeInstance(ChangeRecordRepository::class);
        $this->configuration = GeneralUtility::makeInstance(T3HaulerConfiguration::class);

        $hashUtility = GeneralUtility::makeInstance(HashUtility::class);

        $this->subject = new ChangeDetectionService(
            $hashUtility,
            $this->snapshotRepository,
            $this->configuration,
            $this->changeRecordRepository
        );
    }

    protected function tearDown(): void
    {
        $this->tearDownT3HaulerTests();
        parent::tearDown();
    }

    #[Test]
    public function detectTableChangesWithRealDatabase(): void
    {
        // Test with pages table that has fixture data
        $result = $this->subject->detectTableChanges('pages');

        // First run should show no baseline
        self::assertSame('no_baseline', $result['status']);
        self::assertTrue($result['changed']);
        self::assertNotEmpty($result['current_hash']);
        self::assertNull($result['baseline_hash']);
    }

    #[Test]
    public function createAndCompareTableSnapshots(): void
    {
        // Create initial snapshot
        $initialSnapshot = $this->subject->createTableSnapshot('pages');

        self::assertInstanceOf(DataSnapshot::class, $initialSnapshot);
        self::assertSame('pages', $initialSnapshot->getTableName());
        self::assertNotEmpty($initialSnapshot->getHash());
        self::assertGreaterThan(0, $initialSnapshot->getUid());

        // Verify snapshot was saved to database
        $this->assertRecordExists('tx_t3hauler_snapshots', ['uid' => $initialSnapshot->getUid()]);

        // Second detection should show no changes
        $result = $this->subject->detectTableChanges('pages');
        self::assertSame('unchanged', $result['status']);
        self::assertFalse($result['changed']);
        self::assertSame($initialSnapshot->getHash(), $result['current_hash']);
        self::assertSame($initialSnapshot->getHash(), $result['baseline_hash']);
    }

    #[Test]
    public function detectChangesAfterDataModification(): void
    {
        // Create initial snapshot
        $initialSnapshot = $this->subject->createTableSnapshot('pages');
        $initialHash = $initialSnapshot->getHash();

        // Modify data in pages table
        $this->updateTestData('pages', ['title' => 'Modified Title'], ['uid' => 2]);

        // Detect changes
        $result = $this->subject->detectTableChanges('pages');

        self::assertSame('changed', $result['status']);
        self::assertTrue($result['changed']);
        self::assertNotSame($initialHash, $result['current_hash']);
        self::assertSame($initialHash, $result['baseline_hash']);
    }

    #[Test]
    public function hasChangesDetectsMultipleTables(): void
    {
        // Create snapshots for multiple tables
        $this->subject->createTableSnapshot('pages');
        $this->subject->createTableSnapshot('tt_content');

        // Should detect no changes initially
        self::assertFalse($this->subject->hasChanges());

        // Modify one table
        $this->updateTestData('pages', ['title' => 'Changed Page Title'], ['uid' => 2]);

        // Should now detect changes
        self::assertTrue($this->subject->hasChanges());
    }

    #[Test]
    public function getChangesSummaryProvidesAccurateStatistics(): void
    {
        // Create initial snapshots
        $this->subject->createTableSnapshot('pages');
        $this->subject->createTableSnapshot('tt_content');

        // Modify one table
        $this->updateTestData('pages', ['title' => 'Summary Test'], ['uid' => 2]);

        $summary = $this->subject->getChangesSummary();

        self::assertSame(2, $summary['total_tables']);
        self::assertContains('pages', $summary['changed_tables']);
        self::assertContains('tt_content', $summary['unchanged_tables']);
        self::assertEmpty($summary['no_baseline_tables']);
        self::assertTrue($summary['has_changes']);
    }

    #[Test]
    public function compareSnapshotsWithRealData(): void
    {
        // Create first snapshot
        $snapshot1 = $this->subject->createTableSnapshot('pages');

        // Modify data
        $this->updateTestData('pages', ['title' => 'Comparison Test'], ['uid' => 2]);

        // Create second snapshot
        $snapshot2 = $this->subject->createTableSnapshot('pages');

        // Compare snapshots
        $comparison = $this->subject->compareSnapshots($snapshot1, $snapshot2);

        self::assertSame('pages', $comparison['table_name']);
        self::assertSame($snapshot1->getIdentifier(), $comparison['snapshot1_identifier']);
        self::assertSame($snapshot2->getIdentifier(), $comparison['snapshot2_identifier']);
        self::assertSame($snapshot1->getHash(), $comparison['snapshot1_hash']);
        self::assertSame($snapshot2->getHash(), $comparison['snapshot2_hash']);
        self::assertTrue($comparison['changed']);
        self::assertGreaterThanOrEqual(0, $comparison['time_difference']);
    }

    #[Test]
    public function cleanupOldSnapshotsRemovesExpiredData(): void
    {
        // Create test snapshots with different dates
        $oldSnapshotData = [
            'identifier' => 'old_test_snapshot',
            'table_name' => 'pages',
            'hash' => 'old_hash_123',
            'created_at' => time() - (31 * 24 * 60 * 60), // 31 days ago
            'metadata' => '{}',
        ];

        $recentSnapshotData = [
            'identifier' => 'recent_test_snapshot',
            'table_name' => 'pages',
            'hash' => 'recent_hash_456',
            'created_at' => time() - (10 * 24 * 60 * 60), // 10 days ago
            'metadata' => '{}',
        ];

        $this->insertTestData('tx_t3hauler_snapshots', $oldSnapshotData);
        $this->insertTestData('tx_t3hauler_snapshots', $recentSnapshotData);

        // Verify both snapshots exist
        $this->assertTableCount('tx_t3hauler_snapshots', 2);

        // Clean up snapshots older than 30 days
        $deletedCount = $this->subject->cleanupOldSnapshots(30);

        self::assertSame(1, $deletedCount);
        $this->assertTableCount('tx_t3hauler_snapshots', 1);

        // Verify only the recent snapshot remains
        $this->assertRecordExists('tx_t3hauler_snapshots', ['identifier' => 'recent_test_snapshot']);
    }

    #[Test]
    public function getCurrentSnapshotReturnsActiveSnapshot(): void
    {
        // No current snapshot initially
        $currentSnapshot = $this->subject->getCurrentSnapshot();
        self::assertNull($currentSnapshot);

        // Create a current snapshot
        $snapshotData = [
            'identifier' => 'current_test_snapshot',
            'table_name' => 'tx_t3hauler_snapshots',
            'hash' => 'current_hash_789',
            'created_at' => time(),
            'metadata' => '{}',
            'is_current' => 1,
        ];

        $this->insertTestData('tx_t3hauler_snapshots', $snapshotData);

        // Should now return the current snapshot
        $currentSnapshot = $this->subject->getCurrentSnapshot();
        self::assertInstanceOf(DataSnapshot::class, $currentSnapshot);
        self::assertSame('current_test_snapshot', $currentSnapshot->getIdentifier());
    }

    #[Test]
    public function hasTrackedChangesDetectsChangeRecords(): void
    {
        // Create a snapshot
        $snapshot = $this->subject->createTableSnapshot('pages');
        $snapshotUid = $snapshot->getUid();

        // Initially no tracked changes
        self::assertFalse($this->subject->hasTrackedChanges($snapshotUid));

        // Add a change record
        $changeData = [
            'snapshot_uid' => $snapshotUid,
            'table_name' => 'pages',
            'record_uid' => 2,
            'record_pid' => 1,
            'change_type' => 'update',
            'field_changes' => '{"title":{"old":"Test Page 1","new":"Modified Page 1"}}',
            'record_hash' => 'test_hash_123',
            'previous_hash' => 'previous_hash_456',
            'detected_at' => time(),
            'be_user' => 1,
            'workspace' => 0,
            'language_uid' => 0,
            'correlation_id' => 'test_correlation_123',
        ];

        $this->insertTestData('tx_t3hauler_change_records', $changeData);

        // Should now detect tracked changes
        self::assertTrue($this->subject->hasTrackedChanges($snapshotUid));
    }

    #[Test]
    public function getDetailedChangesReturnsStructuredData(): void
    {
        // Create snapshot and change records
        $snapshot = $this->subject->createTableSnapshot('pages');
        $snapshotUid = $snapshot->getUid();

        // Add multiple change records
        $changes = [
            [
                'snapshot_uid' => $snapshotUid,
                'table_name' => 'pages',
                'record_uid' => 2,
                'record_pid' => 1,
                'change_type' => 'update',
                'field_changes' => '{"title":{"old":"Test Page 1","new":"Updated Page 1"}}',
                'record_hash' => 'hash_1',
                'detected_at' => time(),
                'be_user' => 1,
                'workspace' => 0,
                'correlation_id' => 'correlation_1',
            ],
            [
                'snapshot_uid' => $snapshotUid,
                'table_name' => 'tt_content',
                'record_uid' => 1,
                'record_pid' => 2,
                'change_type' => 'insert',
                'field_changes' => '{"header":{"old":null,"new":"New Content"}}',
                'record_hash' => 'hash_2',
                'detected_at' => time(),
                'be_user' => 1,
                'workspace' => 0,
                'correlation_id' => 'correlation_2',
            ],
        ];

        foreach ($changes as $change) {
            $this->insertTestData('tx_t3hauler_change_records', $change);
        }

        $detailedChanges = $this->subject->getDetailedChanges($snapshotUid);

        self::assertArrayHasKey('summary', $detailedChanges);
        self::assertArrayHasKey('records', $detailedChanges);
        self::assertArrayHasKey('by_table', $detailedChanges);
        self::assertArrayHasKey('by_type', $detailedChanges);

        // Check summary statistics
        self::assertSame(2, $detailedChanges['summary']['total']);
        self::assertSame(1, $detailedChanges['summary']['update']);
        self::assertSame(1, $detailedChanges['summary']['insert']);

        // Check records grouping
        self::assertCount(2, $detailedChanges['records']);
        self::assertArrayHasKey('pages', $detailedChanges['by_table']);
        self::assertArrayHasKey('tt_content', $detailedChanges['by_table']);
        self::assertArrayHasKey('update', $detailedChanges['by_type']);
        self::assertArrayHasKey('insert', $detailedChanges['by_type']);
    }
}
