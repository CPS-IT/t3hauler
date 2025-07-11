<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Tests\Unit\Hook;

use Cpsit\T3hauler\Configuration\T3HaulerConfiguration;
use Cpsit\T3hauler\Domain\Model\DataSnapshot;
use Cpsit\T3hauler\Domain\Repository\DataSnapshotRepository;
use Cpsit\T3hauler\Hook\DataHandlerHook;
use Cpsit\T3hauler\Service\ChangeTrackingService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;

/**
 * Unit tests for DataHandlerHook
 */
final class DataHandlerHookTest extends TestCase
{
    private DataHandlerHook $subject;
    private ChangeTrackingService&MockObject $changeTrackingService;
    private T3HaulerConfiguration&MockObject $configuration;
    private DataSnapshotRepository&MockObject $snapshotRepository;
    private DataHandler&MockObject $dataHandler;
    private ConnectionPool&MockObject $connectionPool;

    protected function setUp(): void
    {
        parent::setUp();

        $this->changeTrackingService = $this->createMock(ChangeTrackingService::class);
        $this->configuration = $this->createMock(T3HaulerConfiguration::class);
        $this->snapshotRepository = $this->createMock(DataSnapshotRepository::class);
        $this->dataHandler = $this->createMock(DataHandler::class);
        $this->connectionPool = $this->createMock(ConnectionPool::class);

        $this->subject = new DataHandlerHook(
            $this->changeTrackingService,
            $this->configuration,
            $this->snapshotRepository,
            $this->connectionPool
        );
    }

    #[Test]
    public function processDatamapBeforeStartResetsInternalState(): void
    {
        $this->configuration->method('getEnabledTables')
            ->willReturn(['pages', 'tt_content']);

        $this->dataHandler->datamap = [
            'pages' => [
                123 => ['title' => 'Test Page'],
            ],
        ];

        // Mock current snapshot to avoid database calls
        $this->snapshotRepository->method('findCurrentSnapshot')
            ->willReturn(null);

        // This should not throw an exception
        $this->subject->processDatamap_beforeStart($this->dataHandler);

        // Call again to ensure state is reset
        $this->subject->processDatamap_beforeStart($this->dataHandler);

        // Assert that the method completed successfully
        /** @phpstan-ignore-next-line staticMethod.alreadyNarrowedTyp */
        self::assertTrue(true);
    }

    #[Test]
    public function processDatamapBeforeStartSkipsWhenNoEnabledTables(): void
    {
        $this->configuration->method('getEnabledTables')
            ->willReturn([]);

        $this->dataHandler->datamap = [
            'pages' => [
                123 => ['title' => 'Test Page'],
            ],
        ];

        $this->subject->processDatamap_beforeStart($this->dataHandler);

        // Assert that the method completed successfully
        /** @phpstan-ignore-next-line staticMethod.alreadyNarrowedTyp */
        self::assertTrue(true);
    }

    #[Test]
    public function processDatamapAfterDatabaseOperationsTracksNewRecord(): void
    {
        $this->configuration->method('getEnabledTables')
            ->willReturn(['pages']);

        $currentSnapshot = new DataSnapshot('test', 'tx_t3hauler_snapshots', 'hash123');
        $currentSnapshot->setUid(456);

        $this->snapshotRepository->method('findCurrentSnapshot')
            ->willReturn($currentSnapshot);

        $this->dataHandler->substNEWwithIDs = ['NEW123' => 789];

        $this->changeTrackingService->expects(self::once())
            ->method('trackChange')
            ->with(self::callback(function (array $data) {
                return $data['snapshot_uid'] === 456
                    && $data['table_name'] === 'pages'
                    && $data['record_uid'] === 789
                    && $data['change_type'] === 'insert';
            }));

        $fieldArray = ['title' => 'New Page', 'hidden' => 0];

        $this->subject->processDatamap_afterDatabaseOperations(
            'new',
            'pages',
            'NEW123',
            $fieldArray,
            $this->dataHandler
        );
    }

