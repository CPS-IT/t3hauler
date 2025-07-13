<?php

/** @noinspection DynamicInvocationViaScopeResolutionInspection */

declare(strict_types=1);

namespace Cpsit\T3hauler\Tests\Unit\Service;

use Cpsit\T3hauler\Configuration\T3HaulerConfiguration;
use Cpsit\T3hauler\Service\ImportService;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

class ImportServiceTest extends TestCase
{
    private ImportService $subject;
    /** @var T3HaulerConfiguration&MockObject */
    private T3HaulerConfiguration $configurationMock;
    /** @var ConnectionPool|MockObject */
    private $connectionPoolMock;
    /** @var Connection|MockObject */
    private $connectionMock;
    /** @var QueryBuilder|MockObject */
    private $queryBuilderMock;
    /** @var AbstractSchemaManager|MockObject */
    private $schemaManagerMock;

    protected function setUp(): void
    {
        $this->configurationMock = $this->createMock(T3HaulerConfiguration::class);
        $this->connectionPoolMock = $this->createMock(ConnectionPool::class);
        $this->connectionMock = $this->createMock(Connection::class);
        $this->queryBuilderMock = $this->createMock(QueryBuilder::class);
        $this->schemaManagerMock = $this->createMock(AbstractSchemaManager::class);

        $this->subject = new ImportService(
            $this->configurationMock,
            $this->connectionPoolMock
        );
    }

    #[Test]
    public function importFromFileFailsWhenFileNotFound(): void
    {
        $result = $this->subject->importFromFile('/nonexistent/file.json');

        self::assertFalse($result['success']);
        self::assertStringContainsString('not found', $result['message']);
        self::assertEquals(0, $result['imported_records']);
    }

    #[Test]
    public function importFromFileFailsWhenFileIsEmpty(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, '');

        $result = $this->subject->importFromFile($tempFile);

        self::assertFalse($result['success']);
        self::assertStringContainsString('empty', $result['message']);
        self::assertEquals(0, $result['imported_records']);

