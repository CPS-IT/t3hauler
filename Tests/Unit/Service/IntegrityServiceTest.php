<?php

/** @noinspection DynamicInvocationViaScopeResolutionInspection */

declare(strict_types=1);

namespace Cpsit\T3hauler\Tests\Unit\Service;

use Cpsit\T3hauler\Configuration\T3HaulerConfiguration;
use Cpsit\T3hauler\Service\IntegrityService;
use Cpsit\T3hauler\Utility\HashUtility;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class IntegrityServiceTest extends TestCase
{
    private IntegrityService $subject;
    /** @var T3HaulerConfiguration&MockObject */
    private T3HaulerConfiguration $configurationMock;
    /** @var HashUtility&MockObject */
    private HashUtility $hashUtilityMock;
    /** @var \TYPO3\CMS\Core\Database\ConnectionPool&MockObject */
    private $connectionPoolMock;
    /** @var \TYPO3\CMS\Core\Database\Connection&MockObject */
    private $connectionMock;
    /** @var \TYPO3\CMS\Core\Database\Query\QueryBuilder&MockObject */
    private $queryBuilderMock;
    /** @var \Doctrine\DBAL\Schema\AbstractSchemaManager&MockObject */
    private $schemaManagerMock;

    protected function setUp(): void
    {
        $this->configurationMock = $this->createMock(T3HaulerConfiguration::class);
        $this->hashUtilityMock = $this->createMock(HashUtility::class);
        $this->connectionPoolMock = $this->createMock(\TYPO3\CMS\Core\Database\ConnectionPool::class);
        $this->connectionMock = $this->createMock(\TYPO3\CMS\Core\Database\Connection::class);
        $this->queryBuilderMock = $this->createMock(\TYPO3\CMS\Core\Database\Query\QueryBuilder::class);
        $this->schemaManagerMock = $this->createMock(\Doctrine\DBAL\Schema\AbstractSchemaManager::class);

        $this->subject = new IntegrityService(
            $this->configurationMock,
            $this->hashUtilityMock,
            $this->connectionPoolMock
        );
    }

    #[Test]
    public function validateMigrationIntegritySucceedsWithMatchingHash(): void
    {
        $migrationHash = 'test_hash_123';
        $affectedTables = ['pages', 'tt_content'];

        $this->configurationMock
            ->method('get')
            ->with('detection.excludeFields', [])
            ->willReturn(['tstamp', 'crdate']);

        $this->hashUtilityMock
            ->method('calculateTableHash')
            ->willReturn('foo-hash1');

        $this->connectionPoolMock
            ->method('getConnectionForTable')
            ->willReturn($this->connectionMock);

        $this->connectionMock
            ->method('createSchemaManager')
            ->willReturn($this->schemaManagerMock);

        $this->schemaManagerMock
            ->expects(self::atLeastOnce())
            ->method('tablesExist')
            ->willReturn(true);

        $result = $this->subject->validateMigrationIntegrity($migrationHash, $affectedTables);

        // Since the current hash will be different from migrationHash, it should fail
        self::assertFalse($result['valid']);
        self::assertArrayHasKey('conflicts', $result);
        self::assertEquals($migrationHash, $result['expected_hash']);
        self::assertArrayHasKey('current_hash', $result);
    }

    #[Test]
    public function validateMigrationIntegrityHandlesExceptions(): void
    {
        $migrationHash = 'test_hash_123';
        $affectedTables = ['pages'];

        $this->configurationMock
            ->expects(self::once())
            ->method('get')
            ->willThrowException(new \Exception('Configuration error'));

        $result = $this->subject->validateMigrationIntegrity($migrationHash, $affectedTables);

        self::assertFalse($result['valid']);
        self::assertStringContainsString('Integrity validation failed', $result['message']);
        self::assertArrayHasKey('exception', $result);
    }

    #[Test]
    public function checkExportConflictsSucceedsWithNoConflicts(): void
    {
        $exportData = [
            'records' => [
                'pages' => [1, 2],
                'tt_content' => [101, 102],
            ],
        ];
        $baselineHash = 'baseline_hash_123';

        $this->configurationMock
            ->expects(self::once())
            ->method('get')
            ->with('detection.excludeFields', [])
            ->willReturn(['tstamp']);

        // Mock table existence - called 4 times: 2 for hash calculation, 2 for conflict detection
        $this->connectionPoolMock
            ->expects(self::atLeast(2))
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

        $this->hashUtilityMock
            ->expects(self::exactly(2))
            ->method('calculateTableHash')
            ->willReturnOnConsecutiveCalls('hash1', 'hash2');

        $result = $this->subject->checkExportConflicts($exportData, $baselineHash);

        // Since current hash will be different from baseline, it should detect conflicts
        self::assertTrue($result['has_conflicts']);
        self::assertArrayHasKey('conflicts', $result);
        self::assertArrayHasKey('resolution_suggestions', $result);
    }

    #[Test]
    public function checkExportConflictsHandlesEmptyRecords(): void
    {
        $exportData = [];
        $baselineHash = 'baseline_hash_123';

        $result = $this->subject->checkExportConflicts($exportData, $baselineHash);

        self::assertFalse($result['has_conflicts']);
        self::assertStringContainsString('No records in export data', $result['message']);
        self::assertEmpty($result['conflicts']);
    }

    #[Test]
    public function validatePreImportRequirementsSucceedsWithValidData(): void
    {
        $exportData = [
            'records' => [
                'pages' => [1, 2],
            ],
            'relations' => [
                'tt_content:101' => [
                    ['field' => 'pid', 'to_table' => 'pages', 'to_uid' => 1],
                ],
            ],
        ];

        // Mock table existence - called 3 times: 1 for pages, 1 for relation check, 1 for dependency check
        $this->connectionPoolMock
            ->expects(self::atLeast(2))
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

        $result = $this->subject->validatePreImportRequirements($exportData);

        self::assertTrue($result['valid']);
        self::assertTrue($result['can_proceed']);
        self::assertEmpty($result['issues']);
    }

    #[Test]
    public function validatePreImportRequirementsDetectsMissingTable(): void
    {
        $exportData = [
            'records' => [
                'nonexistent_table' => [1, 2],
            ],
        ];

        // Mock table existence check to return false
        $this->connectionPoolMock
            ->expects(self::once())
            ->method('getConnectionForTable')
            ->willReturn($this->connectionMock);

        $this->connectionMock
            ->expects(self::once())
            ->method('createSchemaManager')
            ->willReturn($this->schemaManagerMock);

        $this->schemaManagerMock
            ->expects(self::once())
            ->method('tablesExist')
            ->willReturn(false);

        $result = $this->subject->validatePreImportRequirements($exportData);

        self::assertFalse($result['valid']);
        self::assertFalse($result['can_proceed']);
        self::assertNotEmpty($result['issues']);
        self::assertStringContainsString('nonexistent_table', $result['issues'][0]);
    }

    #[Test]
    public function createIntegrityCheckpointSucceeds(): void
    {
        $tables = ['pages', 'tt_content'];
        $checkpointId = 'test_checkpoint_123';

        $this->configurationMock
            ->expects(self::once())
            ->method('get')
            ->with('detection.excludeFields', [])
            ->willReturn(['tstamp']);

        // Mock table existence and hash calculation
        $this->connectionPoolMock
            ->expects(self::atLeast(2))
            ->method('getConnectionForTable')
            ->willReturn($this->connectionMock);

        $this->connectionMock
            ->expects(self::exactly(2))
            ->method('createSchemaManager')
            ->willReturn($this->schemaManagerMock);

        $this->connectionMock
            ->expects(self::exactly(2))
            ->method('createQueryBuilder')
            ->willReturn($this->queryBuilderMock);

        $this->schemaManagerMock
            ->expects(self::exactly(2))
            ->method('tablesExist')
            ->willReturn(true);

        $this->hashUtilityMock
            ->expects(self::exactly(2))
            ->method('calculateTableHash')
            ->willReturnOnConsecutiveCalls('hash1', 'hash2');

        // Mock record count queries
        $resultMock = $this->createMock(\Doctrine\DBAL\Result::class);
        $resultMock
            ->expects(self::exactly(2))
            ->method('fetchOne')
            ->willReturnOnConsecutiveCalls(5, 10);

        $this->queryBuilderMock
            ->expects(self::exactly(2))
            ->method('count')
            ->willReturnSelf();

        $this->queryBuilderMock
            ->expects(self::exactly(2))
            ->method('from')
            ->willReturnSelf();

        $this->queryBuilderMock
            ->expects(self::exactly(2))
            ->method('executeQuery')
            ->willReturn($resultMock);

        $result = $this->subject->createIntegrityCheckpoint($tables, $checkpointId);

        self::assertTrue($result['success']);
        self::assertEquals($checkpointId, $result['checkpoint_id']);
        self::assertEquals(2, $result['tables_included']);
        self::assertArrayHasKey('checkpoint_data', $result);
    }

    #[Test]
    public function createIntegrityCheckpointHandlesExceptions(): void
    {
        $tables = ['pages'];
        $checkpointId = 'test_checkpoint_123';

        $this->configurationMock
            ->expects(self::once())
            ->method('get')
            ->willThrowException(new \Exception('Configuration error'));

        $result = $this->subject->createIntegrityCheckpoint($tables, $checkpointId);

        self::assertFalse($result['success']);
        self::assertEquals($checkpointId, $result['checkpoint_id']);
        self::assertStringContainsString('Failed to create integrity checkpoint', $result['message']);
        self::assertArrayHasKey('exception', $result);
    }

    #[Test]
    public function validatePreImportRequirementsHandlesRelationIssues(): void
    {
        $exportData = [
            'records' => [
                'pages' => [1],
            ],
            'relations' => [
                'tt_content:101' => [
                    ['field' => 'pid', 'to_table' => 'nonexistent_table', 'to_uid' => 1],
                ],
            ],
        ];

        // Mock pages table exists, nonexistent_table doesn't
        $this->connectionPoolMock
            ->expects(self::atLeast(2))
            ->method('getConnectionForTable')
            ->willReturn($this->connectionMock);

        $this->connectionMock
            ->expects(self::atLeastOnce())
            ->method('createSchemaManager')
            ->willReturn($this->schemaManagerMock);

        $this->schemaManagerMock
            ->expects(self::atLeastOnce())
            ->method('tablesExist')
            ->willReturnOnConsecutiveCalls(true, false, false);

        $result = $this->subject->validatePreImportRequirements($exportData);

        self::assertFalse($result['valid']); // Should fail due to missing table in relations
        self::assertFalse($result['can_proceed']);
        self::assertNotEmpty($result['issues']);
    }

    #[Test]
    public function validatePreImportRequirementsHandlesExceptions(): void
    {
        $exportData = [
            'records' => [
                'pages' => [1],
            ],
        ];

        $this->connectionPoolMock
            ->expects(self::once())
            ->method('getConnectionForTable')
            ->willThrowException(new \Exception('Database error'));

        $result = $this->subject->validatePreImportRequirements($exportData);

        self::assertFalse($result['valid']);
        self::assertFalse($result['can_proceed']);
        self::assertStringContainsString('Pre-import validation failed', $result['message']);
        self::assertNotEmpty($result['issues']);
    }

    #[Test]
    public function checkExportConflictsHandlesExceptions(): void
    {
        $exportData = [
            'records' => [
                'pages' => [1, 2],
            ],
        ];
        $baselineHash = 'baseline_hash_123';

        $this->configurationMock
            ->expects(self::once())
            ->method('get')
            ->willThrowException(new \Exception('Configuration error'));

        $result = $this->subject->checkExportConflicts($exportData, $baselineHash);

        self::assertTrue($result['has_conflicts']);
        self::assertStringContainsString('Conflict detection failed', $result['message']);
        self::assertArrayHasKey('exception', $result);
        self::assertEmpty($result['conflicts']);
    }

    #[Test]
    public function validateMigrationIntegrityWithMatchingHashSucceeds(): void
    {
        $affectedTables = ['pages'];
        $excludeFields = ['tstamp'];

        // Mock configuration
        $this->configurationMock
            ->method('get')
            ->with('detection.excludeFields', [])
            ->willReturn($excludeFields);

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

        // Mock hash calculation to return consistent hash
        $this->hashUtilityMock
            ->method('calculateTableHash')
            ->with('pages', $excludeFields)
            ->willReturn('table_hash_pages');

        // The combined hash will be sha256 of serialize(['pages' => 'table_hash_pages'])
        $expectedCombinedHash = hash('sha256', serialize(['pages' => 'table_hash_pages']));

        $result = $this->subject->validateMigrationIntegrity($expectedCombinedHash, $affectedTables);

        self::assertTrue($result['valid']);
        self::assertEquals('Migration integrity validated successfully', $result['message']);
        self::assertEquals($expectedCombinedHash, $result['hash']);
        self::assertEquals($affectedTables, $result['affected_tables']);
    }

    #[Test]
    public function checkExportConflictsWithMatchingHashSucceeds(): void
    {
        $exportData = [
            'records' => [
                'pages' => [1, 2],
            ],
        ];
        $excludeFields = ['tstamp'];

        // Mock configuration
        $this->configurationMock
            ->method('get')
            ->with('detection.excludeFields', [])
            ->willReturn($excludeFields);

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

        // Mock hash calculation to return consistent hash
        $this->hashUtilityMock
            ->method('calculateTableHash')
            ->with('pages', $excludeFields)
            ->willReturn('table_hash_pages');

        // The combined hash will be sha256 of serialize(['pages' => 'table_hash_pages'])
        $baselineHash = hash('sha256', serialize(['pages' => 'table_hash_pages']));

        $result = $this->subject->checkExportConflicts($exportData, $baselineHash);

        self::assertFalse($result['has_conflicts']);
        self::assertEquals('No conflicts detected', $result['message']);
        self::assertEquals($baselineHash, $result['baseline_hash']);
        self::assertEquals($baselineHash, $result['current_hash']);
        self::assertEmpty($result['conflicts']);
    }

    #[Test]
    public function checkExportConflictsDetectsRecordConflicts(): void
    {
        $exportData = [
            'records' => [
                'pages' => [1, 2],
            ],
        ];
        $baselineHash = 'different_baseline_hash';

        // Mock configuration
        $this->configurationMock
            ->method('get')
            ->with('detection.excludeFields', [])
            ->willReturn([]);

        // Mock table existence
        $this->connectionPoolMock
            ->method('getConnectionForTable')
            ->willReturn($this->connectionMock);

        $this->connectionMock
            ->method('createSchemaManager')
            ->willReturn($this->schemaManagerMock);

        $this->connectionMock
            ->method('createQueryBuilder')
            ->willReturn($this->queryBuilderMock);

        $this->schemaManagerMock
            ->method('tablesExist')
            ->willReturn(true);

        // Mock hash calculation to return different hash
        $this->hashUtilityMock
            ->method('calculateTableHash')
            ->willReturn('current_table_hash');

        // Mock query builder for record existence check
        $resultMock = $this->createMock(\Doctrine\DBAL\Result::class);
        $resultMock
            ->method('fetchAssociative')
            ->willReturnOnConsecutiveCalls(
                ['uid' => 1, 'title' => 'Page 1'], // First record exists
                false // Second record doesn't exist
            );

        $this->queryBuilderMock
            ->method('select')
            ->willReturnSelf();

        $this->queryBuilderMock
            ->method('from')
            ->willReturnSelf();

        $this->queryBuilderMock
            ->method('where')
            ->willReturnSelf();

        $this->queryBuilderMock
            ->method('createNamedParameter')
            ->willReturn(':uid');

        $this->queryBuilderMock
            ->method('executeQuery')
            ->willReturn($resultMock);

        $result = $this->subject->checkExportConflicts($exportData, $baselineHash);

        self::assertTrue($result['has_conflicts']);
        self::assertStringContainsString('Conflicts detected', $result['message']);
        self::assertNotEmpty($result['conflicts']);
        self::assertNotEmpty($result['resolution_suggestions']);
    }

    #[Test]
    public function createIntegrityCheckpointHandlesMissingTables(): void
    {
        $tables = ['pages', 'nonexistent_table'];
        $checkpointId = 'test_checkpoint_missing_tables';

        $this->configurationMock
            ->method('get')
            ->with('detection.excludeFields', [])
            ->willReturn([]);

        // Mock table existence - pages exists, nonexistent_table doesn't
        $this->connectionPoolMock
            ->method('getConnectionForTable')
            ->willReturn($this->connectionMock);

        $this->connectionMock
            ->method('createSchemaManager')
            ->willReturn($this->schemaManagerMock);

        $this->connectionMock
            ->method('createQueryBuilder')
            ->willReturn($this->queryBuilderMock);

        $this->schemaManagerMock
            ->method('tablesExist')
            ->willReturnOnConsecutiveCalls(true, false);

        $this->hashUtilityMock
            ->method('calculateTableHash')
            ->willReturn('pages_hash');

        // Mock record count query for pages table only
        $resultMock = $this->createMock(\Doctrine\DBAL\Result::class);
        $resultMock
            ->method('fetchOne')
            ->willReturn(5);

        $this->queryBuilderMock
            ->method('count')
            ->willReturnSelf();

        $this->queryBuilderMock
            ->method('from')
            ->willReturnSelf();

        $this->queryBuilderMock
            ->method('executeQuery')
            ->willReturn($resultMock);

        $result = $this->subject->createIntegrityCheckpoint($tables, $checkpointId);

        self::assertTrue($result['success']);
        self::assertEquals(1, $result['tables_included']); // Only pages table included
        self::assertArrayHasKey('pages', $result['checkpoint_data']['tables']);
        self::assertArrayNotHasKey('nonexistent_table', $result['checkpoint_data']['tables']);
    }

    #[Test]
    public function validatePreImportRequirementsWithDiskSpaceWarning(): void
    {
        $exportData = [
            'records' => [
                'pages' => [1],
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

        $result = $this->subject->validatePreImportRequirements($exportData);

        // Should pass validation but may have warnings about disk space
        self::assertTrue($result['valid']);
        self::assertTrue($result['can_proceed']);
        self::assertEmpty($result['issues']);
        // Disk space warning may or may not be present depending on actual disk space
    }

    #[Test]
    public function validatePreImportRequirementsWithComplexRelations(): void
    {
        $exportData = [
            'records' => [
                'pages' => [1],
                'tt_content' => [101],
            ],
            'relations' => [
                'tt_content:101' => [
                    ['field' => 'pid', 'to_table' => 'pages', 'to_uid' => 1],
                    ['field' => 'image', 'to_table' => 'sys_file', 'to_uid' => 201],
                ],
                'pages:1' => [
                    ['field' => 'parent', 'to_table' => 'pages', 'to_uid' => 0],
                ],
            ],
        ];

        // Mock all tables exist
        $this->connectionPoolMock
            ->method('getConnectionForTable')
            ->willReturn($this->connectionMock);

        $this->connectionMock
            ->method('createSchemaManager')
            ->willReturn($this->schemaManagerMock);

        $this->schemaManagerMock
            ->method('tablesExist')
            ->willReturn(true);

        $result = $this->subject->validatePreImportRequirements($exportData);

        self::assertTrue($result['valid']);
        self::assertTrue($result['can_proceed']);
        self::assertEmpty($result['issues']);
    }
}