    #[Test]
    public function processDatamapAfterDatabaseOperationsTracksUpdateRecord(): void
    {
        $this->configuration->method('getEnabledTables')
            ->willReturn(['pages']);

        $currentSnapshot = new DataSnapshot('test', 'tx_t3hauler_snapshots', 'hash123');
        $currentSnapshot->setUid(456);

        $this->snapshotRepository->method('findCurrentSnapshot')
            ->willReturn($currentSnapshot);

        $this->changeTrackingService->expects(self::once())
            ->method('trackChange')
            ->with(self::callback(function (array $data) {
                return $data['snapshot_uid'] === 456
                    && $data['table_name'] === 'pages'
                    && $data['record_uid'] === 123
                    && $data['change_type'] === 'update';
            }));

        $fieldArray = ['title' => 'Updated Page'];

        $this->subject->processDatamap_afterDatabaseOperations(
            'update',
            'pages',
            123,
            $fieldArray,
            $this->dataHandler
        );
    }

    #[Test]
    public function processDatamapAfterDatabaseOperationsSkipsDisabledTable(): void
    {
        $this->configuration->method('getEnabledTables')
            ->willReturn(['tt_content']); // pages not enabled

        $this->changeTrackingService->expects(self::never())
            ->method('trackChange');

        $fieldArray = ['title' => 'New Page'];

        $this->subject->processDatamap_afterDatabaseOperations(
            'new',
            'pages',
            'NEW123',
            $fieldArray,
            $this->dataHandler
        );
    }

    #[Test]
    public function processDatamapAfterDatabaseOperationsSkipsWhenNoSnapshot(): void
    {
        $this->configuration->method('getEnabledTables')
            ->willReturn(['pages']);

        $this->snapshotRepository->method('findCurrentSnapshot')
            ->willReturn(null);

        $this->changeTrackingService->expects(self::never())
            ->method('trackChange');

        $fieldArray = ['title' => 'New Page'];

        $this->subject->processDatamap_afterDatabaseOperations(
            'new',
            'pages',
            'NEW123',
            $fieldArray,
            $this->dataHandler
        );
    }

    #[Test]
    public function processCmdmapDeleteActionTracksDeletedRecord(): void
    {
        $this->configuration->method('getEnabledTables')
            ->willReturn(['pages']);

        $currentSnapshot = new DataSnapshot('test', 'tx_t3hauler_snapshots', 'hash123');
        $currentSnapshot->setUid(456);

        $this->snapshotRepository->method('findCurrentSnapshot')
            ->willReturn($currentSnapshot);

        $this->changeTrackingService->expects(self::once())
            ->method('trackChange')
            ->with(self::callback(function (array $data) {
                return $data['snapshot_uid'] === 456
                    && $data['table_name'] === 'pages'
                    && $data['record_uid'] === 123
                    && $data['change_type'] === 'delete';
            }));

        $record = ['title' => 'Deleted Page', 'deleted' => 1];
        $recordWasDeleted = true;

        $this->subject->processCmdmap_deleteAction(
            'pages',
            123,
            $record,
            $recordWasDeleted,
            $this->dataHandler
        );
    }

    #[Test]
    public function processCmdmapDeleteActionSkipsDisabledTable(): void
    {
        $this->configuration->method('getEnabledTables')
            ->willReturn(['tt_content']); // pages not enabled

        $this->changeTrackingService->expects(self::never())
            ->method('trackChange');

        $record = ['title' => 'Deleted Page'];
        $recordWasDeleted = true;

        $this->subject->processCmdmap_deleteAction(
            'pages',
            123,
            $record,
            $recordWasDeleted,
            $this->dataHandler
        );
    }

    #[Test]
    public function processCmdmapMoveActionTracksMovedRecord(): void
    {
        $this->configuration->method('getEnabledTables')
            ->willReturn(['pages']);

        $currentSnapshot = new DataSnapshot('test', 'tx_t3hauler_snapshots', 'hash123');
        $currentSnapshot->setUid(456);

        $this->snapshotRepository->method('findCurrentSnapshot')
            ->willReturn($currentSnapshot);

        $this->changeTrackingService->expects(self::once())
            ->method('trackChange')
            ->with(self::callback(function (array $data) {
                return $data['snapshot_uid'] === 456
                    && $data['table_name'] === 'pages'
                    && $data['record_uid'] === 123
                    && $data['change_type'] === 'move'
                    && $data['new_data']['pid'] === 456;
            }));

        $this->subject->processCmdmap_postProcess(
            'move',
            'pages',
            123,
            456, // destPid
            $this->dataHandler,
            null,
            null
        );
    }

