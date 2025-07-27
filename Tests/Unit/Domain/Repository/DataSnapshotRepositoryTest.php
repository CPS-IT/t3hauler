<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Tests\Unit\Domain\Repository;

use Cpsit\T3hauler\Domain\Model\DataSnapshot;
use Cpsit\T3hauler\Domain\Repository\DataSnapshotRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Unit tests for DataSnapshotRepository
 */
final class DataSnapshotRepositoryTest extends TestCase
{
    private DataSnapshotRepository $subject;
    /** @var ConnectionPool&MockObject */
    private ConnectionPool $connectionPoolMock;
    /** @var Connection&MockObject */
    private Connection $connectionMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connectionPoolMock = $this->createMock(ConnectionPool::class);
        $this->connectionMock = $this->createMock(Connection::class);

        $this->connectionPoolMock->method('getConnectionForTable')
            ->with('tx_t3hauler_snapshots')
            ->willReturn($this->connectionMock);

        // Mock getQueryBuilderForTable to return a QueryBuilder from the connection mock
        $this->connectionPoolMock->method('getQueryBuilderForTable')
            ->with('tx_t3hauler_snapshots')
            ->willReturnCallback(function () {
                return $this->connectionMock->createQueryBuilder();
            });

        $this->subject = new DataSnapshotRepository($this->connectionPoolMock);
    }

    #[Test]
    public function constructorSetsConnectionPool(): void
    {
        $repository = new DataSnapshotRepository($this->connectionPoolMock);

        self::assertInstanceOf(DataSnapshotRepository::class, $repository);
    }

    #[Test]
    public function saveInsertsNewSnapshotWhenUidIsNull(): void
    {
        $snapshot = $this->createDataSnapshot();
        $snapshotData = $snapshot->toArray();
        unset($snapshotData['uid']);

        $this->connectionMock->expects(self::once())
            ->method('insert')
            ->with('tx_t3hauler_snapshots', $snapshotData);

        $this->connectionMock->expects(self::once())
            ->method('lastInsertId')
            ->willReturn('123');

        $result = $this->subject->save($snapshot);

        self::assertSame($snapshot, $result);
        self::assertEquals(123, $result->getUid());
    }

    #[Test]
    public function saveUpdatesExistingSnapshotWhenUidExists(): void
    {
        $snapshot = $this->createDataSnapshot();
        $snapshot->setUid(456);
        $snapshotData = $snapshot->toArray();
        unset($snapshotData['uid']);

        $this->connectionMock->expects(self::once())
            ->method('update')
            ->with(
                'tx_t3hauler_snapshots',
                $snapshotData,
                ['uid' => 456],
                ['uid' => Connection::PARAM_INT]
            );

        $result = $this->subject->save($snapshot);

        self::assertSame($snapshot, $result);
    }

    #[Test]
    public function deleteDoesNothingWhenSnapshotHasNoUid(): void
    {
        $snapshot = $this->createDataSnapshot();

        $this->connectionMock->expects(self::never())
            ->method('delete');

        $this->subject->delete($snapshot);
    }

    #[Test]
    public function deleteRemovesSnapshotWhenUidExists(): void
    {
        $snapshot = $this->createDataSnapshot();
        $snapshot->setUid(789);

        $this->connectionMock->expects(self::once())
            ->method('delete')
            ->with(
                'tx_t3hauler_snapshots',
                ['uid' => 789],
                ['uid' => Connection::PARAM_INT]
            );

        $this->subject->delete($snapshot);
    }

    #[Test]
    public function saveHandlesLastInsertIdAsString(): void
    {
        $snapshot = $this->createDataSnapshot();

        $this->connectionMock->expects(self::once())
            ->method('insert');

        $this->connectionMock->expects(self::once())
            ->method('lastInsertId')
            ->willReturn('999');

        $result = $this->subject->save($snapshot);

        self::assertEquals(999, $result->getUid());
    }

    #[Test]
    public function saveHandlesEmptyLastInsertId(): void
    {
        $snapshot = $this->createDataSnapshot();

        $this->connectionMock->expects(self::once())
            ->method('insert');

        $this->connectionMock->expects(self::once())
            ->method('lastInsertId')
            ->willReturn('0');

        $result = $this->subject->save($snapshot);

        self::assertEquals(0, $result->getUid());
    }

    #[Test]
    public function savePreservesOriginalSnapshotOnUpdate(): void
    {
        $snapshot = $this->createDataSnapshot();
        $snapshot->setUid(123);
        $originalIdentifier = $snapshot->getIdentifier();

        $this->connectionMock->method('update')
            ->willReturn(1);

        $result = $this->subject->save($snapshot);

        self::assertSame($snapshot, $result);
        self::assertEquals($originalIdentifier, $result->getIdentifier());
    }

    #[Test]
    public function deleteWithNullUidDoesNotCallConnection(): void
    {
        $snapshot = $this->createDataSnapshot();
        // Uid is null by default

        $this->connectionMock->expects(self::never())
            ->method('delete');

        $this->subject->delete($snapshot);
    }

    #[Test]
    public function saveInsertsCorrectDataForNewSnapshot(): void
    {
        $snapshot = new DataSnapshot(
            'test-identifier',
            'test_table',
            'test-hash'
        );

        $expectedData = $snapshot->toArray();
        unset($expectedData['uid']);

        $this->connectionMock->expects(self::once())
            ->method('insert')
            ->with('tx_t3hauler_snapshots', $expectedData);

        $this->connectionMock->method('lastInsertId')
            ->willReturn('1');

        $this->subject->save($snapshot);
    }

    #[Test]
    public function saveUpdatesCorrectDataForExistingSnapshot(): void
    {
        $snapshot = new DataSnapshot(
            'test-identifier',
            'test_table',
            'test-hash'
        );
        $snapshot->setUid(42);

        $expectedData = $snapshot->toArray();
        unset($expectedData['uid']);

        $this->connectionMock->expects(self::once())
            ->method('update')
            ->with(
                'tx_t3hauler_snapshots',
                $expectedData,
                ['uid' => 42],
                ['uid' => Connection::PARAM_INT]
            );

        $this->subject->save($snapshot);
    }

    #[Test]
    public function connectionPoolIsCalledWithCorrectTableName(): void
    {
        $this->connectionPoolMock->expects(self::atLeastOnce())
            ->method('getConnectionForTable')
            ->with('tx_t3hauler_snapshots');

        $snapshot = $this->createDataSnapshot();
        $snapshot->setUid(123);

        $this->connectionMock->method('delete');

        $this->subject->delete($snapshot);
    }

    #[Test]
    public function saveMaintainsSnapshotIntegrity(): void
    {
        $snapshot = $this->createDataSnapshot();
        $originalHash = $snapshot->getHash();
        $originalTableName = $snapshot->getTableName();

        $this->connectionMock->method('insert');
        $this->connectionMock->method('lastInsertId')->willReturn('1');

        $result = $this->subject->save($snapshot);

        self::assertSame($snapshot, $result);
        self::assertEquals($originalHash, $result->getHash());
        self::assertEquals($originalTableName, $result->getTableName());
    }

    #[Test]
    public function saveHandlesComplexSnapshotData(): void
    {
        $snapshot = new DataSnapshot(
            'complex-identifier-with-special-chars-@#$%',
            'complex_table_name_with_special_chars',
            'sha256:abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890'
        );

        $snapshot->setMetadata([
            'version' => '2.0',
            'tables' => ['pages', 'tt_content'],
            'source' => 'migration',
            'custom_field' => 'custom_value with special chars äöü',
        ]);

        $snapshot->setMigrationVersion('migration_v2.1.0');

        $this->connectionMock->expects(self::once())
            ->method('insert')
            ->with('tx_t3hauler_snapshots', self::callback(function ($data) {
                return is_array($data)
                    && isset($data['identifier'])
                    && isset($data['table_name'])
                    && isset($data['hash'])
                    && isset($data['created_at'])
                    && isset($data['metadata'])
                    && isset($data['migration_version']);
            }));

        $this->connectionMock->method('lastInsertId')->willReturn('42');

        $result = $this->subject->save($snapshot);

        self::assertEquals(42, $result->getUid());
    }

    #[Test]
    public function deleteRemovesSnapshotWithCorrectParameters(): void
    {
        $snapshot = $this->createDataSnapshot();
        $snapshot->setUid(555);

        $this->connectionMock->expects(self::once())
            ->method('delete')
            ->with(
                'tx_t3hauler_snapshots',
                ['uid' => 555],
                ['uid' => Connection::PARAM_INT]
            );

        $this->subject->delete($snapshot);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function uidDataProvider(): array
    {
        return [
            'small_uid' => [1],
            'medium_uid' => [12345],
            'large_uid' => [999999],
        ];
    }

    #[Test]
    #[DataProvider('uidDataProvider')]
    public function deleteHandlesVariousUidValues(int $uid): void
    {
        $snapshot = $this->createDataSnapshot();
        $snapshot->setUid($uid);

        $this->connectionMock->expects(self::once())
            ->method('delete')
            ->with(
                'tx_t3hauler_snapshots',
                ['uid' => $uid],
                ['uid' => Connection::PARAM_INT]
            );

        $this->subject->delete($snapshot);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function identifierDataProvider(): array
    {
        return [
            'simple_identifier' => ['simple-id'],
            'complex_identifier' => ['snapshot_2023_01_01_12_34_56_abc123'],
            'uuid_like_identifier' => ['550e8400-e29b-41d4-a716-446655440000'],
            'empty_string' => [''],
            'special_chars' => ['test@#$%^&*()'],
        ];
    }

    #[Test]
    #[DataProvider('identifierDataProvider')]
    public function saveAcceptsVariousIdentifiers(string $identifier): void
    {
        $snapshot = new DataSnapshot(
            $identifier,
            'test_table',
            'test-hash'
        );

        $this->connectionMock->expects(self::once())
            ->method('insert')
            ->with('tx_t3hauler_snapshots', self::callback(function ($data) use ($identifier) {
                return is_array($data) && $data['identifier'] === $identifier;
            }));

        $this->connectionMock->method('lastInsertId')->willReturn('1');

        $this->subject->save($snapshot);
    }

    #[Test]
    public function savePreservesCreatedAtTimestamp(): void
    {
        $createdAt = new \DateTimeImmutable('2023-01-01 12:34:56');
        $snapshot = new DataSnapshot(
            'test-id',
            'test_table',
            'test-hash',
            $createdAt
        );

        $this->connectionMock->expects(self::once())
            ->method('insert')
            ->with('tx_t3hauler_snapshots', self::callback(function ($data) use ($createdAt) {
                return is_array($data) && $data['created_at'] === $createdAt->getTimestamp();
            }));

        $this->connectionMock->method('lastInsertId')->willReturn('1');

        $this->subject->save($snapshot);
    }

    #[Test]
    public function saveHandlesMetadataCorrectly(): void
    {
        $snapshot = $this->createDataSnapshot();
        $metadata = [
            'test_key' => 'test_value',
            'number' => 42,
            'array' => ['item1', 'item2'],
            'boolean' => true,
        ];
        $snapshot->setMetadata($metadata);

        $this->connectionMock->expects(self::once())
            ->method('insert')
            ->with('tx_t3hauler_snapshots', self::callback(function ($data) {
                return is_array($data) && isset($data['metadata']) && is_string($data['metadata']);
            }));

        $this->connectionMock->method('lastInsertId')->willReturn('1');

        $this->subject->save($snapshot);
    }

    #[Test]
    public function saveHandlesMigrationVersionCorrectly(): void
    {
        $snapshot = $this->createDataSnapshot();
        $migrationVersion = 'v2.1.0-final';
        $snapshot->setMigrationVersion($migrationVersion);

        $this->connectionMock->expects(self::once())
            ->method('insert')
            ->with('tx_t3hauler_snapshots', self::callback(function ($data) use ($migrationVersion) {
                return is_array($data) && $data['migration_version'] === $migrationVersion;
            }));

        $this->connectionMock->method('lastInsertId')->willReturn('1');

        $this->subject->save($snapshot);
    }

    #[Test]
    public function updateDoesNotIncludeUidInDataArray(): void
    {
        $snapshot = $this->createDataSnapshot();
        $snapshot->setUid(999);

        $this->connectionMock->expects(self::once())
            ->method('update')
            ->with(
                'tx_t3hauler_snapshots',
                self::callback(function ($data) {
                    return is_array($data) && !array_key_exists('uid', $data);
                }),
                ['uid' => 999],
                ['uid' => Connection::PARAM_INT]
            );

        $this->subject->save($snapshot);
    }

    #[Test]
    public function findByIdentifierReturnsNullWhenNotFound(): void
    {
        $queryBuilder = $this->createMock(\TYPO3\CMS\Core\Database\Query\QueryBuilder::class);
        $result = $this->createMock(\Doctrine\DBAL\Result::class);

        $this->connectionMock->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $queryBuilder->expects(self::once())
            ->method('select')
            ->with('*')
            ->willReturn($queryBuilder);

        $queryBuilder->expects(self::once())
            ->method('from')
            ->with('tx_t3hauler_snapshots')
            ->willReturn($queryBuilder);

        $queryBuilder->expects(self::once())
            ->method('andWhere')
            ->willReturn($queryBuilder);

        $queryBuilder->expects(self::once())
            ->method('executeQuery')
            ->willReturn($result);

        $result->expects(self::once())
            ->method('fetchAllAssociative')
            ->willReturn([]);

        $snapshot = $this->subject->findByIdentifier('non-existent');

        self::assertNull($snapshot);
    }

    #[Test]
    public function findByIdentifierReturnsSnapshot(): void
    {
        $row = [
            'uid' => 1,
            'identifier' => 'test-snapshot',
            'table_name' => 'test_table',
            'hash' => 'abc123',
            'created_at' => time(),
            'metadata' => '{}',
            'migration_version' => null,
        ];

        $queryBuilder = $this->createMock(\TYPO3\CMS\Core\Database\Query\QueryBuilder::class);
        $result = $this->createMock(\Doctrine\DBAL\Result::class);

        $this->connectionMock->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $queryBuilder->expects(self::once())
            ->method('select')
            ->with('*')
            ->willReturn($queryBuilder);

        $queryBuilder->expects(self::once())
            ->method('from')
            ->with('tx_t3hauler_snapshots')
            ->willReturn($queryBuilder);

        $queryBuilder->expects(self::once())
            ->method('andWhere')
            ->willReturn($queryBuilder);

        $queryBuilder->expects(self::once())
            ->method('executeQuery')
            ->willReturn($result);

        $result->expects(self::once())
            ->method('fetchAllAssociative')
            ->willReturn([$row]);

        $snapshot = $this->subject->findByIdentifier('test-snapshot');

        self::assertInstanceOf(DataSnapshot::class, $snapshot);
        self::assertEquals('test-snapshot', $snapshot->getIdentifier());
    }

    #[Test]
    public function findLatestByTableNameReturnsLatestSnapshot(): void
    {
        $row = [
            'uid' => 1,
            'identifier' => 'test-snapshot',
            'table_name' => 'test_table',
            'hash' => 'abc123',
            'created_at' => time(),
            'metadata' => '{}',
            'migration_version' => null,
        ];

        $queryBuilder = $this->createMock(\TYPO3\CMS\Core\Database\Query\QueryBuilder::class);
        $result = $this->createMock(\Doctrine\DBAL\Result::class);

        $this->connectionMock->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $queryBuilder->expects(self::once())
            ->method('select')
            ->with('*')
            ->willReturn($queryBuilder);

        $queryBuilder->expects(self::once())
            ->method('from')
            ->with('tx_t3hauler_snapshots')
            ->willReturn($queryBuilder);

        $queryBuilder->expects(self::once())
            ->method('andWhere')
            ->willReturn($queryBuilder);

        $queryBuilder->expects(self::once())
            ->method('orderBy')
            ->with('created_at', 'DESC')
            ->willReturn($queryBuilder);

        $queryBuilder->expects(self::once())
            ->method('setMaxResults')
            ->with(1)
            ->willReturn($queryBuilder);

        $queryBuilder->expects(self::once())
            ->method('executeQuery')
            ->willReturn($result);

        $result->expects(self::once())
            ->method('fetchAllAssociative')
            ->willReturn([$row]);

        $snapshot = $this->subject->findLatestByTableName('test_table');

        self::assertInstanceOf(DataSnapshot::class, $snapshot);
        self::assertEquals('test_table', $snapshot->getTableName());
    }

    #[Test]
    public function findByTableNameReturnsArrayOfSnapshots(): void
    {
        $rows = [
            [
                'uid' => 1,
                'identifier' => 'test-snapshot-1',
                'table_name' => 'test_table',
                'hash' => 'abc123',
                'created_at' => time(),
                'metadata' => '{}',
                'migration_version' => null,
            ],
            [
                'uid' => 2,
                'identifier' => 'test-snapshot-2',
                'table_name' => 'test_table',
                'hash' => 'def456',
                'created_at' => time() - 3600,
                'metadata' => '{}',
                'migration_version' => null,
            ],
        ];

        $queryBuilder = $this->createMock(\TYPO3\CMS\Core\Database\Query\QueryBuilder::class);
        $result = $this->createMock(\Doctrine\DBAL\Result::class);

        $this->connectionMock->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $queryBuilder->expects(self::once())
            ->method('select')
            ->with('*')
            ->willReturn($queryBuilder);

        $queryBuilder->expects(self::once())
            ->method('from')
            ->with('tx_t3hauler_snapshots')
            ->willReturn($queryBuilder);

        $queryBuilder->expects(self::once())
            ->method('andWhere')
            ->willReturn($queryBuilder);

        $queryBuilder->expects(self::once())
            ->method('orderBy')
            ->with('created_at', 'DESC')
            ->willReturn($queryBuilder);

        $queryBuilder->expects(self::once())
            ->method('executeQuery')
            ->willReturn($result);

        $result->expects(self::once())
            ->method('fetchAllAssociative')
            ->willReturn($rows);

        $snapshots = $this->subject->findByTableName('test_table');

        self::assertCount(2, $snapshots);
        self::assertInstanceOf(DataSnapshot::class, $snapshots[0]);
        self::assertInstanceOf(DataSnapshot::class, $snapshots[1]);
    }

    #[Test]
    public function findByMigrationVersionReturnsSnapshots(): void
    {
        $rows = [
            [
                'uid' => 1,
                'identifier' => 'test-snapshot-1',
                'table_name' => 'test_table',
                'hash' => 'abc123',
                'created_at' => time(),
                'metadata' => '{}',
                'migration_version' => 'v1.0.0',
            ],
        ];

        $queryBuilder = $this->createMock(\TYPO3\CMS\Core\Database\Query\QueryBuilder::class);
        $result = $this->createMock(\Doctrine\DBAL\Result::class);

        $this->connectionMock->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $queryBuilder->expects(self::once())
            ->method('select')
            ->with('*')
            ->willReturn($queryBuilder);

        $queryBuilder->expects(self::once())
            ->method('from')
            ->with('tx_t3hauler_snapshots')
            ->willReturn($queryBuilder);

        $queryBuilder->expects(self::once())
            ->method('andWhere')
            ->willReturn($queryBuilder);

        $queryBuilder->expects(self::once())
            ->method('orderBy')
            ->with('created_at', 'ASC')
            ->willReturn($queryBuilder);

        $queryBuilder->expects(self::once())
            ->method('executeQuery')
            ->willReturn($result);

        $result->expects(self::once())
            ->method('fetchAllAssociative')
            ->willReturn($rows);

        $snapshots = $this->subject->findByMigrationVersion('v1.0.0');

        self::assertCount(1, $snapshots);
        self::assertInstanceOf(DataSnapshot::class, $snapshots[0]);
    }

    #[Test]
    public function findAllReturnsAllSnapshots(): void
    {
        $rows = [
            [
                'uid' => 1,
                'identifier' => 'test-snapshot-1',
                'table_name' => 'test_table',
                'hash' => 'abc123',
                'created_at' => time(),
                'metadata' => '{}',
                'migration_version' => null,
            ],
        ];

        $queryBuilder = $this->createMock(\TYPO3\CMS\Core\Database\Query\QueryBuilder::class);
        $result = $this->createMock(\Doctrine\DBAL\Result::class);

        $this->connectionMock->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $queryBuilder->expects(self::once())
            ->method('select')
            ->with('*')
            ->willReturn($queryBuilder);

        $queryBuilder->expects(self::once())
            ->method('from')
            ->with('tx_t3hauler_snapshots')
            ->willReturn($queryBuilder);

        $queryBuilder->expects(self::once())
            ->method('orderBy')
            ->with('created_at', 'DESC')
            ->willReturn($queryBuilder);

        $queryBuilder->expects(self::once())
            ->method('executeQuery')
            ->willReturn($result);

        $result->expects(self::once())
            ->method('fetchAllAssociative')
            ->willReturn($rows);

        $snapshots = $this->subject->findAll();

        self::assertCount(1, $snapshots);
        self::assertInstanceOf(DataSnapshot::class, $snapshots[0]);
    }

    #[Test]
    public function deleteSnapshotsOlderThanDeletesOldSnapshots(): void
    {
        $cutoffDate = new \DateTimeImmutable('2023-01-01');

        $queryBuilder = $this->createMock(\TYPO3\CMS\Core\Database\Query\QueryBuilder::class);

        $this->connectionMock->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $queryBuilder->expects(self::once())
            ->method('delete')
            ->with('tx_t3hauler_snapshots')
            ->willReturn($queryBuilder);

        $queryBuilder->expects(self::once())
            ->method('where')
            ->willReturn($queryBuilder);

        $queryBuilder->expects(self::once())
            ->method('executeStatement')
            ->willReturn(5);

        $deletedCount = $this->subject->deleteSnapshotsOlderThan($cutoffDate);

        self::assertEquals(5, $deletedCount);
    }

    #[Test]
    public function deleteByTableNameDeletesSnapshotsForTable(): void
    {
        $queryBuilder = $this->createMock(\TYPO3\CMS\Core\Database\Query\QueryBuilder::class);

        $this->connectionMock->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $queryBuilder->expects(self::once())
            ->method('delete')
            ->with('tx_t3hauler_snapshots')
            ->willReturn($queryBuilder);

        $queryBuilder->expects(self::once())
            ->method('andWhere')
            ->willReturn($queryBuilder);

        $queryBuilder->expects(self::once())
            ->method('executeStatement')
            ->willReturn(3);

        $deletedCount = $this->subject->deleteByTableName('test_table');

        self::assertEquals(3, $deletedCount);
    }

    #[Test]
    public function existsByIdentifierReturnsTrueWhenExists(): void
    {
        $queryBuilder = $this->createMock(\TYPO3\CMS\Core\Database\Query\QueryBuilder::class);
        $result = $this->createMock(\Doctrine\DBAL\Result::class);

        $this->connectionMock->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $queryBuilder->expects(self::once())
            ->method('count')
            ->with('uid')
            ->willReturn($queryBuilder);

        $queryBuilder->expects(self::once())
            ->method('from')
            ->with('tx_t3hauler_snapshots')
            ->willReturn($queryBuilder);

        $queryBuilder->expects(self::once())
            ->method('andWhere')
            ->willReturn($queryBuilder);

        $queryBuilder->expects(self::once())
            ->method('executeQuery')
            ->willReturn($result);

        $result->expects(self::once())
            ->method('fetchOne')
            ->willReturn(1);

        $exists = $this->subject->existsByIdentifier('test-snapshot');

        self::assertTrue($exists);
    }

    #[Test]
    public function existsByIdentifierReturnsFalseWhenNotExists(): void
    {
        $queryBuilder = $this->createMock(\TYPO3\CMS\Core\Database\Query\QueryBuilder::class);
        $result = $this->createMock(\Doctrine\DBAL\Result::class);

        $this->connectionMock->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $queryBuilder->expects(self::once())
            ->method('count')
            ->with('uid')
            ->willReturn($queryBuilder);

        $queryBuilder->expects(self::once())
            ->method('from')
            ->with('tx_t3hauler_snapshots')
            ->willReturn($queryBuilder);

        $queryBuilder->expects(self::once())
            ->method('andWhere')
            ->willReturn($queryBuilder);

        $queryBuilder->expects(self::once())
            ->method('executeQuery')
            ->willReturn($result);

        $result->expects(self::once())
            ->method('fetchOne')
            ->willReturn(0);

        $exists = $this->subject->existsByIdentifier('non-existent');

        self::assertFalse($exists);
    }

    #[Test]
    public function countByTableNameReturnsCount(): void
    {
        $queryBuilder = $this->createMock(\TYPO3\CMS\Core\Database\Query\QueryBuilder::class);
        $result = $this->createMock(\Doctrine\DBAL\Result::class);

        $this->connectionMock->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $queryBuilder->expects(self::once())
            ->method('count')
            ->with('uid')
            ->willReturn($queryBuilder);

        $queryBuilder->expects(self::once())
            ->method('from')
            ->with('tx_t3hauler_snapshots')
            ->willReturn($queryBuilder);

        $queryBuilder->expects(self::once())
            ->method('andWhere')
            ->willReturn($queryBuilder);

        $queryBuilder->expects(self::once())
            ->method('executeQuery')
            ->willReturn($result);

        $result->expects(self::once())
            ->method('fetchOne')
            ->willReturn(5);

        $count = $this->subject->countByTableName('test_table');

        self::assertEquals(5, $count);
    }

    #[Test]
    public function findCurrentSnapshotReturnsCurrentSnapshot(): void
    {
        $row = [
            'uid' => 1,
            'identifier' => 'current-snapshot',
            'table_name' => 'tx_t3hauler_snapshots',
            'hash' => 'abc123',
            'created_at' => time(),
            'metadata' => '{}',
            'migration_version' => null,
        ];

        $queryBuilder = $this->createMock(\TYPO3\CMS\Core\Database\Query\QueryBuilder::class);
        $result = $this->createMock(\Doctrine\DBAL\Result::class);

        $this->connectionMock->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $queryBuilder->expects(self::once())
            ->method('select')
            ->with('*')
            ->willReturn($queryBuilder);

        $queryBuilder->expects(self::once())
            ->method('from')
            ->with('tx_t3hauler_snapshots')
            ->willReturn($queryBuilder);

        $queryBuilder->expects(self::once())
            ->method('andWhere')
            ->willReturn($queryBuilder);

        $queryBuilder->expects(self::once())
            ->method('orderBy')
            ->with('created_at', 'DESC')
            ->willReturn($queryBuilder);

        $queryBuilder->expects(self::once())
            ->method('setMaxResults')
            ->with(1)
            ->willReturn($queryBuilder);

        $queryBuilder->expects(self::once())
            ->method('executeQuery')
            ->willReturn($result);

        $result->expects(self::once())
            ->method('fetchAllAssociative')
            ->willReturn([$row]);

        $snapshot = $this->subject->findCurrentSnapshot();

        self::assertInstanceOf(DataSnapshot::class, $snapshot);
        self::assertEquals('current-snapshot', $snapshot->getIdentifier());
    }

    private function createDataSnapshot(): DataSnapshot
    {
        return new DataSnapshot(
            'test-snapshot-identifier',
            'test_table',
            'abc123hash'
        );
    }
}
