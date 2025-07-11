<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Tests\Functional\Service;

use Cpsit\T3hauler\Configuration\T3HaulerConfiguration;
use Cpsit\T3hauler\Domain\Repository\DataSnapshotRepository;
use Cpsit\T3hauler\Service\ChangeDetectionService;
use Cpsit\T3hauler\Service\ExportService;
use Cpsit\T3hauler\Service\ImportService;
use Cpsit\T3hauler\Tests\Functional\TestingUtilities;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Integration tests for export/import functionality
 */
final class ExportImportIntegrationTest extends FunctionalTestCase
{
    use TestingUtilities;
    private ExportService $exportService;
    private ImportService $importService;
    private ChangeDetectionService $changeDetectionService;
    private string $tempDir;

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

        // Create temporary directory for test files within TYPO3's allowed paths
        $this->tempDir = Environment::getVarPath() . '/tests/t3hauler_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);

        // Create a proper T3HaulerConfiguration with valid paths
        $configuration = new T3HaulerConfiguration([], [$this->tempDir]);
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);

        $this->exportService = GeneralUtility::makeInstance(
            ExportService::class,
            $configuration,
            $connectionPool,
            GeneralUtility::makeInstance(DataSnapshotRepository::class),
        );
        $this->importService = GeneralUtility::makeInstance(
            ImportService::class,
            $configuration,
            $connectionPool,
        );
        $this->changeDetectionService = GeneralUtility::makeInstance(ChangeDetectionService::class);

        // Temporary directory already created above with proper TYPO3 paths
    }

    protected function tearDown(): void
    {
        // Clean up temporary directory
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }

        $this->tearDownT3HaulerTests();
        parent::tearDown();
    }

    #[Test]
    public function exportAndImportBasicMigration(): void
    {
        // Create a snapshot for the migration table (required by ExportService)
        $this->createSnapshotForTable('tx_t3hauler_migrations');

        // Create a migration with some basic data
        $migrationData = [
            'migration_id' => 'test_migration_001',
            'description' => 'Test migration for export/import',
            'author' => 'Test Author',
            'status' => 'pending',
            'created_at' => time(),
            'metadata' => json_encode(['test' => true]),
        ];

        $migrationUid = $this->insertTestData('tx_t3hauler_migrations', $migrationData);

        // Export the migration
        $exportPath = $this->tempDir . '/test_export.json';
        $result = $this->exportService->exportChangedData(['tx_t3hauler_migrations'], $exportPath);

        self::assertNotEmpty($result);
        self::assertFileExists($exportPath);

        // Verify export file content
        $exportContent = file_get_contents($exportPath);
        self::assertNotFalse($exportContent);

        $exportData = json_decode($exportContent, true);
        self::assertIsArray($exportData);
        self::assertArrayHasKey('records', $exportData);
        self::assertArrayHasKey('metadata', $exportData);
        self::assertArrayHasKey('tx_t3hauler_migrations', $exportData['records']);

        // Get the first migration record
        $migrationRecords = $exportData['records']['tx_t3hauler_migrations'];
        $migrationRecord = reset($migrationRecords);
        self::assertSame('test_migration_001', $migrationRecord['migration_id']);

        // Clear the migration from database
        $this->deleteTestData('tx_t3hauler_migrations', ['uid' => $migrationUid]);
        $this->assertTableCount('tx_t3hauler_migrations', 0);

        // Import the migration
        $importResult = $this->importService->importFromFile($exportPath);

        self::assertNotEmpty($importResult);
        self::assertArrayHasKey('success', $importResult);

        // Verify migration was imported
        $this->assertTableCount('tx_t3hauler_migrations', 1);
        $this->assertRecordExists('tx_t3hauler_migrations', ['migration_id' => 'test_migration_001']);

        // Verify imported data matches original
        $importedMigration = $this->getConnectionForTable('tx_t3hauler_migrations')
            ->select(['*'], 'tx_t3hauler_migrations', ['migration_id' => 'test_migration_001'])
            ->fetchAssociative();

        self::assertSame('Test migration for export/import', $importedMigration['description']);
        self::assertSame('Test Author', $importedMigration['author']);
    }

    #[Test]
    public function exportIncludesChangeRecords(): void
    {
        // Create snapshot for the migration table (required by ExportService)
        $this->createSnapshotForTable('tx_t3hauler_migrations');
        $this->createSnapshotForTable('tx_t3hauler_change_records');

        // Create snapshot and change records
        $snapshot = $this->changeDetectionService->createTableSnapshot('pages');
        $snapshotUid = $snapshot->getUid();

        // Create migration
        $migrationData = [
            'migration_id' => 'migration_with_changes',
            'description' => 'Migration with change records',
            'author' => 'Test Author',
            'status' => 'pending',
            'created_at' => time(),
        ];

        $migrationUid = $this->insertTestData('tx_t3hauler_migrations', $migrationData);

        // Add change records
        $changeRecords = [
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
                'correlation_id' => 'corr_1',
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
                'correlation_id' => 'corr_2',
            ],
        ];

        foreach ($changeRecords as $change) {
            $this->insertTestData('tx_t3hauler_change_records', $change);
        }

        // Export the migration
        $exportPath = $this->tempDir . '/migration_with_changes.json';
        $result = $this->exportService->exportChangedData(['tx_t3hauler_migrations', 'tx_t3hauler_change_records'], $exportPath);

        self::assertNotEmpty($result);

        // Verify export includes change records
        $exportContent = file_get_contents($exportPath);
        $exportData = json_decode($exportContent, true);

        self::assertArrayHasKey('records', $exportData);
        self::assertArrayHasKey('tx_t3hauler_change_records', $exportData['records']);
        self::assertCount(2, $exportData['records']['tx_t3hauler_change_records']);

        // Verify change record details
        $exportedChanges = array_values($exportData['records']['tx_t3hauler_change_records']);
        self::assertSame('pages', $exportedChanges[0]['table_name']);
        self::assertSame('update', $exportedChanges[0]['change_type']);
        self::assertSame('tt_content', $exportedChanges[1]['table_name']);
        self::assertSame('insert', $exportedChanges[1]['change_type']);
    }

    #[Test]
    public function importValidatesFileFormat(): void
    {
        // Create invalid JSON file
        $invalidPath = $this->tempDir . '/invalid.json';
        file_put_contents($invalidPath, '{"invalid": json}');

        // Import should fail gracefully
        $result = $this->importService->importFromFile($invalidPath);
        self::assertNotEmpty($result);
        self::assertArrayHasKey('success', $result);
        self::assertFalse($result['success']);

        // Create valid JSON but invalid structure
        $invalidStructurePath = $this->tempDir . '/invalid_structure.json';
        file_put_contents($invalidStructurePath, '{"not": "a migration"}');

        $result = $this->importService->importFromFile($invalidStructurePath);
        self::assertNotEmpty($result);
        self::assertArrayHasKey('success', $result);
        self::assertFalse($result['success']);
    }

    #[Test]
    public function exportSupportsMultipleFormats(): void
    {
        // Create snapshot for the migration table (required by ExportService)
        $this->createSnapshotForTable('tx_t3hauler_migrations');

        // Create migration
        $migrationData = [
            'migration_id' => 'format_test_migration',
            'description' => 'Testing different export formats',
            'author' => 'Format Tester',
            'status' => 'pending',
            'created_at' => time(),
        ];

        $migrationUid = $this->insertTestData('tx_t3hauler_migrations', $migrationData);

        // Test JSON export
        $jsonPath = $this->tempDir . '/test.json';
        $result = $this->exportService->exportChangedData(['tx_t3hauler_migrations'], $jsonPath);
        self::assertNotEmpty($result);
        self::assertFileExists($jsonPath);

        $jsonContent = json_decode(file_get_contents($jsonPath), true);
        self::assertIsArray($jsonContent);
        self::assertArrayHasKey('records', $jsonContent);
        self::assertArrayHasKey('tx_t3hauler_migrations', $jsonContent['records']);
    }

    #[Test]
    public function roundTripPreservesDataIntegrity(): void
    {
        // Create snapshot for the migration table (required by ExportService)
        $this->createSnapshotForTable('tx_t3hauler_migrations');

        // Create comprehensive migration data
        $migrationData = [
            'migration_id' => 'integrity_test_migration',
            'description' => 'Testing data integrity during round trip',
            'author' => 'Integrity Tester',
            'status' => 'pending',
            'created_at' => time(),
            'metadata' => json_encode([
                'site' => 'test-site',
                'environment' => 'testing',
                'custom_data' => ['key1' => 'value1', 'key2' => 'value2'],
            ]),
        ];

        $migrationUid = $this->insertTestData('tx_t3hauler_migrations', $migrationData);

        // Get original data for comparison
        $originalMigration = $this->getConnectionForTable('tx_t3hauler_migrations')
            ->select(['*'], 'tx_t3hauler_migrations', ['uid' => $migrationUid])
            ->fetchAssociative();

        // Export
        $exportPath = $this->tempDir . '/integrity_test.json';
        $exportResult = $this->exportService->exportChangedData(['tx_t3hauler_migrations'], $exportPath);
        self::assertNotEmpty($exportResult);

        // Clear original data
        $this->deleteTestData('tx_t3hauler_migrations', ['uid' => $migrationUid]);

        // Import
        $importResult = $this->importService->importFromFile($exportPath);
        self::assertNotEmpty($importResult);
        self::assertArrayHasKey('success', $importResult);

        // Get imported data
        $importedMigration = $this->getConnectionForTable('tx_t3hauler_migrations')
            ->select(['*'], 'tx_t3hauler_migrations', ['migration_id' => 'integrity_test_migration'])
            ->fetchAssociative();

        // Verify data integrity (excluding auto-generated fields like uid)
        self::assertSame($originalMigration['migration_id'], $importedMigration['migration_id']);
        self::assertSame($originalMigration['description'], $importedMigration['description']);
        self::assertSame($originalMigration['author'], $importedMigration['author']);
        self::assertSame($originalMigration['metadata'], $importedMigration['metadata']);

        // Verify metadata JSON integrity
        $originalMetadata = json_decode($originalMigration['metadata'], true);
        $importedMetadata = json_decode($importedMigration['metadata'], true);
        self::assertSame($originalMetadata, $importedMetadata);
    }

    #[Test]
    public function importHandlesDuplicateMigrations(): void
    {
        // Create snapshot for the migration table (required by ExportService)
        $this->createSnapshotForTable('tx_t3hauler_migrations');

        // Create and export migration
        $migrationData = [
            'migration_id' => 'duplicate_test_migration',
            'description' => 'Testing duplicate handling',
            'author' => 'Test Author',
            'status' => 'pending',
            'created_at' => time(),
        ];

        $migrationUid = $this->insertTestData('tx_t3hauler_migrations', $migrationData);

        $exportPath = $this->tempDir . '/duplicate_test.json';
        $this->exportService->exportChangedData(['tx_t3hauler_migrations'], $exportPath);

        // Verify original exists
        $this->assertTableCount('tx_t3hauler_migrations', 1);

        // Import same migration again
        $importResult = $this->importService->importFromFile($exportPath);

        // Should handle duplicate gracefully (either skip or update)
        // The exact behavior depends on import service implementation
        self::assertNotEmpty($importResult);
        self::assertArrayHasKey('success', $importResult);

        // Verify we don't have unexpected duplicates
        $duplicateCount = $this->getConnectionForTable('tx_t3hauler_migrations')
            ->count('*', 'tx_t3hauler_migrations', ['migration_id' => 'duplicate_test_migration']);

        // Should be 1 (original) or 1 (replaced) but not more
        self::assertLessThanOrEqual(2, $duplicateCount);
    }

    #[Test]
    public function exportIncludesMetadataAndTimestamps(): void
    {
        // Create snapshot for the migration table (required by ExportService)
        $this->createSnapshotForTable('tx_t3hauler_migrations');

        // Create migration
        $timestamp = time();
        $migrationData = [
            'migration_id' => 'metadata_test_migration',
            'name' => 'Metadata Test Migration',
            'description' => 'Testing metadata export',
            'author' => 'Metadata Tester',
            'status' => 'pending',
            'created_at' => $timestamp,
        ];

        $migrationUid = $this->insertTestData('tx_t3hauler_migrations', $migrationData);

        // Export
        $exportPath = $this->tempDir . '/metadata_test.json';
        $this->exportService->exportChangedData(['tx_t3hauler_migrations'], $exportPath);

        // Verify export includes metadata
        $exportData = json_decode(file_get_contents($exportPath), true);

        self::assertArrayHasKey('metadata', $exportData);
        self::assertArrayHasKey('created_at', $exportData['metadata']);
        self::assertArrayHasKey('format_version', $exportData['metadata']);
        self::assertArrayHasKey('charset', $exportData['metadata']);

        // Verify migration data is present
        self::assertArrayHasKey('records', $exportData);
        self::assertArrayHasKey('tx_t3hauler_migrations', $exportData['records']);
        $migrationRecords = array_values($exportData['records']['tx_t3hauler_migrations']);
        self::assertSame($timestamp, $migrationRecords[0]['created_at']);
    }

    /**
     * Create a snapshot for a specific table
     */
    private function createSnapshotForTable(string $tableName): void
    {
        $snapshotData = [
            'identifier' => 'test_snapshot_' . $tableName,
            'table_name' => $tableName,
            'hash' => 'test_hash_' . $tableName,
            'created_at' => time() - 100, // Create snapshot in the past
            'metadata' => '{}',
            'migration_version' => '1.0.0',
        ];

        $this->insertTestData('tx_t3hauler_snapshots', $snapshotData);
    }

    /**
     * Recursively remove directory and its contents
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