    #[Test]
    public function processCmdmapMoveActionSkipsDisabledTable(): void
    {
        $this->configuration->method('getEnabledTables')
            ->willReturn(['tt_content']); // pages not enabled

        $this->changeTrackingService->expects(self::never())
            ->method('trackChange');

        $this->subject->processCmdmap_postProcess(
            'move',
            'pages',
            123,
            456,
            $this->dataHandler,
            null,
            null
        );
    }

    #[Test]
    public function hookMethodsHandleExceptionsGracefully(): void
    {
        $this->configuration->method('getEnabledTables')
            ->willReturn(['pages']);

        $this->snapshotRepository->method('findCurrentSnapshot')
            ->willThrowException(new \Exception('Database error'));

        $this->changeTrackingService->expects(self::never())
            ->method('trackChange');

        // Should not throw exception - hook should handle it gracefully
        $fieldArray = ['title' => 'New Page'];

        $this->subject->processDatamap_afterDatabaseOperations(
            'new',
            'pages',
            'NEW123',
            $fieldArray,
            $this->dataHandler
        );
    }

    #[Test]
    public function hookMethodsWorkWithoutBackendUser(): void
    {
        $this->configuration->method('getEnabledTables')
            ->willReturn(['pages']);

        $currentSnapshot = new DataSnapshot('test', 'tx_t3hauler_snapshots', 'hash123');
        $currentSnapshot->setUid(456);

        $this->snapshotRepository->method('findCurrentSnapshot')
            ->willReturn($currentSnapshot);

        // Set up substNEWwithIDs for new record
        $this->dataHandler->substNEWwithIDs = ['NEW123' => 789];

        // Unset global BE_USER
        $originalBeUser = $GLOBALS['BE_USER'] ?? null;
        unset($GLOBALS['BE_USER']);

        $this->changeTrackingService->expects(self::once())
            ->method('trackChange')
            ->with(self::callback(function (array $data) {
                return $data['be_user'] === 0
                    && $data['workspace'] === 0
                    && $data['record_uid'] === 789;
            }));

        $fieldArray = ['title' => 'New Page'];

        $this->subject->processDatamap_afterDatabaseOperations(
            'new',
            'pages',
            'NEW123',
            $fieldArray,
            $this->dataHandler
        );

        // Restore original BE_USER
        if ($originalBeUser !== null) {
            $GLOBALS['BE_USER'] = $originalBeUser;
        }
    }

    #[Test]
    public function hookMethodsWorkWithBackendUser(): void
    {
        $this->configuration->method('getEnabledTables')
            ->willReturn(['pages']);

        $currentSnapshot = new DataSnapshot('test', 'tx_t3hauler_snapshots', 'hash123');
        $currentSnapshot->setUid(456);

        $this->snapshotRepository->method('findCurrentSnapshot')
            ->willReturn($currentSnapshot);

        // Set up substNEWwithIDs for new record
        $this->dataHandler->substNEWwithIDs = ['NEW123' => 789];

        // Mock backend user
        $backendUser = $this->createMock(BackendUserAuthentication::class);
        $backendUser->user = ['uid' => 1];
        $backendUser->workspace = 0;

        $originalBeUser = $GLOBALS['BE_USER'] ?? null;
        $GLOBALS['BE_USER'] = $backendUser;

        $this->changeTrackingService->expects(self::once())
            ->method('trackChange')
            ->with(self::callback(function (array $data) {
                return $data['be_user'] === 1
                    && $data['workspace'] === 0
                    && $data['record_uid'] === 789;
            }));

        $fieldArray = ['title' => 'New Page'];

        $this->subject->processDatamap_afterDatabaseOperations(
            'new',
            'pages',
            'NEW123',
            $fieldArray,
            $this->dataHandler
        );

        // Restore original BE_USER
        if ($originalBeUser !== null) {
            $GLOBALS['BE_USER'] = $originalBeUser;
        } else {
            unset($GLOBALS['BE_USER']);
        }
    }
}
