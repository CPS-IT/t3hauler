<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Tests\Unit\Service;

use Cpsit\T3hauler\Configuration\T3HaulerConfiguration;
use Cpsit\T3hauler\Domain\Repository\DataSnapshotRepository;
use Cpsit\T3hauler\Service\ExportService;
use Cpsit\T3hauler\Service\FilesystemInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Unit tests for ExportService
 */
final class ExportServiceTest extends TestCase
{
    private ExportService $subject;
    /** @var T3HaulerConfiguration&MockObject */
    private T3HaulerConfiguration $configurationMock;
    /** @var ConnectionPool|MockObject */
    private $connectionPoolMock;
    /** @var DataSnapshotRepository&MockObject */
    private DataSnapshotRepository $dataSnapshotRepositoryMock;
    /** @var FilesystemInterface&MockObject */
    private FilesystemInterface $filesystemMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configurationMock = $this->createMock(T3HaulerConfiguration::class);
        $this->connectionPoolMock = $this->createMock(ConnectionPool::class);
        $this->dataSnapshotRepositoryMock = $this->createMock(DataSnapshotRepository::class);
        $this->filesystemMock = $this->createMock(FilesystemInterface::class);

        $this->subject = new ExportService(
            $this->configurationMock,
            $this->connectionPoolMock,
            $this->dataSnapshotRepositoryMock,
            $this->filesystemMock
        );
    }

    #[Test]
    public function exportChangedDataFailsWithEmptyChangedTables(): void
    {
        $result = $this->subject->exportChangedData([], 'output.json');

        self::assertFalse($result->isSuccess());
        self::assertEquals('No changed tables to export', $result->message);
        self::assertEquals(0, $result->recordCount);
    }

    #[Test]
    public function exportChangedDataHandlesExceptionGracefully(): void
    {
        $changedTables = ['pages'];
        $outputPath = '/path/to/export.json';

        $this->connectionPoolMock->method('getConnectionForTable')
            ->willThrowException(new \RuntimeException('Database connection failed'));

        $result = $this->subject->exportChangedData($changedTables, $outputPath);

        self::assertFalse($result->isSuccess());
        self::assertStringStartsWith('Export failed:', $result->message);
        self::assertEquals(0, $result->recordCount);
        self::assertEquals('RuntimeException', $result->exception);
        self::assertEquals(0, $result->exceptionCode);
    }

    #[Test]
    public function exportChangedDataHandlesTableWithoutSnapshot(): void
    {
        $changedTables = ['pages'];
        $outputPath = '/path/to/export.json';

        // Fix configuration mock to return an array
        $this->configurationMock->method('get')
            ->willReturnCallback(function ($key, $default = null) {
                return match ($key) {
                    'detection.excludeFields' => [],
                    't3hauler.export.includeHidden' => false,
                    't3hauler.export.includeDeleted' => false,
                    default => $default
                };
            });

        $this->dataSnapshotRepositoryMock->method('findLatestByTableName')
            ->with('pages')
            ->willReturn(null);

        $result = $this->subject->exportChangedData($changedTables, $outputPath);

        self::assertFalse($result->isSuccess());
        self::assertEquals('No records found to export from changed tables', $result->message);
    }

    #[Test]
    public function exportChangedDataFailsWhenExportGeneratesNoData(): void
    {
        $changedTables = ['pages'];
        $outputPath = '/path/to/empty_export.json';

        // Set up mocks that return empty export data
        $this->setupEmptyExportMocks();

        $result = $this->subject->exportChangedData($changedTables, $outputPath);

        self::assertFalse($result->isSuccess());
        self::assertEquals('No records found to export from changed tables', $result->message);
        self::assertEquals(0, $result->recordCount);
    }

    #[Test]
    public function exportChangedDataWithInvalidPath(): void
    {
        $changedTables = ['pages'];
        $outputPath = '/invalid/directory/path/that/does/not/exist/export.json';

        // Mock configuration to return empty array
        $this->configurationMock->method('get')
            ->willReturnCallback(function ($key, $default = null) {
                return match ($key) {
                    'detection.excludeFields' => [],
                    't3hauler.export.includeHidden' => false,
                    't3hauler.export.includeDeleted' => false,
                    default => $default
                };
            });

        $result = $this->subject->exportChangedData($changedTables, $outputPath);

        self::assertFalse($result->isSuccess());
        self::assertEquals(0, $result->recordCount);
        self::assertTrue(
            str_starts_with($result->message, 'Failed to create output directory:') ||
            str_starts_with($result->message, 'No records found to export from changed tables') ||
            str_starts_with($result->message, 'Export failed:')
        );
    }

    #[Test]
    public function exportChangedDataHandlesNullAndEmptyValues(): void
    {
        $changedTables = [];
        $outputPath = '';

        $result = $this->subject->exportChangedData($changedTables, $outputPath);

        self::assertFalse($result->isSuccess());
        self::assertEquals('No changed tables to export', $result->message);
        self::assertEquals(0, $result->recordCount);
    }

    /**
     * @return array<string, array{array<string>, string}>
     */
    public static function exceptionDataProvider(): array
    {
        return [
            'runtime_exception' => [['pages'], 'RuntimeException'],
            'invalid_argument' => [['pages'], 'InvalidArgumentException'],
            'logic_exception' => [['pages'], 'LogicException'],
        ];
    }

    #[Test]
    #[DataProvider('exceptionDataProvider')]
    public function exportChangedDataHandlesVariousExceptions(array $tables, string $exceptionClass): void
    {
        $outputPath = '/path/to/exception_test.json';

        $this->connectionPoolMock->method('getConnectionForTable')
            ->willThrowException(new $exceptionClass('Test exception'));

        $result = $this->subject->exportChangedData($tables, $outputPath);

        self::assertFalse($result->isSuccess());
        self::assertStringStartsWith('Export failed:', $result->message);
        self::assertEquals(0, $result->recordCount);
        self::assertIsString($result->exception);
    }

    /**
     * @return array<string, array{array<int, string>}>
     */
    public static function tableNamesDataProvider(): array
    {
        return [
            'single_table' => [['pages']],
            'multiple_tables' => [['pages', 'tt_content', 'sys_file']],
            'typo3_core_tables' => [['pages', 'tt_content', 'sys_file', 'be_users', 'fe_users']],
            'custom_tables' => [['tx_myext_domain_model_item', 'tx_news_domain_model_news']],
            'mixed_tables' => [['pages', 'tx_myext_table', 'sys_file_reference']],
        ];
    }

    #[Test]
    #[DataProvider('tableNamesDataProvider')]
    public function exportChangedDataHandlesVariousTableNames(array $tableNames): void
    {
        $outputPath = '/path/to/tables_test.json';

        // Set up mocks for no snapshot found (will return early)
        $this->setupEmptyExportMocks();

        $result = $this->subject->exportChangedData($tableNames, $outputPath);

        self::assertFalse($result->isSuccess());
        self::assertEquals('No records found to export from changed tables', $result->message);
        self::assertEquals(0, $result->recordCount);
    }

    private function setupEmptyExportMocks(): void
    {
        $this->configurationMock->method('get')
            ->willReturnCallback(function ($key, $default = null) {
                return match ($key) {
                    'detection.excludeFields' => [],
                    't3hauler.export.includeHidden' => false,
                    't3hauler.export.includeDeleted' => false,
                    default => $default
                };
            });

        // Mock no snapshot found
        $this->dataSnapshotRepositoryMock->method('findLatestByTableName')
            ->willReturn(null);
    }
}
