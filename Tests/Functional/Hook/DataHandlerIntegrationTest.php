<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Tests\Functional\Hook;

use Cpsit\T3hauler\Tests\Functional\TestingUtilities;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Functional tests for DataHandler integration with change tracking
 */
final class DataHandlerIntegrationTest extends FunctionalTestCase
{
    use TestingUtilities;
    private DataHandler $dataHandler;

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

        // Set up backend user (required for DataHandler)
        $this->setUpBackendUser(1);

        // Create DataHandler using GeneralUtility to ensure proper initialization
        $this->dataHandler = GeneralUtility::makeInstance(DataHandler::class);

        // Initialize DataHandler with start() method to set up all required properties
        $this->dataHandler->start([], [], $GLOBALS['BE_USER']);

        // Set additional required properties
        $this->dataHandler->admin = true;

        // Create a current snapshot for change tracking
        $this->createCurrentSnapshot();

        // Configure T3Hauler to track pages and tt_content
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['t3hauler']['detection']['enabledTables'] = ['pages', 'tt_content'];
    }

    protected function tearDown(): void
    {
        $this->tearDownT3HaulerTests();
        parent::tearDown();
    }

    #[Test]
    public function dataHandlerTracksPageCreation(): void
    {
        // No change records initially
        $this->assertTableCount('tx_t3hauler_change_records', 0);

        // Create new page via DataHandler
        $datamap = [
            'pages' => [
                'NEW_PAGE' => [
                    'pid' => 1,
                    'title' => 'New Test Page',
                    'doktype' => 1,
                    'hidden' => 0,
                ],
            ],
        ];

        $this->dataHandler->datamap = $datamap;
        $this->dataHandler->process_datamap();

        // Verify no errors occurred
        self::assertEmpty($this->dataHandler->errorLog);

        // Verify page was created
        $newPageUid = $this->dataHandler->substNEWwithIDs['NEW_PAGE'];
        self::assertIsInt($newPageUid);
        self::assertGreaterThan(0, $newPageUid);

        // Verify change record was created
        $this->assertTableCount('tx_t3hauler_change_records', 1);

        // Check change record details
        $changeRecord = $this->getConnectionForTable('tx_t3hauler_change_records')
            ->select(['*'], 'tx_t3hauler_change_records', [])
            ->fetchAssociative();

        self::assertSame('pages', $changeRecord['table_name']);
        self::assertSame($newPageUid, $changeRecord['record_uid']);
        self::assertSame('insert', $changeRecord['change_type']);
        self::assertSame(1, $changeRecord['be_user']);
        self::assertStringContainsString('New Test Page', $changeRecord['field_changes']);
    }

    #[Test]
    public function dataHandlerTracksPageUpdate(): void
    {
        // No change records initially
        $this->assertTableCount('tx_t3hauler_change_records', 0);

        // Update existing page via DataHandler
        $datamap = [
            'pages' => [
                2 => [
                    'title' => 'Updated Test Page Title',
                    'hidden' => 1,
                ],
            ],
        ];

        $this->dataHandler->datamap = $datamap;
        $this->dataHandler->process_datamap();

        // Verify no errors occurred
        self::assertEmpty($this->dataHandler->errorLog);

        // Verify change record was created
        $this->assertTableCount('tx_t3hauler_change_records', 1);

        // Check change record details
        $changeRecord = $this->getConnectionForTable('tx_t3hauler_change_records')
            ->select(['*'], 'tx_t3hauler_change_records', [])
            ->fetchAssociative();

        self::assertSame('pages', $changeRecord['table_name']);
        self::assertSame(2, $changeRecord['record_uid']);
        self::assertSame('update', $changeRecord['change_type']);
        self::assertSame(1, $changeRecord['be_user']);

        // Verify field changes are tracked
        $fieldChanges = json_decode($changeRecord['field_changes'], true);
        self::assertArrayHasKey('title', $fieldChanges);
        self::assertSame('Test Page 1', $fieldChanges['title']['old']);
        self::assertSame('Updated Test Page Title', $fieldChanges['title']['new']);
        self::assertArrayHasKey('hidden', $fieldChanges);
        self::assertSame(0, $fieldChanges['hidden']['old']);
        self::assertSame(1, $fieldChanges['hidden']['new']);
    }

    #[Test]
    public function dataHandlerTracksPageDeletion(): void
    {
        // No change records initially
        $this->assertTableCount('tx_t3hauler_change_records', 0);

        // Delete page via DataHandler
        $cmdmap = [
            'pages' => [
                3 => [
                    'delete' => 1,
                ],
            ],
        ];

        $this->dataHandler->cmdmap = $cmdmap;
        $this->dataHandler->process_cmdmap();

        // Verify no errors occurred
        self::assertEmpty($this->dataHandler->errorLog);

        // Verify page was deleted (marked as deleted)
        // Query without deleted restriction to see all records
        $queryBuilder = $this->getConnectionForTable('pages')->createQueryBuilder();
        $queryBuilder->getRestrictions()->removeAll();
        $deletedPage = $queryBuilder
            ->select('deleted')
            ->from('pages')
            ->where($queryBuilder->expr()->eq('uid', 3))
            ->executeQuery()
            ->fetchAssociative();

        self::assertNotFalse($deletedPage, 'Page with UID 3 should still exist but be marked as deleted');
        self::assertSame(1, $deletedPage['deleted']);

        // Verify change record was created
        $this->assertTableCount('tx_t3hauler_change_records', 1);

        // Check change record details
        $changeRecord = $this->getConnectionForTable('tx_t3hauler_change_records')
            ->select(['*'], 'tx_t3hauler_change_records', [])
            ->fetchAssociative();

        self::assertSame('pages', $changeRecord['table_name']);
        self::assertSame(3, $changeRecord['record_uid']);
        self::assertSame('delete', $changeRecord['change_type']);
        self::assertSame(1, $changeRecord['be_user']);
    }

    #[Test]
    public function dataHandlerTracksPageMove(): void
    {
        // No change records initially
        $this->assertTableCount('tx_t3hauler_change_records', 0);

        // Move page via DataHandler
        $cmdmap = [
            'pages' => [
                4 => [
                    'move' => 3, // Move nested page to different parent
                ],
            ],
        ];

        $this->dataHandler->cmdmap = $cmdmap;
        $this->dataHandler->process_cmdmap();

        // Verify no errors occurred
        self::assertEmpty($this->dataHandler->errorLog);

        // Verify page was moved
        $movedPage = $this->getConnectionForTable('pages')
            ->select(['pid'], 'pages', ['uid' => 4])
            ->fetchAssociative();
        self::assertSame(3, $movedPage['pid']);

        // Verify change record was created
        $this->assertTableCount('tx_t3hauler_change_records', 1);

        // Check change record details
        $changeRecord = $this->getConnectionForTable('tx_t3hauler_change_records')
            ->select(['*'], 'tx_t3hauler_change_records', [])
            ->fetchAssociative();

        self::assertSame('pages', $changeRecord['table_name']);
        self::assertSame(4, $changeRecord['record_uid']);
        self::assertSame('move', $changeRecord['change_type']);
        self::assertSame(1, $changeRecord['be_user']);

        // Verify move data is tracked
        $fieldChanges = json_decode($changeRecord['field_changes'], true);
        self::assertArrayHasKey('pid', $fieldChanges);
        self::assertSame(3, $fieldChanges['pid']['new']);
    }

    #[Test]
    public function dataHandlerTracksContentElementChanges(): void
    {
        // No change records initially
        $this->assertTableCount('tx_t3hauler_change_records', 0);

        // Create new content element
        $datamap = [
            'tt_content' => [
                'NEW_CONTENT' => [
                    'pid' => 2,
                    'CType' => 'text',
                    'header' => 'New Content Header',
                    'bodytext' => 'New content body text',
                    'colPos' => 0,
                ],
            ],
        ];

        $this->dataHandler->datamap = $datamap;
        $this->dataHandler->process_datamap();

        // Verify no errors occurred
        self::assertEmpty($this->dataHandler->errorLog);

        // Verify content element was created
        $newContentUid = $this->dataHandler->substNEWwithIDs['NEW_CONTENT'];
        self::assertIsInt($newContentUid);
        self::assertGreaterThan(0, $newContentUid);

        // Verify change record was created
        $this->assertTableCount('tx_t3hauler_change_records', 1);

        // Check change record details
        $changeRecord = $this->getConnectionForTable('tx_t3hauler_change_records')
            ->select(['*'], 'tx_t3hauler_change_records', [])
            ->fetchAssociative();

        self::assertSame('tt_content', $changeRecord['table_name']);
        self::assertSame($newContentUid, $changeRecord['record_uid']);
        self::assertSame('insert', $changeRecord['change_type']);
        self::assertStringContainsString('New Content Header', $changeRecord['field_changes']);
    }

    #[Test]
    public function dataHandlerSkipsDisabledTables(): void
    {
        // No change records initially
        $this->assertTableCount('tx_t3hauler_change_records', 0);

        // Try to update be_users (should not be tracked - not in enabled tables)
        $datamap = [
            'be_users' => [
                1 => [
                    'realName' => 'Updated Test User',
                ],
            ],
        ];

        $this->dataHandler->datamap = $datamap;
        $this->dataHandler->process_datamap();

        // Verify no errors occurred
        self::assertEmpty($this->dataHandler->errorLog);

        // Verify no change record was created (table not enabled)
        $this->assertTableCount('tx_t3hauler_change_records', 0);
    }

    #[Test]
    public function dataHandlerSkipsWhenNoCurrentSnapshot(): void
    {
        // Remove current snapshot
        $this->getConnectionForTable('tx_t3hauler_snapshots')->truncate('tx_t3hauler_snapshots');

        // No change records initially
        $this->assertTableCount('tx_t3hauler_change_records', 0);

        // Update page
        $datamap = [
            'pages' => [
                2 => [
                    'title' => 'Should Not Be Tracked',
                ],
            ],
        ];

        $this->dataHandler->datamap = $datamap;
        $this->dataHandler->process_datamap();

        // Verify no errors occurred
        self::assertEmpty($this->dataHandler->errorLog);

        // Verify no change record was created (no current snapshot)
        $this->assertTableCount('tx_t3hauler_change_records', 0);
    }

    #[Test]
    public function multipleChangesGetSameCorrelationId(): void
    {
        // No change records initially
        $this->assertTableCount('tx_t3hauler_change_records', 0);

        // Update multiple records in same operation
        $datamap = [
            'pages' => [
                2 => [
                    'title' => 'First Updated Page',
                ],
                3 => [
                    'title' => 'Second Updated Page',
                ],
            ],
        ];

        $this->dataHandler->datamap = $datamap;
        $this->dataHandler->process_datamap();

        // Verify no errors occurred
        self::assertEmpty($this->dataHandler->errorLog);

        // Verify both change records were created
        $this->assertTableCount('tx_t3hauler_change_records', 2);

        // Get all change records
        $changeRecords = $this->getConnectionForTable('tx_t3hauler_change_records')
            ->select(['correlation_id', 'record_uid'], 'tx_t3hauler_change_records', [])
            ->fetchAllAssociative();

        // Verify same correlation ID for both records
        self::assertSame($changeRecords[0]['correlation_id'], $changeRecords[1]['correlation_id']);
        self::assertStringStartsWith('t3h_', $changeRecords[0]['correlation_id']);

        // Verify different record UIDs
        self::assertNotSame($changeRecords[0]['record_uid'], $changeRecords[1]['record_uid']);
    }

    /**
     * Create a current snapshot for change tracking tests
     */
    private function createCurrentSnapshot(): void
    {
        $snapshotData = [
            'identifier' => 'test_current_snapshot',
            'table_name' => 'tx_t3hauler_snapshots',
            'hash' => 'current_test_hash',
            'created_at' => time(),
            'metadata' => '{}',
            'migration_version' => '1.0.0',
        ];

        $this->insertTestData('tx_t3hauler_snapshots', $snapshotData);
    }
}
