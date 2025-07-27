<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Tests\Unit\Service;

use Cpsit\T3hauler\Configuration\T3HaulerConfiguration;
use Cpsit\T3hauler\Domain\Dto\ChangesSummary;
use Cpsit\T3hauler\Domain\Model\Migration;
use Cpsit\T3hauler\Domain\Repository\MigrationRepository;
use Cpsit\T3hauler\Service\ChangeDetectionService;
use Cpsit\T3hauler\Service\ExportService;
use Cpsit\T3hauler\Service\FilesystemInterface;
use Cpsit\T3hauler\Service\MigrationService;
use Cpsit\T3hauler\Utility\HashUtility;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MigrationServiceTest extends TestCase
{
    private MigrationService $subject;
    /** @var ChangeDetectionService&\PHPUnit\Framework\MockObject\MockObject */
    private ChangeDetectionService $changeDetectionService;
    /** @var ExportService&\PHPUnit\Framework\MockObject\MockObject */
    private ExportService $exportService;
    /** @var MigrationRepository&\PHPUnit\Framework\MockObject\MockObject */
    private MigrationRepository $migrationRepository;
    /** @var T3HaulerConfiguration&\PHPUnit\Framework\MockObject\MockObject */
    private T3HaulerConfiguration $configuration;
    /** @var HashUtility&\PHPUnit\Framework\MockObject\MockObject */
    private HashUtility $hashUtility;
    /** @var FilesystemInterface&\PHPUnit\Framework\MockObject\MockObject */
    private FilesystemInterface $filesystem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->changeDetectionService = $this->createMock(ChangeDetectionService::class);
        $this->exportService = $this->createMock(ExportService::class);
        $this->migrationRepository = $this->createMock(MigrationRepository::class);
        $this->configuration = $this->createMock(T3HaulerConfiguration::class);
        $this->hashUtility = $this->createMock(HashUtility::class);
        $this->filesystem = $this->createMock(FilesystemInterface::class);

        $this->subject = new MigrationService(
            $this->changeDetectionService,
            $this->exportService,
            $this->migrationRepository,
            $this->configuration,
            $this->hashUtility,
            $this->filesystem
        );
    }

    #[Test]
    public function createMigrationReturnsFailureWhenNoChanges(): void
    {
        $changesSummary = new ChangesSummary(0, [], [], [], false);

        $this->changeDetectionService->expects(self::once())
            ->method('getChangesSummary')
            ->willReturn($changesSummary);

        $result = $this->subject->createMigration('Test migration', 'Test Author');

        self::assertFalse($result['success']);
        self::assertSame('No changes detected since last snapshot. Nothing to migrate.', $result['message']);
        self::assertNull($result['migration']);
    }

    #[Test]
    public function createMigrationThrowsExceptionWhenNoMigrationPaths(): void
    {
        $changesSummary = new ChangesSummary(1, ['pages'], [], [], true);

        $this->changeDetectionService->expects(self::once())
            ->method('getChangesSummary')
            ->willReturn($changesSummary);

        $this->configuration->expects(self::once())
            ->method('getMigrationPaths')
            ->willReturn([]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No migration paths configured');

        $this->subject->createMigration('Test migration', 'Test Author');
    }

    #[Test]
    public function createMigrationReturnsDryRunResult(): void
    {
        $changesSummary = new ChangesSummary(
            2,
            ['pages', 'tt_content'],
            [],
            [],
            true
        );

        $this->changeDetectionService->expects(self::once())
            ->method('getChangesSummary')
            ->willReturn($changesSummary);

        $this->configuration->expects(self::once())
            ->method('getMigrationPaths')
            ->willReturn(['/tmp/migrations']);

        $result = $this->subject->createMigration('Test migration', 'Test Author', 'zug', true);

        self::assertTrue($result['success']);
        self::assertSame('DRY RUN: Migration would be created', $result['message']);
        self::assertIsArray($result['migration']);
        self::assertArrayHasKey('migration_id', $result['migration']);
        self::assertSame('Test migration', $result['migration']['description']);
        self::assertSame('Test Author', $result['migration']['author']);
        self::assertSame('zug', $result['migration']['site']);
        self::assertSame($changesSummary, $result['migration']['changes']);
    }

    #[Test]
    public function createMigrationThrowsExceptionWhenDirectoryCreationFails(): void
    {
        $changesSummary = new ChangesSummary(1, ['pages'], [], [], true);

        $this->changeDetectionService->expects(self::once())
            ->method('getChangesSummary')
            ->willReturn($changesSummary);

        $this->configuration->expects(self::once())
            ->method('getMigrationPaths')
            ->willReturn(['/tmp/migrations']);

        $this->filesystem->expects(self::once())
            ->method('getAbsoluteFilePath')
            ->with('/tmp/migrations')
            ->willReturn('/tmp/migrations');

        $this->filesystem->expects(self::once())
            ->method('isDirectory')
            ->with('/tmp/migrations')
            ->willReturn(false);

        $this->filesystem->expects(self::once())
            ->method('createDirectory')
            ->with('/tmp/migrations')
            ->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to create migration directory');

        $this->subject->createMigration('Test migration', 'Test Author');
    }

    #[Test]
    public function createMigrationHandlesExportFailure(): void
    {
        $changesSummary = new ChangesSummary(1, ['pages'], [], [], true);

        $this->changeDetectionService->expects(self::once())
            ->method('getChangesSummary')
            ->willReturn($changesSummary);

        $this->configuration->expects(self::once())
            ->method('getMigrationPaths')
            ->willReturn(['/tmp/migrations']);

        $this->filesystem->expects(self::once())
            ->method('getAbsoluteFilePath')
            ->with('/tmp/migrations')
            ->willReturn('/tmp/migrations');

        $this->filesystem->expects(self::once())
            ->method('isDirectory')
            ->with('/tmp/migrations')
            ->willReturn(true);

        $this->configuration->expects(self::once())
            ->method('getEnabledTables')
            ->willReturn(['pages', 'tt_content']);

        $this->configuration->expects(self::once())
            ->method('getExcludedFields')
            ->willReturn(['tstamp', 'crdate']);

        $this->configuration->expects(self::once())
            ->method('getHashAlgorithm')
            ->willReturn('sha256');

        $this->hashUtility->expects(self::once())
            ->method('calculateMultiTableHash')
            ->willReturn('source_hash_123');

        $exportResult = \Cpsit\T3hauler\Domain\Dto\ExportResult::failure(
            \Cpsit\T3hauler\Domain\Enumeration\ExportStatus::EXPORT_FAILED,
            'Export failed'
        );

        $this->exportService->expects(self::once())
            ->method('exportChangedData')
            ->willReturn($exportResult);

        $this->filesystem->expects(self::once())
            ->method('exists')
            ->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to export data');

        $this->subject->createMigration('Test migration', 'Test Author');
    }

    #[Test]
    public function createMigrationCleansUpOnFailure(): void
    {
        $changesSummary = new ChangesSummary(1, ['pages'], [], [], true);

        $this->changeDetectionService->expects(self::once())
            ->method('getChangesSummary')
            ->willReturn($changesSummary);

        $this->configuration->expects(self::once())
            ->method('getMigrationPaths')
            ->willReturn(['/tmp/migrations']);

        $this->filesystem->expects(self::once())
            ->method('getAbsoluteFilePath')
            ->with('/tmp/migrations')
            ->willReturn('/tmp/migrations');

        $this->filesystem->expects(self::once())
            ->method('isDirectory')
            ->with('/tmp/migrations')
            ->willReturn(true);

        $this->configuration->expects(self::once())
            ->method('getEnabledTables')
            ->willReturn(['pages', 'tt_content']);

        $this->configuration->expects(self::once())
            ->method('getExcludedFields')
            ->willReturn(['tstamp', 'crdate']);

        $this->configuration->expects(self::once())
            ->method('getHashAlgorithm')
            ->willReturn('sha256');

        $this->hashUtility->expects(self::once())
            ->method('calculateMultiTableHash')
            ->willReturn('source_hash_123');

        $exportResult = \Cpsit\T3hauler\Domain\Dto\ExportResult::success(
            'Export successful',
            5,
            ['pages' => 3, 'tt_content' => 2],
            '/tmp/migrations/test.json',
            1024
        );

        $this->exportService->expects(self::once())
            ->method('exportChangedData')
            ->willReturn($exportResult);

        $this->migrationRepository->expects(self::once())
            ->method('save')
            ->willThrowException(new \RuntimeException('Database error'));

        // Cleanup should be called
        $this->filesystem->expects(self::once())
            ->method('exists')
            ->willReturn(true);

        $this->filesystem->expects(self::once())
            ->method('deleteFile')
            ->willReturn(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to create migration');

        $this->subject->createMigration('Test migration', 'Test Author');
    }

    #[Test]
    public function getMigrationCallsRepository(): void
    {
        $migration = new Migration('T3H_123', 'Test', 'Test', 'Author', 'hash', 'file.t3d');

        $this->migrationRepository->expects(self::once())
            ->method('findByMigrationId')
            ->with('T3H_123')
            ->willReturn($migration);

        $result = $this->subject->getMigration('T3H_123');

        self::assertSame($migration, $result);
    }

    #[Test]
    public function listMigrationsCallsRepository(): void
    {
        $migrations = [
            new Migration('T3H_123', 'Test1', 'Test1', 'Author', 'hash1', 'file1.t3d'),
            new Migration('T3H_456', 'Test2', 'Test2', 'Author', 'hash2', 'file2.t3d'),
        ];

        $this->migrationRepository->expects(self::once())
            ->method('findAll')
            ->willReturn($migrations);

        $result = $this->subject->listMigrations();

        self::assertSame($migrations, $result);
    }

    #[Test]
    public function getPendingMigrationsCallsRepository(): void
    {
        $migrations = [
            new Migration('T3H_123', 'Test', 'Test', 'Author', 'hash', 'file.t3d'),
        ];

        $this->migrationRepository->expects(self::once())
            ->method('findPending')
            ->willReturn($migrations);

        $result = $this->subject->getPendingMigrations();

        self::assertSame($migrations, $result);
    }

    #[Test]
    public function getAppliedMigrationsCallsRepository(): void
    {
        $migrations = [
            new Migration('T3H_123', 'Test', 'Test', 'Author', 'hash', 'file.t3d'),
        ];

        $this->migrationRepository->expects(self::once())
            ->method('findApplied')
            ->willReturn($migrations);

        $result = $this->subject->getAppliedMigrations();

        self::assertSame($migrations, $result);
    }

    #[Test]
    public function getStatisticsCallsRepository(): void
    {
        $stats = [
            'total' => 10,
            'pending' => 3,
            'applied' => 6,
            'failed' => 1,
            'rolled_back' => 0,
        ];

        $this->migrationRepository->expects(self::once())
            ->method('getSummary')
            ->willReturn($stats);

        $result = $this->subject->getStatistics();

        self::assertSame($stats, $result);
    }

    #[Test]
    public function validateMigrationReturnsFalseWhenNotFound(): void
    {
        $this->migrationRepository->expects(self::once())
            ->method('findByMigrationId')
            ->with('T3H_404')
            ->willReturn(null);

        $result = $this->subject->validateMigration('T3H_404');

        self::assertFalse($result['valid']);
        self::assertSame('Migration not found: T3H_404', $result['message']);
    }

    #[Test]
    public function validateMigrationChecksFiles(): void
    {
        $migration = new Migration('T3H_123', 'Test', 'Test', 'Author', 'hash', 'test.t3d');

        $this->migrationRepository->expects(self::once())
            ->method('findByMigrationId')
            ->with('T3H_123')
            ->willReturn($migration);

        $this->configuration->expects(self::once())
            ->method('getMigrationPaths')
            ->willReturn(['/tmp/migrations']);

        $this->filesystem->expects(self::exactly(2))
            ->method('exists')
            ->willReturnOnConsecutiveCalls(false, false);

        $result = $this->subject->validateMigration('T3H_123');

        self::assertFalse($result['valid']); // Files don't exist in test
        self::assertIsArray($result['issues']);
        self::assertNotEmpty($result['issues']);
        self::assertSame($migration, $result['migration']);
        self::assertArrayHasKey('files', $result);
    }

    #[Test]
    public function validateMigrationReturnsValidWhenFilesExist(): void
    {
        $migration = new Migration('T3H_123', 'Test', 'Test', 'Author', 'hash', 'test.json');

        $this->migrationRepository->expects(self::once())
            ->method('findByMigrationId')
            ->with('T3H_123')
            ->willReturn($migration);

        $this->configuration->expects(self::once())
            ->method('getMigrationPaths')
            ->willReturn(['/tmp/migrations']);

        $this->filesystem->expects(self::exactly(2))
            ->method('exists')
            ->willReturnOnConsecutiveCalls(true, true);

        $result = $this->subject->validateMigration('T3H_123');

        self::assertTrue($result['valid']);
        self::assertSame('Migration is valid', $result['message']);
        self::assertSame($migration, $result['migration']);
        self::assertArrayHasKey('files', $result);
        self::assertEmpty($result['issues']);
    }

    #[Test]
    public function validateMigrationHandlesEmptyMigrationPaths(): void
    {
        $migration = new Migration('T3H_123', 'Test', 'Test', 'Author', 'hash', 'test.json');

        $this->migrationRepository->expects(self::once())
            ->method('findByMigrationId')
            ->with('T3H_123')
            ->willReturn($migration);

        $this->configuration->expects(self::once())
            ->method('getMigrationPaths')
            ->willReturn([]);

        $this->filesystem->expects(self::exactly(2))
            ->method('exists')
            ->willReturnOnConsecutiveCalls(false, false);

        $result = $this->subject->validateMigration('T3H_123');

        self::assertFalse($result['valid']);
        self::assertIsArray($result['issues']);
        self::assertNotEmpty($result['issues']);
    }
}
