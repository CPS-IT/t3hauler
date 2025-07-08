<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Tests\Unit\Service;

use Cpsit\T3hauler\Configuration\T3HaulerConfiguration;
use Cpsit\T3hauler\Domain\Model\Migration;
use Cpsit\T3hauler\Domain\Repository\MigrationRepository;
use Cpsit\T3hauler\Service\ChangeDetectionService;
use Cpsit\T3hauler\Service\ExportService;
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->changeDetectionService = $this->createMock(ChangeDetectionService::class);
        $this->exportService = $this->createMock(ExportService::class);
        $this->migrationRepository = $this->createMock(MigrationRepository::class);
        $this->configuration = $this->createMock(T3HaulerConfiguration::class);
        $this->hashUtility = $this->createMock(HashUtility::class);

        $this->subject = new MigrationService(
            $this->changeDetectionService,
            $this->exportService,
            $this->migrationRepository,
            $this->configuration,
            $this->hashUtility
        );
    }

    #[Test]
    public function createMigrationReturnsFailureWhenNoChanges(): void
    {
        $this->changeDetectionService->expects(self::once())
            ->method('getChangesSummary')
            ->willReturn(['has_changes' => false]);

        $result = $this->subject->createMigration('Test migration', 'Test Author');

        self::assertFalse($result['success']);
        self::assertSame('No changes detected since last snapshot. Nothing to migrate.', $result['message']);
        self::assertNull($result['migration']);
    }

    #[Test]
    public function createMigrationThrowsExceptionWhenNoMigrationPaths(): void
    {
        $this->changeDetectionService->expects(self::once())
            ->method('getChangesSummary')
            ->willReturn(['has_changes' => true]);

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
        $changesSummary = [
            'has_changes' => true,
            'changed_tables' => ['pages', 'tt_content'],
            'unchanged_tables' => [],
            'no_baseline_tables' => [],
        ];

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
    public function createMigrationCreatesSuccessfully(): void
    {
        self::markTestSkipped('File system access is required for this test. This test should be a functional test.');
        $changesSummary = [
            'has_changes' => true,
            'changed_tables' => ['pages'],
            'unchanged_tables' => [],
            'no_baseline_tables' => [],
        ];

        $exportResult = [
            'success' => true,
            'message' => 'Export successful',
            'record_count' => 5,
        ];

        $this->changeDetectionService->expects(self::once())
            ->method('getChangesSummary')
            ->willReturn($changesSummary);

        $this->configuration->expects(self::once())
            ->method('getMigrationPaths')
            ->willReturn(['/tmp/migrations']);

        $this->configuration->expects(self::exactly(2))
            ->method('getEnabledTables')
            ->willReturn(['pages', 'tt_content']);

        $this->configuration->expects(self::exactly(2))
            ->method('getExcludedFields')
            ->willReturn(['tstamp', 'crdate']);

        $this->configuration->expects(self::exactly(2))
            ->method('getHashAlgorithm')
            ->willReturn('sha256');

        $this->hashUtility->expects(self::once())
            ->method('calculateMultiTableHash')
            ->willReturn('source_hash_123');

        $this->exportService->expects(self::once())
            ->method('exportChangedData')
            ->willReturn($exportResult);

        $savedMigration = new Migration(
            'T3H_123',
            'Test migration',
            'Test migration',
            'Test Author',
            'source_hash_123',
            'T3H_123.t3d'
        );

        $this->migrationRepository->expects(self::once())
            ->method('save')
            ->willReturn($savedMigration);

        $this->changeDetectionService->expects(self::once())
            ->method('createSnapshot')
            ->willReturn([]);

        $result = $this->subject->createMigration('Test migration', 'Test Author');

        self::assertTrue($result['success']);
        self::assertSame('Migration created successfully', $result['message']);
        self::assertInstanceOf(Migration::class, $result['migration']);
        self::assertArrayHasKey('files', $result);
        self::assertArrayHasKey('snapshots', $result);
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

        $result = $this->subject->validateMigration('T3H_123');

        self::assertFalse($result['valid']); // Files don't exist in test
        self::assertIsArray($result['issues']);
        self::assertNotEmpty($result['issues']);
        self::assertSame($migration, $result['migration']);
        self::assertArrayHasKey('files', $result);
    }
}