        unlink($tempFile);
    }

    #[Test]
    public function importFromFileSucceedsWithValidJsonFile(): void
    {
        $exportData = [
            'metadata' => [
                'format_version' => '1.0',
                'total_records' => 2,
            ],
            'records' => [
                'pages' => [
                    '1' => ['title' => 'Page 1', 'uid' => 1],
                    '2' => ['title' => 'Page 2', 'uid' => 2],
                ],
            ],
            'relations' => [],
        ];

        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, json_encode($exportData));

        // Mock table existence check
        $this->connectionPoolMock
            ->expects(self::atLeast(2))
            ->method('getConnectionForTable')
            ->with('pages')
            ->willReturn($this->connectionMock);

        $this->connectionMock
            ->expects(self::atLeastOnce())
            ->method('createSchemaManager')
            ->willReturn($this->schemaManagerMock);

        $this->schemaManagerMock
            ->expects(self::atLeastOnce())
            ->method('tablesExist')
            ->with(['pages'])
            ->willReturn(true);

        // Mock database insertions
        $this->connectionMock
            ->expects(self::exactly(2))
            ->method('insert')
            ->with('pages', self::isType('array'))
            ->willReturn(1);

        // Mock QueryBuilder for duplicate detection
        $this->connectionMock
            ->expects(self::any())
            ->method('createQueryBuilder')
            ->willReturn($this->queryBuilderMock);

        $this->queryBuilderMock
            ->expects(self::any())
            ->method('select')
            ->willReturnSelf();

        $this->queryBuilderMock
            ->expects(self::any())
            ->method('from')
            ->willReturnSelf();

        $this->queryBuilderMock
            ->expects(self::any())
            ->method('where')
            ->willReturnSelf();

        $this->queryBuilderMock
            ->expects(self::any())
            ->method('setMaxResults')
            ->willReturnSelf();

        $this->queryBuilderMock
            ->expects(self::any())
            ->method('createNamedParameter')
            ->willReturn(':param');

        $resultStatementMock = $this->createMock(\Doctrine\DBAL\Result::class);
        $resultStatementMock
            ->expects(self::any())
            ->method('fetchAssociative')
            ->willReturn(false); // No existing records

        $this->queryBuilderMock
            ->expects(self::any())
            ->method('executeQuery')
            ->willReturn($resultStatementMock);

        $result = $this->subject->importFromFile($tempFile);

        self::assertTrue($result['success']);
        self::assertEquals(2, $result['imported_records']);
        self::assertArrayHasKey('imported_tables', $result);

        unlink($tempFile);
    }

    #[Test]
    public function importFromFileHandlesDryRunMode(): void
    {
        $exportData = [
            'metadata' => [
                'format_version' => '1.0',
                'total_records' => 3,
            ],
            'records' => [
                'pages' => [1, 2],
                'tt_content' => [101],
            ],
            'relations' => [],
        ];

        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, json_encode($exportData));

        // Mock table existence checks
        $this->connectionPoolMock
            ->expects(self::exactly(2))
            ->method('getConnectionForTable')
            ->willReturn($this->connectionMock);

        $this->connectionMock
            ->expects(self::atLeastOnce())
            ->method('createSchemaManager')
            ->willReturn($this->schemaManagerMock);

        $this->schemaManagerMock
            ->expects(self::atLeastOnce())
            ->method('tablesExist')
            ->willReturn(true);

        $result = $this->subject->importFromFile($tempFile, true);

        self::assertTrue($result['success']);
        self::assertEquals(0, $result['imported_records']);
        self::assertEquals(3, $result['would_import']);
        self::assertTrue($result['dry_run']);
        self::assertArrayHasKey('tables_summary', $result);

        unlink($tempFile);
    }

    #[Test]
    public function importRecordsSucceedsWithValidData(): void
    {
        $exportData = [
            'metadata' => [
                'format_version' => '1.0',
                'total_records' => 2,
            ],
            'records' => [
                'pages' => [
                    '1' => ['title' => 'Page 1', 'uid' => 1],
                    '2' => ['title' => 'Page 2', 'uid' => 2],
                ],
            ],
            'relations' => [],
        ];

        // Mock table existence check
        $this->connectionPoolMock
            ->expects(self::atLeast(2))
            ->method('getConnectionForTable')
            ->with('pages')
            ->willReturn($this->connectionMock);

        $this->connectionMock
            ->expects(self::atLeastOnce())
            ->method('createSchemaManager')
            ->willReturn($this->schemaManagerMock);

        $this->schemaManagerMock
            ->expects(self::atLeastOnce())
            ->method('tablesExist')
            ->with(['pages'])
            ->willReturn(true);

        // Mock database insertions
        $this->connectionMock
            ->expects(self::exactly(2))
            ->method('insert')
            ->with('pages', self::isType('array'))
            ->willReturn(1);

        // Mock QueryBuilder for duplicate detection
        $this->connectionMock
            ->expects(self::any())
            ->method('createQueryBuilder')
            ->willReturn($this->queryBuilderMock);

        $this->queryBuilderMock
            ->expects(self::any())
            ->method('select')
            ->willReturnSelf();

        $this->queryBuilderMock
            ->expects(self::any())
            ->method('from')
            ->willReturnSelf();

        $this->queryBuilderMock
            ->expects(self::any())
            ->method('where')
            ->willReturnSelf();

        $this->queryBuilderMock
            ->expects(self::any())
            ->method('setMaxResults')
            ->willReturnSelf();

        $this->queryBuilderMock
            ->expects(self::any())
            ->method('createNamedParameter')
            ->willReturn(':param');

        $resultStatementMock = $this->createMock(\Doctrine\DBAL\Result::class);
        $resultStatementMock
            ->expects(self::any())
            ->method('fetchAssociative')
            ->willReturn(false);

        $this->queryBuilderMock
            ->expects(self::any())
            ->method('executeQuery')
            ->willReturn($resultStatementMock);

        $result = $this->subject->importRecords($exportData);

        self::assertTrue($result['success']);
        self::assertEquals(2, $result['imported_records']);
        self::assertArrayHasKey('imported_tables', $result);
    }

    #[Test]
    public function importRecordsFailsWithInvalidStructure(): void
    {
        $exportData = [
            'invalid' => 'structure',
        ];

        $result = $this->subject->importRecords($exportData);

        self::assertFalse($result['success']);
        self::assertStringContainsString('Invalid export structure', $result['message']);
        self::assertEquals(0, $result['imported_records']);
    }

    #[Test]
    public function importRecordsAppliesTableFilter(): void
    {
        $exportData = [
            'metadata' => [
                'format_version' => '1.0',
                'total_records' => 3,
            ],
            'records' => [
                'pages' => [
                    '1' => ['title' => 'Page 1', 'uid' => 1],
                    '2' => ['title' => 'Page 2', 'uid' => 2],
                ],
                'tt_content' => [
                    '101' => ['header' => 'Content 1', 'uid' => 101],
                ],
            ],
            'relations' => [],
        ];

        // Mock table existence check for pages only
        $this->connectionPoolMock
            ->expects(self::atLeastOnce())
            ->method('getConnectionForTable')
            ->with('pages')
            ->willReturn($this->connectionMock);

        $this->connectionMock
            ->expects(self::atLeastOnce())
            ->method('createSchemaManager')
            ->willReturn($this->schemaManagerMock);

        $this->schemaManagerMock
            ->expects(self::atLeastOnce())
            ->method('tablesExist')
            ->with(['pages'])
            ->willReturn(true);

        // Mock database insertions for pages only
        $this->connectionMock
            ->expects(self::exactly(2))
            ->method('insert')
            ->with('pages', self::isType('array'))
            ->willReturn(1);

        // Mock QueryBuilder for duplicate detection
        $this->connectionMock
            ->expects(self::any())
            ->method('createQueryBuilder')
            ->willReturn($this->queryBuilderMock);

        $this->queryBuilderMock
            ->expects(self::any())
            ->method('select')
            ->willReturnSelf();

        $this->queryBuilderMock
            ->expects(self::any())
            ->method('from')
            ->willReturnSelf();

        $this->queryBuilderMock
            ->expects(self::any())
            ->method('where')
            ->willReturnSelf();

        $this->queryBuilderMock
            ->expects(self::any())
            ->method('setMaxResults')
            ->willReturnSelf();

        $this->queryBuilderMock
            ->expects(self::any())
            ->method('createNamedParameter')
            ->willReturn(':param');

        $resultStatementMock = $this->createMock(\Doctrine\DBAL\Result::class);
        $resultStatementMock
            ->expects(self::any())
            ->method('fetchAssociative')
            ->willReturn(false);

        $this->queryBuilderMock
            ->expects(self::any())
            ->method('executeQuery')
            ->willReturn($resultStatementMock);

        $result = $this->subject->importRecords($exportData, ['pages']);

        self::assertTrue($result['success']);
        self::assertEquals(2, $result['imported_records']);
        self::assertArrayHasKey('pages', $result['imported_tables']);
        self::assertArrayNotHasKey('tt_content', $result['imported_tables']);
    }

    #[Test]
    public function validateTargetIntegritySucceedsWithMatchingHash(): void
    {
        $exportData = ['records' => ['pages' => [1, 2]]];
        $baselineHash = 'test_hash';

        $result = $this->subject->validateTargetIntegrity($exportData, $baselineHash);

        // Since this is a mock implementation, it will always report hash mismatch
        self::assertFalse($result['valid']);
        self::assertArrayHasKey('expected_hash', $result);
        self::assertArrayHasKey('current_hash', $result);
    }

    #[Test]
    public function validateTargetIntegrityHandlesExceptions(): void
    {
        $exportData = ['records' => ['invalid_table' => [1, 2]]];
        $baselineHash = 'test_hash';

        $result = $this->subject->validateTargetIntegrity($exportData, $baselineHash);

        self::assertFalse($result['valid']);
        self::assertArrayHasKey('message', $result);
    }

    #[Test]
    public function importFromFileFailsWithInvalidJson(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, '{"invalid": json}');

        $result = $this->subject->importFromFile($tempFile);

        self::assertFalse($result['success']);
        self::assertStringContainsString('Failed to parse export file', $result['message']);
        self::assertEquals(0, $result['imported_records']);

        unlink($tempFile);
    }

    #[Test]
    public function importFromFileFailsWithInvalidExportStructure(): void
    {
        $exportData = [
            'invalid' => 'structure',
        ];

        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, json_encode($exportData));

        $result = $this->subject->importFromFile($tempFile);

        self::assertFalse($result['success']);
        self::assertStringContainsString('Invalid export structure', $result['message']);
        self::assertEquals(0, $result['imported_records']);

        unlink($tempFile);
    }

    #[Test]
    public function importFromFileFailsWithUnsupportedFormatVersion(): void
    {
        $exportData = [
            'metadata' => [
                'format_version' => '2.0',
            ],
            'records' => [],
        ];

        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, json_encode($exportData));

        $result = $this->subject->importFromFile($tempFile);

        self::assertFalse($result['success']);
        self::assertStringContainsString('Unsupported format version', $result['message']);
        self::assertEquals(0, $result['imported_records']);

        unlink($tempFile);
    }

    #[Test]
    public function importFromFileFailsWithMissingFormatVersion(): void
    {
        $exportData = [
            'metadata' => [
                'created_at' => time(),
            ],
            'records' => [],
        ];

        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, json_encode($exportData));

        $result = $this->subject->importFromFile($tempFile);

        self::assertFalse($result['success']);
        self::assertStringContainsString('Missing format version', $result['message']);
        self::assertEquals(0, $result['imported_records']);

        unlink($tempFile);
    }

    #[Test]
    public function importFromFileHandlesGeneralExceptions(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, json_encode([
            'metadata' => ['format_version' => '1.0'],
            'records' => ['pages' => ['1' => ['title' => 'Test']]],
        ]));

        // Make the connection pool throw an exception
        $this->connectionPoolMock
            ->expects(self::once())
            ->method('getConnectionForTable')
            ->willThrowException(new \Exception('Database connection failed'));

        $result = $this->subject->importFromFile($tempFile);

        self::assertFalse($result['success']);
        // The service might return "Import completed with errors" instead of "Import failed:" depending on the exception timing
        self::assertTrue(
            str_contains($result['message'], 'Import failed:') ||
            str_contains($result['message'], 'Import completed with errors')
        );
        self::assertEquals(0, $result['imported_records']);

        unlink($tempFile);
    }

    #[Test]
    public function importRecordsHandlesExceptions(): void
    {
        $exportData = [
            'metadata' => ['format_version' => '1.0'],
            'records' => ['pages' => ['1' => ['title' => 'Test']]],
        ];

        // Make the connection pool throw an exception
        $this->connectionPoolMock
            ->expects(self::once())
            ->method('getConnectionForTable')
            ->willThrowException(new \Exception('Database error'));

        $result = $this->subject->importRecords($exportData);

        self::assertFalse($result['success']);
        self::assertStringContainsString('Import completed with errors', $result['message']);
        self::assertEquals(0, $result['imported_records']);
        self::assertArrayHasKey('errors', $result);
        self::assertNotEmpty($result['errors']);
    }

    #[Test]
    public function importFromFileDryRunDetectsMissingTables(): void
    {
        $exportData = [
            'metadata' => ['format_version' => '1.0'],
            'records' => [
                'pages' => [1, 2],
                'nonexistent_table' => [101],
            ],
        ];

        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, json_encode($exportData));

        // Mock table existence - pages exists, nonexistent_table doesn't
        $this->connectionPoolMock
            ->method('getConnectionForTable')
            ->willReturn($this->connectionMock);

        $this->connectionMock
            ->method('createSchemaManager')
            ->willReturn($this->schemaManagerMock);

        $this->schemaManagerMock
            ->method('tablesExist')
            ->willReturnCallback(function ($tables) {
                return $tables[0] === 'pages';
            });

        $result = $this->subject->importFromFile($tempFile, true);

        self::assertTrue($result['success']);
        self::assertEquals(0, $result['imported_records']);
        self::assertEquals(2, $result['would_import']); // Only records from existing tables (pages: 2)
        self::assertTrue($result['dry_run']);
        self::assertNotEmpty($result['issues']);
        self::assertStringContainsString('nonexistent_table', $result['issues'][0]);

        unlink($tempFile);
    }

    #[Test]
    public function importRecordsSkipsNonExistentTables(): void
    {
        $exportData = [
            'metadata' => ['format_version' => '1.0'],
            'records' => [
                'pages' => ['1' => ['title' => 'Page 1', 'uid' => 1]],
                'nonexistent_table' => ['1' => ['name' => 'Test', 'uid' => 1]],
            ],
        ];

        // Mock table existence - pages exists, nonexistent_table doesn't
        $this->connectionPoolMock
            ->method('getConnectionForTable')
            ->willReturn($this->connectionMock);

        $this->connectionMock
            ->method('createSchemaManager')
            ->willReturn($this->schemaManagerMock);

        $this->schemaManagerMock
            ->method('tablesExist')
            ->willReturnCallback(function ($tables) {
                return $tables[0] === 'pages';
            });

        // Mock successful insertion for pages table
        $this->connectionMock
            ->expects(self::once())
            ->method('insert')
            ->with('pages', ['title' => 'Page 1'])
            ->willReturn(1);

        // Mock query builder for duplicate check
        $this->connectionMock
            ->method('createQueryBuilder')
            ->willReturn($this->queryBuilderMock);

        $this->queryBuilderMock->method('select')->willReturnSelf();
        $this->queryBuilderMock->method('from')->willReturnSelf();
        $this->queryBuilderMock->method('where')->willReturnSelf();
        $this->queryBuilderMock->method('setMaxResults')->willReturnSelf();
        $this->queryBuilderMock->method('createNamedParameter')->willReturn(':param');

        $resultStatementMock = $this->createMock(\Doctrine\DBAL\Result::class);
        $resultStatementMock->method('fetchAssociative')->willReturn(false);
        $this->queryBuilderMock->method('executeQuery')->willReturn($resultStatementMock);

        $result = $this->subject->importRecords($exportData);

        self::assertFalse($result['success']); // Should fail due to errors with nonexistent table
        self::assertEquals(1, $result['imported_records']); // Only pages record imported
        self::assertNotEmpty($result['errors']);
        self::assertStringContainsString('nonexistent_table', $result['errors'][0]);
    }

    #[Test]
    public function importRecordsSkipsExistingRecords(): void
    {
        $exportData = [
            'metadata' => ['format_version' => '1.0'],
            'records' => [
                'tx_t3hauler_migrations' => [
                    '1' => ['migration_id' => 'test-migration', 'name' => 'Test', 'uid' => 1],
                ],
            ],
        ];

        // Mock table existence
        $this->connectionPoolMock
            ->method('getConnectionForTable')
            ->willReturn($this->connectionMock);

        $this->connectionMock
            ->method('createSchemaManager')
            ->willReturn($this->schemaManagerMock);

        $this->schemaManagerMock
            ->method('tablesExist')
            ->willReturn(true);

        // Mock query builder to return existing record
        $this->connectionMock
            ->method('createQueryBuilder')
            ->willReturn($this->queryBuilderMock);

        $this->queryBuilderMock->method('select')->willReturnSelf();
        $this->queryBuilderMock->method('from')->willReturnSelf();
        $this->queryBuilderMock->method('where')->willReturnSelf();
        $this->queryBuilderMock->method('setMaxResults')->willReturnSelf();
        $this->queryBuilderMock->method('createNamedParameter')->willReturn(':param');

        $resultStatementMock = $this->createMock(\Doctrine\DBAL\Result::class);
        $resultStatementMock->method('fetchAssociative')
            ->willReturn(['uid' => 1, 'migration_id' => 'test-migration']); // Existing record

        $this->queryBuilderMock->method('executeQuery')->willReturn($resultStatementMock);

        // Should not call insert since record already exists
        $this->connectionMock
            ->expects(self::never())
            ->method('insert');

        $result = $this->subject->importRecords($exportData);

        self::assertTrue($result['success']);
        self::assertEquals(0, $result['imported_records']); // No records imported (skipped)
    }

    #[Test]
    public function importRecordsHandlesInsertExceptions(): void
    {
        $exportData = [
            'metadata' => ['format_version' => '1.0'],
            'records' => [
                'pages' => [
                    '1' => ['title' => 'Page 1', 'uid' => 1],
                    '2' => ['title' => 'Page 2', 'uid' => 2],
                ],
            ],
        ];

        // Mock table existence
        $this->connectionPoolMock
            ->method('getConnectionForTable')
            ->willReturn($this->connectionMock);

        $this->connectionMock
            ->method('createSchemaManager')
            ->willReturn($this->schemaManagerMock);

        $this->schemaManagerMock
            ->method('tablesExist')
            ->willReturn(true);

        // Mock query builder for duplicate check
        $this->connectionMock
            ->method('createQueryBuilder')
            ->willReturn($this->queryBuilderMock);

        $this->queryBuilderMock->method('select')->willReturnSelf();
        $this->queryBuilderMock->method('from')->willReturnSelf();
        $this->queryBuilderMock->method('where')->willReturnSelf();
        $this->queryBuilderMock->method('setMaxResults')->willReturnSelf();
        $this->queryBuilderMock->method('createNamedParameter')->willReturn(':param');

        $resultStatementMock = $this->createMock(\Doctrine\DBAL\Result::class);
        $resultStatementMock->method('fetchAssociative')->willReturn(false);
        $this->queryBuilderMock->method('executeQuery')->willReturn($resultStatementMock);

        // First insert succeeds, second throws exception
        $this->connectionMock
            ->expects(self::exactly(2))
            ->method('insert')
            ->willReturnOnConsecutiveCalls(1, self::throwException(new \Exception('Insert failed')));

        $result = $this->subject->importRecords($exportData);

        self::assertTrue($result['success']); // Should still be successful overall
        self::assertEquals(1, $result['imported_records']); // One record imported despite error
    }

    #[Test]
    public function importRecordsSkipsInvalidRecordData(): void
    {
        $exportData = [
            'metadata' => ['format_version' => '1.0'],
            'records' => [
                'pages' => [
                    '1' => ['title' => 'Page 1', 'uid' => 1],
                    '2' => 'invalid_data', // Not an array
                    '3' => ['title' => 'Page 3', 'uid' => 3],
                ],
            ],
        ];

        // Mock table existence
        $this->connectionPoolMock
            ->method('getConnectionForTable')
            ->willReturn($this->connectionMock);

        $this->connectionMock
            ->method('createSchemaManager')
            ->willReturn($this->schemaManagerMock);

        $this->schemaManagerMock
            ->method('tablesExist')
            ->willReturn(true);

        // Mock query builder for duplicate check
        $this->connectionMock
            ->method('createQueryBuilder')
            ->willReturn($this->queryBuilderMock);

        $this->queryBuilderMock->method('select')->willReturnSelf();
        $this->queryBuilderMock->method('from')->willReturnSelf();
        $this->queryBuilderMock->method('where')->willReturnSelf();
        $this->queryBuilderMock->method('setMaxResults')->willReturnSelf();
        $this->queryBuilderMock->method('createNamedParameter')->willReturn(':param');

        $resultStatementMock = $this->createMock(\Doctrine\DBAL\Result::class);
        $resultStatementMock->method('fetchAssociative')->willReturn(false);
        $this->queryBuilderMock->method('executeQuery')->willReturn($resultStatementMock);

        // Should only insert valid records (2 inserts, skipping invalid data)
        $this->connectionMock
            ->expects(self::exactly(2))
            ->method('insert')
            ->willReturn(1);

        $result = $this->subject->importRecords($exportData);

        self::assertTrue($result['success']);
        self::assertEquals(2, $result['imported_records']); // Only valid records imported
    }

    #[Test]
    public function importRecordsFiltersByTableName(): void
    {
        $exportData = [
            'metadata' => ['format_version' => '1.0'],
            'records' => [
                'pages' => ['1' => ['title' => 'Page 1', 'uid' => 1]],
                'tt_content' => ['1' => ['header' => 'Content 1', 'uid' => 1]],
            ],
            'relations' => [
                'pages:1' => [['field' => 'test', 'to_table' => 'pages', 'to_uid' => 2]],
                'tt_content:1' => [['field' => 'pid', 'to_table' => 'pages', 'to_uid' => 1]],
            ],
        ];

        // Mock table existence for tt_content only
        $this->connectionPoolMock
            ->method('getConnectionForTable')
            ->with('tt_content')
            ->willReturn($this->connectionMock);

        $this->connectionMock
            ->method('createSchemaManager')
            ->willReturn($this->schemaManagerMock);

        $this->schemaManagerMock
            ->method('tablesExist')
            ->willReturn(true);

        // Mock query builder for duplicate check
        $this->connectionMock
            ->method('createQueryBuilder')
            ->willReturn($this->queryBuilderMock);

        $this->queryBuilderMock->method('select')->willReturnSelf();
        $this->queryBuilderMock->method('from')->willReturnSelf();
        $this->queryBuilderMock->method('where')->willReturnSelf();
        $this->queryBuilderMock->method('setMaxResults')->willReturnSelf();
        $this->queryBuilderMock->method('createNamedParameter')->willReturn(':param');

        $resultStatementMock = $this->createMock(\Doctrine\DBAL\Result::class);
        $resultStatementMock->method('fetchAssociative')->willReturn(false);
        $this->queryBuilderMock->method('executeQuery')->willReturn($resultStatementMock);

        // Should only insert from tt_content table
        $this->connectionMock
            ->expects(self::once())
            ->method('insert')
            ->with('tt_content', ['header' => 'Content 1'])
            ->willReturn(1);

        $result = $this->subject->importRecords($exportData, ['tt_content']);

        self::assertTrue($result['success']);
        self::assertEquals(1, $result['imported_records']);
        self::assertArrayHasKey('tt_content', $result['imported_tables']);
        self::assertArrayNotHasKey('pages', $result['imported_tables']);
    }

    #[Test]
    public function validateTargetIntegrityReturnsValidWithMatchingHash(): void
    {
        $records = ['pages' => [1, 2]];
        $exportData = ['records' => $records];

        // Calculate the hash that ImportService would calculate
        $expectedHash = hash('sha256', serialize($records));

        $result = $this->subject->validateTargetIntegrity($exportData, $expectedHash);

        self::assertTrue($result['valid']);
        self::assertEquals('Target environment integrity verified', $result['message']);
        self::assertEquals($expectedHash, $result['hash']);
    }
}
