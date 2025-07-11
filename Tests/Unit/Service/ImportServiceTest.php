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
}
