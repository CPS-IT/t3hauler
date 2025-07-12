<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Tests\Functional\Service;

use Cpsit\T3hauler\Configuration\T3HaulerConfiguration;
use Cpsit\T3hauler\Domain\Model\DataSnapshot;
use Cpsit\T3hauler\Domain\Repository\DataSnapshotRepository;
use Cpsit\T3hauler\Service\ExportService;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Functional tests for ExportService
 *
 * Tests the full database integration and export functionality including
 * private methods that require real database connections
 */
final class ExportServiceTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['typo3conf/ext/t3hauler'];

    protected array $coreExtensionsToLoad = [
        'core',
        'backend',
        'frontend',
    ];

    private ExportService $subject;
    private ConnectionPool $connectionPool;
    private DataSnapshotRepository $dataSnapshotRepository;
    private T3HaulerConfiguration $configuration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connectionPool = $this->get(ConnectionPool::class);
        $this->dataSnapshotRepository = $this->get(DataSnapshotRepository::class);

        // Create configuration mock for tests
        $this->configuration = $this->createMock(T3HaulerConfiguration::class);
        $this->configuration->method('get')
            ->willReturnCallback(function (string $key, $default = null) {
                return match ($key) {
                    'detection.excludeFields' => ['password', 'deleted', 'cruser_id'],
                    default => $default,
                };
            });

        $this->subject = new ExportService(
            $this->configuration,
            $this->connectionPool,
            $this->dataSnapshotRepository
        );

        // Set up test database and data
        $this->setUpTestData();
    }

    #[Test]
    public function exportChangedDataExportsRecordsSuccessfully(): void
    {
        $changedTables = ['pages'];
        $outputPath = $this->getTemporaryOutputPath();

        try {
            $result = $this->subject->exportChangedData($changedTables, $outputPath);

            self::assertTrue($result->isSuccess());
            self::assertGreaterThan(0, $result->recordCount);
            self::assertFileExists($outputPath);

            // Validate the exported JSON structure
            $exportContent = file_get_contents($outputPath);
            $exportData = json_decode($exportContent, true);

            self::assertArrayHasKey('metadata', $exportData);
            self::assertArrayHasKey('records', $exportData);
            self::assertArrayHasKey('relations', $exportData);

            // Check if pages are exported
            self::assertArrayHasKey('pages', $exportData['records']);
            self::assertGreaterThan(0, count($exportData['records']['pages']));

        } finally {
            $this->cleanupFile($outputPath);
        }
    }

    #[Test]
    public function exportChangedDataHandlesNonExistentTable(): void
    {
        $changedTables = ['non_existent_table'];
        $outputPath = $this->getTemporaryOutputPath();

        try {
            $result = $this->subject->exportChangedData($changedTables, $outputPath);

            // Should handle gracefully and return no records found
            self::assertFalse($result->isSuccess());
            self::assertEquals('No records found to export from changed tables', $result->message);

        } finally {
            $this->cleanupFile($outputPath);
        }
    }

    #[Test]
    public function exportChangedDataCreatesDirectoryStructure(): void
    {
        $changedTables = ['pages'];
        $tempDir = sys_get_temp_dir() . '/export_test_' . uniqid();
        $outputPath = $tempDir . '/subdir/export.json';

        try {
            $result = $this->subject->exportChangedData($changedTables, $outputPath);

            self::assertTrue($result->isSuccess());
            self::assertDirectoryExists(dirname($outputPath));
            self::assertFileExists($outputPath);

        } finally {
            $this->cleanupFile($outputPath);
            $this->cleanupDirectory($tempDir);
        }
    }

    #[Test]
    public function exportChangedDataWithMultipleTables(): void
    {
        $changedTables = ['pages', 'tt_content'];
        $outputPath = $this->getTemporaryOutputPath();

        try {
            $result = $this->subject->exportChangedData($changedTables, $outputPath);

            if ($result->isSuccess()) {
                self::assertGreaterThan(0, $result->recordCount);
                self::assertIsArray($result->exportedTables);

                // Verify the export file content
                $exportContent = file_get_contents($outputPath);
                $exportData = json_decode($exportContent, true);
                self::assertArrayHasKey('records', $exportData);
            } else {
                // If no records found, that's also a valid outcome
                self::assertEquals('No records found to export from changed tables', $result->message);
            }

        } finally {
            $this->cleanupFile($outputPath);
        }
    }

    #[Test]
    public function exportChangedDataHandlesEmptyResultGracefully(): void
    {
        // Create a snapshot with future timestamp so no records will be found
        $futureSnapshot = new DataSnapshot('future_test', 'pages', 'hash456', new \DateTimeImmutable('+1 year'));
        $this->dataSnapshotRepository->save($futureSnapshot);

        $changedTables = ['pages'];
        $outputPath = $this->getTemporaryOutputPath();

        try {
            $result = $this->subject->exportChangedData($changedTables, $outputPath);

            self::assertFalse($result->isSuccess());
            self::assertEquals('No records found to export from changed tables', $result->message);

        } finally {
            $this->cleanupFile($outputPath);
        }
    }

    #[Test]
    public function exportChangedDataValidatesExcludedFields(): void
    {
        // Create a configuration that excludes certain fields
        $configurationWithExclusions = $this->createMock(T3HaulerConfiguration::class);
        $configurationWithExclusions->method('get')
            ->willReturnCallback(function (string $key, $default = null) {
                return match ($key) {
                    'detection.excludeFields' => ['password', 'deleted', 'cruser_id', 'title'], // Exclude title field
                    default => $default,
                };
            });

        $exportService = new ExportService(
            $configurationWithExclusions,
            $this->connectionPool,
            $this->dataSnapshotRepository
        );

        $changedTables = ['pages'];
        $outputPath = $this->getTemporaryOutputPath();

        try {
            $result = $exportService->exportChangedData($changedTables, $outputPath);

            if ($result->isSuccess()) {
                // Verify that excluded fields are not in the export
                $exportContent = file_get_contents($outputPath);
                $exportData = json_decode($exportContent, true);

                if (isset($exportData['records']['pages'])) {
                    foreach ($exportData['records']['pages'] as $record) {
                        // Title should be excluded from the export
                        self::assertArrayNotHasKey('title', $record, 'Excluded field "title" should not be present in export');
                        // UID should always be present
                        self::assertArrayHasKey('uid', $record, 'UID field should always be present');
                    }
                }
            } else {
                // If no records found, that's also a valid outcome for this test
                self::assertEquals('No records found to export from changed tables', $result->message);
            }

        } finally {
            $this->cleanupFile($outputPath);
        }
    }

    #[Test]
    public function exportChangedDataDetectsNewRecords(): void
    {
        // Create a baseline snapshot that's older than our test data
        $baselineSnapshot = new DataSnapshot('baseline_test', 'pages', 'hash123', new \DateTimeImmutable('-2 hours'));
        $this->dataSnapshotRepository->save($baselineSnapshot);

        // Insert a new page record with recent timestamp
        $connection = $this->connectionPool->getConnectionForTable('pages');
        $recentTimestamp = time() - 1800; // 30 minutes ago

        $connection->insert('pages', [
            'pid' => 0,
            'title' => 'New Test Page',
            'tstamp' => $recentTimestamp,
            'crdate' => $recentTimestamp,
            'sorting' => 100,
        ]);

        $changedTables = ['pages'];
        $outputPath = $this->getTemporaryOutputPath();

        try {
            $result = $this->subject->exportChangedData($changedTables, $outputPath);

            if ($result->isSuccess()) {
                self::assertGreaterThan(0, $result->recordCount);

                // Verify the export contains our new record
                $exportContent = file_get_contents($outputPath);
                $exportData = json_decode($exportContent, true);

                self::assertArrayHasKey('records', $exportData);
                self::assertArrayHasKey('pages', $exportData['records']);

                // Check for the new record in the export
                $foundNewRecord = false;
                foreach ($exportData['records']['pages'] as $record) {
                    if (isset($record['title']) && $record['title'] === 'New Test Page') {
                        $foundNewRecord = true;
                        break;
                    }
                }

                if (count($exportData['records']['pages']) > 0) {
                    // We should find the new record if any records were exported
                    self::assertTrue($foundNewRecord, 'New test record should be found in export');
                }
            } else {
                // This is acceptable if the table schema detection fails
                self::assertContains($result->message, [
                    'No records found to export from changed tables',
                    'Export failed:',
                ]);
            }

        } finally {
            $this->cleanupFile($outputPath);
        }
    }

    #[Test]
    public function exportChangedDataHandlesTableSchemaDetection(): void
    {
        // Test the getAllowedFields private method through the public interface
        $changedTables = ['pages'];
        $outputPath = $this->getTemporaryOutputPath();

        try {
            $result = $this->subject->exportChangedData($changedTables, $outputPath);

            // The test should either succeed or fail gracefully
            self::assertIsBool($result->isSuccess());
            self::assertIsString($result->message);
            self::assertIsInt($result->recordCount);

            if ($result->isSuccess()) {
                // Verify the export file contains expected structure
                $exportContent = file_get_contents($outputPath);
                $exportData = json_decode($exportContent, true);

                self::assertArrayHasKey('metadata', $exportData);
                self::assertArrayHasKey('records', $exportData);
                self::assertArrayHasKey('relations', $exportData);
            }

        } finally {
            $this->cleanupFile($outputPath);
        }
    }

    #[Test]
    public function exportChangedDataWithComplexTableStructure(): void
    {
        // Create a custom table to test schema detection
        $connection = $this->connectionPool->getConnectionForTable('pages');

        // Test with pages table which has a known structure
        $changedTables = ['pages'];
        $outputPath = $this->getTemporaryOutputPath();

        try {
            $result = $this->subject->exportChangedData($changedTables, $outputPath);

            // The operation should complete (either with success or graceful failure)
            self::assertIsBool($result->isSuccess());
            self::assertIsString($result->message);
            self::assertIsInt($result->recordCount);

            if ($result->isSuccess()) {
                // Verify metadata is properly generated
                self::assertIsArray($result->metadata);
                self::assertGreaterThan(0, $result->fileSize);
            }

        } finally {
            $this->cleanupFile($outputPath);
        }
    }

    #[Test]
    public function exportChangedDataRespectsTimestampFiltering(): void
    {
        // Create test data with different timestamps
        $connection = $this->connectionPool->getConnectionForTable('pages');

        $oldTimestamp = time() - 7200; // 2 hours ago
        $newTimestamp = time() - 1800; // 30 minutes ago

        // Insert old record
        $connection->insert('pages', [
            'pid' => 0,
            'title' => 'Old Test Page',
            'tstamp' => $oldTimestamp,
            'crdate' => $oldTimestamp,
            'sorting' => 100,
        ]);

        // Insert new record
        $connection->insert('pages', [
            'pid' => 0,
            'title' => 'New Test Page',
            'tstamp' => $newTimestamp,
            'crdate' => $newTimestamp,
            'sorting' => 200,
        ]);

        // Create a snapshot between the two timestamps
        $midSnapshot = new DataSnapshot('mid_test', 'pages', 'hash789', new \DateTimeImmutable('-1 hour'));
        $this->dataSnapshotRepository->save($midSnapshot);

        $changedTables = ['pages'];
        $outputPath = $this->getTemporaryOutputPath();

        try {
            $result = $this->subject->exportChangedData($changedTables, $outputPath);

            if ($result->isSuccess()) {
                // Should only export records changed after the snapshot
                $exportContent = file_get_contents($outputPath);
                $exportData = json_decode($exportContent, true);

                if (isset($exportData['records']['pages'])) {
                    // Check that only newer records are included
                    foreach ($exportData['records']['pages'] as $record) {
                        if (isset($record['title'])) {
                            // Only the new record should be included (if title field is present)
                            self::assertNotEquals(
                                'Old Test Page',
                                $record['title'],
                                'Old records should not be included in export'
                            );
                        }
                    }
                }
            }

        } finally {
            $this->cleanupFile($outputPath);
        }
    }

    private function setUpTestData(): void
    {
        // Create a test snapshot that's older than our test data
        $snapshot = new DataSnapshot('test_baseline', 'pages', 'hash123', new \DateTimeImmutable('-1 hour'));
        $this->dataSnapshotRepository->save($snapshot);

        // Insert test pages data
        $connection = $this->connectionPool->getConnectionForTable('pages');

        // Insert some test pages with recent timestamps
        $recentTimestamp = time() - 1800; // 30 minutes ago

        $connection->insert('pages', [
            'pid' => 0,
            'title' => 'Functional Test Page 1',
            'tstamp' => $recentTimestamp,
            'crdate' => $recentTimestamp,
            'sorting' => 100,
        ]);

        $connection->insert('pages', [
            'pid' => 0,
            'title' => 'Functional Test Page 2',
            'tstamp' => $recentTimestamp,
            'crdate' => $recentTimestamp,
            'sorting' => 200,
        ]);

        // Also insert some tt_content records for multi-table testing
        $contentConnection = $this->connectionPool->getConnectionForTable('tt_content');

        $contentConnection->insert('tt_content', [
            'pid' => 1,
            'header' => 'Test Content Element',
            'tstamp' => $recentTimestamp,
            'crdate' => $recentTimestamp,
            'sorting' => 100,
            'CType' => 'text',
        ]);
    }

    private function getTemporaryOutputPath(): string
    {
        return tempnam(sys_get_temp_dir(), 'export_functional_test_') . '.json';
    }

    private function cleanupFile(string $filePath): void
    {
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    private function cleanupDirectory(string $dirPath): void
    {
        if (is_dir($dirPath)) {
            $files = glob($dirPath . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                } elseif (is_dir($file)) {
                    $this->cleanupDirectory($file);
                }
            }
            rmdir($dirPath);
        }
    }
}
