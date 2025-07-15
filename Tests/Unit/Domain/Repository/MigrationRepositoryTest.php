<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Tests\Unit\Domain\Repository;

use Cpsit\T3hauler\Domain\Model\Migration;
use Cpsit\T3hauler\Domain\Repository\MigrationRepository;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Expression\ExpressionBuilder;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

/**
 * Unit tests for MigrationRepository
 */
final class MigrationRepositoryTest extends TestCase
{
    private MigrationRepository $subject;
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
            ->with('tx_t3hauler_migrations')
            ->willReturn($this->connectionMock);

        $this->subject = new MigrationRepository($this->connectionPoolMock);
    }

    #[Test]
    public function constructorSetsConnectionPool(): void
    {
        $repository = new MigrationRepository($this->connectionPoolMock);

        self::assertInstanceOf(MigrationRepository::class, $repository);
    }

    #[Test]
    public function saveInsertsNewMigrationWhenUidIsNull(): void
    {
        $migration = $this->createMigration();
        $migrationData = $migration->toArray();
        unset($migrationData['uid']);

        $this->connectionMock->expects(self::once())
            ->method('insert')
            ->with('tx_t3hauler_migrations', $migrationData);

        $this->connectionMock->expects(self::once())
            ->method('lastInsertId')
            ->willReturn('123');

        $result = $this->subject->save($migration);

        self::assertSame($migration, $result);
        self::assertEquals(123, $result->getUid());
    }

    #[Test]
    public function saveUpdatesExistingMigrationWhenUidExists(): void
    {
        $migration = $this->createMigration();
        $migration->setUid(456);
        $migrationData = $migration->toArray();

        $this->connectionMock->expects(self::once())
            ->method('update')
            ->with('tx_t3hauler_migrations', $migrationData, ['uid' => 456]);

        $result = $this->subject->save($migration);

        self::assertSame($migration, $result);
    }

    #[Test]
    public function deleteReturnsFalseWhenMigrationHasNoUid(): void
    {
        $migration = $this->createMigration();

        $result = $this->subject->delete($migration);

        self::assertFalse($result);
    }

    #[Test]
    public function deleteReturnsTrueWhenMigrationIsDeleted(): void
    {
        $migration = $this->createMigration();
        $migration->setUid(789);

        $this->connectionMock->expects(self::once())
            ->method('delete')
            ->with('tx_t3hauler_migrations', ['uid' => 789])
            ->willReturn(1);

        $result = $this->subject->delete($migration);

        self::assertTrue($result);
    }

    #[Test]
    public function deleteReturnsFalseWhenNoRowsAffected(): void
    {
        $migration = $this->createMigration();
        $migration->setUid(789);

        $this->connectionMock->expects(self::once())
            ->method('delete')
            ->with('tx_t3hauler_migrations', ['uid' => 789])
            ->willReturn(0);

        $result = $this->subject->delete($migration);

        self::assertFalse($result);
    }

    #[Test]
    public function deleteByMigrationIdReturnsTrueWhenMigrationIsDeleted(): void
    {
        $this->connectionMock->expects(self::once())
            ->method('delete')
            ->with('tx_t3hauler_migrations', ['migration_id' => 'test-id'])
            ->willReturn(1);

        $result = $this->subject->deleteByMigrationId('test-id');

        self::assertTrue($result);
    }

    #[Test]
    public function deleteByMigrationIdReturnsFalseWhenNoRowsAffected(): void
    {
        $this->connectionMock->expects(self::once())
            ->method('delete')
            ->with('tx_t3hauler_migrations', ['migration_id' => 'test-id'])
            ->willReturn(0);

        $result = $this->subject->deleteByMigrationId('test-id');

        self::assertFalse($result);
    }

    #[Test]
    public function saveHandlesLastInsertIdAsString(): void
    {
        $migration = $this->createMigration();

        $this->connectionMock->expects(self::once())
            ->method('insert');

        $this->connectionMock->expects(self::once())
            ->method('lastInsertId')
            ->willReturn('999');

        $result = $this->subject->save($migration);

        self::assertEquals(999, $result->getUid());
    }

    #[Test]
    public function saveHandlesEmptyLastInsertId(): void
    {
        $migration = $this->createMigration();

        $this->connectionMock->expects(self::once())
            ->method('insert');

        $this->connectionMock->expects(self::once())
            ->method('lastInsertId')
            ->willReturn('0');

        $result = $this->subject->save($migration);

        self::assertEquals(0, $result->getUid());
    }

    #[Test]
    public function savePreservesOriginalMigrationOnUpdate(): void
    {
        $migration = $this->createMigration();
        $migration->setUid(123);
        $originalId = $migration->getMigrationId();

        $this->connectionMock->method('update')
            ->willReturn(1);

        $result = $this->subject->save($migration);

        self::assertSame($migration, $result);
        self::assertEquals($originalId, $result->getMigrationId());
    }

    #[Test]
    public function deleteWithNullUidReturnsFalse(): void
    {
        $migration = $this->createMigration();
        // Uid is null by default

        $result = $this->subject->delete($migration);

        self::assertFalse($result);
    }

    #[Test]
    public function saveInsertsCorrectDataForNewMigration(): void
    {
        $migration = new Migration(
            'test-id',
            'Test Name',
            'Test Description',
            'Test Author',
            'test-hash',
            '/test/file.json'
        );

        $expectedData = $migration->toArray();
        unset($expectedData['uid']);

        $this->connectionMock->expects(self::once())
            ->method('insert')
            ->with('tx_t3hauler_migrations', $expectedData);

        $this->connectionMock->method('lastInsertId')
            ->willReturn('1');

        $this->subject->save($migration);
    }

    #[Test]
    public function saveUpdatesCorrectDataForExistingMigration(): void
    {
        $migration = new Migration(
            'test-id',
            'Test Name',
            'Test Description',
            'Test Author',
            'test-hash',
            '/test/file.json'
        );
        $migration->setUid(42);

        $expectedData = $migration->toArray();

        $this->connectionMock->expects(self::once())
            ->method('update')
            ->with('tx_t3hauler_migrations', $expectedData, ['uid' => 42]);

        $this->subject->save($migration);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function deleteRowCountDataProvider(): array
    {
        return [
            'single_row_deleted' => [1],
            'multiple_rows_deleted' => [3],
            'no_rows_deleted' => [0],
        ];
    }

    #[Test]
    #[DataProvider('deleteRowCountDataProvider')]
    public function deleteReturnsCorrectBooleanBasedOnAffectedRows(int $affectedRows): void
    {
        $migration = $this->createMigration();
        $migration->setUid(123);

        $this->connectionMock->expects(self::once())
            ->method('delete')
            ->willReturn($affectedRows);

        $result = $this->subject->delete($migration);

        self::assertEquals($affectedRows > 0, $result);
    }

    #[Test]
    #[DataProvider('deleteRowCountDataProvider')]
    public function deleteByMigrationIdReturnsCorrectBooleanBasedOnAffectedRows(int $affectedRows): void
    {
        $this->connectionMock->expects(self::once())
            ->method('delete')
            ->willReturn($affectedRows);

        $result = $this->subject->deleteByMigrationId('test-id');

        self::assertEquals($affectedRows > 0, $result);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function migrationIdDataProvider(): array
    {
        return [
            'simple_id' => ['simple-id'],
            'complex_id' => ['migration_2023_01_01_12_34_56_abc123'],
            'uuid_like_id' => ['550e8400-e29b-41d4-a716-446655440000'],
            'empty_string' => [''],
            'special_chars' => ['test@#$%^&*()'],
        ];
    }

    #[Test]
    #[DataProvider('migrationIdDataProvider')]
    public function deleteByMigrationIdAcceptsVariousMigrationIds(string $migrationId): void
    {
        $this->connectionMock->expects(self::once())
            ->method('delete')
            ->with('tx_t3hauler_migrations', ['migration_id' => $migrationId])
            ->willReturn(1);

        $result = $this->subject->deleteByMigrationId($migrationId);

        self::assertTrue($result);
    }

    #[Test]
    public function connectionPoolIsCalledWithCorrectTableName(): void
    {
        $this->connectionPoolMock->expects(self::atLeastOnce())
            ->method('getConnectionForTable')
            ->with('tx_t3hauler_migrations');

        $migration = $this->createMigration();
        $migration->setUid(123); // Set uid so delete() actually calls the connection

        $this->connectionMock->method('delete')->willReturn(1);

        $this->subject->delete($migration);
    }

    #[Test]
    public function saveMaintainsMigrationIntegrity(): void
    {
        $migration = $this->createMigration();
        $originalHash = $migration->getSourceHash();
        $originalAuthor = $migration->getAuthor();

        $this->connectionMock->method('insert');
        $this->connectionMock->method('lastInsertId')->willReturn('1');

        $result = $this->subject->save($migration);

        self::assertSame($migration, $result);
        self::assertEquals($originalHash, $result->getSourceHash());
        self::assertEquals($originalAuthor, $result->getAuthor());
    }

    #[Test]
    public function saveHandlesComplexMigrationData(): void
    {
        $migration = new Migration(
            'complex-migration-id-with-special-chars-@#$%',
            'Complex Migration Name with Special Characters äöü',
            'A very detailed description\nwith multiple lines\nand special characters: äöü ñ',
            'Complex Author Name <email@domain.com>',
            'sha256:abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890',
            '/very/long/path/to/migration/data/file/with/special-chars-äöü.json'
        );

        $migration->setMetadata([
            'version' => '2.0',
            'tags' => ['important', 'production'],
            'custom_field' => 'custom_value',
        ]);

        $this->connectionMock->expects(self::once())
            ->method('insert')
            ->with('tx_t3hauler_migrations', self::callback(function ($data) {
                return is_array($data)
                    && isset($data['migration_id'])
                    && isset($data['name'])
                    && isset($data['description'])
                    && isset($data['author'])
                    && isset($data['source_hash'])
                    && isset($data['data_file'])
                    && isset($data['metadata']);
            }));

        $this->connectionMock->method('lastInsertId')->willReturn('42');

        $result = $this->subject->save($migration);

        self::assertEquals(42, $result->getUid());
    }

    #[Test]
    public function findByMigrationIdReturnsNullWhenNotFound(): void
    {
        $queryBuilder = $this->createQueryBuilderMock();
        $result = $this->createMock(Result::class);

        $queryBuilder->expects(self::once())
            ->method('select')
            ->with('*')
            ->willReturnSelf();

        $queryBuilder->expects(self::once())
            ->method('from')
            ->with('tx_t3hauler_migrations')
            ->willReturnSelf();

        $queryBuilder->expects(self::once())
            ->method('where')
            ->willReturnSelf();

        $queryBuilder->expects(self::once())
            ->method('executeQuery')
            ->willReturn($result);

        $result->expects(self::once())
            ->method('fetchAssociative')
            ->willReturn(false);

        $this->connectionMock->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $migration = $this->subject->findByMigrationId('nonexistent-id');

        self::assertNull($migration);
    }

    #[Test]
    public function findByMigrationIdReturnsMigrationWhenFound(): void
    {
        $migrationData = [
            'uid' => 123,
            'migration_id' => 'test-id',
            'name' => 'Test Migration',
            'description' => 'Test Description',
            'author' => 'Test Author',
            'source_hash' => 'abc123',
            'data_file' => '/path/to/data.json',
            'status' => 'pending',
            'created_at' => time(),
            'updated_at' => time(),
            'metadata' => '{}',
        ];

        $queryBuilder = $this->createQueryBuilderMock();
        $result = $this->createMock(Result::class);

        $queryBuilder->expects(self::once())
            ->method('select')
            ->with('*')
            ->willReturnSelf();

        $queryBuilder->expects(self::once())
            ->method('from')
            ->with('tx_t3hauler_migrations')
            ->willReturnSelf();

        $queryBuilder->expects(self::once())
            ->method('where')
            ->willReturnSelf();

        $queryBuilder->expects(self::once())
            ->method('executeQuery')
            ->willReturn($result);

        $result->expects(self::once())
            ->method('fetchAssociative')
            ->willReturn($migrationData);

        $this->connectionMock->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $migration = $this->subject->findByMigrationId('test-id');

        self::assertInstanceOf(Migration::class, $migration);
        self::assertEquals('test-id', $migration->getMigrationId());
        self::assertEquals('Test Migration', $migration->getName());
    }

    #[Test]
    public function findByUidReturnsNullWhenNotFound(): void
    {
        $queryBuilder = $this->createQueryBuilderMock();
        $result = $this->createMock(Result::class);

        $queryBuilder->expects(self::once())
            ->method('select')
            ->with('*')
            ->willReturnSelf();

        $queryBuilder->expects(self::once())
            ->method('from')
            ->with('tx_t3hauler_migrations')
            ->willReturnSelf();

        $queryBuilder->expects(self::once())
            ->method('where')
            ->willReturnSelf();

        $queryBuilder->expects(self::once())
            ->method('executeQuery')
            ->willReturn($result);

        $result->expects(self::once())
            ->method('fetchAssociative')
            ->willReturn(false);

        $this->connectionMock->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $migration = $this->subject->findByUid(999);

        self::assertNull($migration);
    }

    #[Test]
    public function findByUidReturnsMigrationWhenFound(): void
    {
        $migrationData = [
            'uid' => 123,
            'migration_id' => 'test-id',
            'name' => 'Test Migration',
            'description' => 'Test Description',
            'author' => 'Test Author',
            'source_hash' => 'abc123',
            'data_file' => '/path/to/data.json',
            'status' => 'pending',
            'created_at' => time(),
            'updated_at' => time(),
            'metadata' => '{}',
        ];

        $queryBuilder = $this->createQueryBuilderMock();
        $result = $this->createMock(Result::class);

        $queryBuilder->expects(self::once())
            ->method('select')
            ->with('*')
            ->willReturnSelf();

        $queryBuilder->expects(self::once())
            ->method('from')
            ->with('tx_t3hauler_migrations')
            ->willReturnSelf();

        $queryBuilder->expects(self::once())
            ->method('where')
            ->willReturnSelf();

        $queryBuilder->expects(self::once())
            ->method('executeQuery')
            ->willReturn($result);

        $result->expects(self::once())
            ->method('fetchAssociative')
            ->willReturn($migrationData);

        $this->connectionMock->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $migration = $this->subject->findByUid(123);

        self::assertInstanceOf(Migration::class, $migration);
        self::assertEquals(123, $migration->getUid());
        self::assertEquals('test-id', $migration->getMigrationId());
    }

    #[Test]
    public function findAllReturnsEmptyArrayWhenNoMigrations(): void
    {
        $queryBuilder = $this->createQueryBuilderMock();
        $result = $this->createMock(Result::class);

        $queryBuilder->expects(self::once())
            ->method('select')
            ->with('*')
            ->willReturnSelf();

        $queryBuilder->expects(self::once())
            ->method('from')
            ->with('tx_t3hauler_migrations')
            ->willReturnSelf();

        $queryBuilder->expects(self::once())
            ->method('orderBy')
            ->with('created_at', 'DESC')
            ->willReturnSelf();

        $queryBuilder->expects(self::once())
            ->method('executeQuery')
            ->willReturn($result);

        $result->expects(self::once())
            ->method('fetchAllAssociative')
            ->willReturn([]);

        $this->connectionMock->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $migrations = $this->subject->findAll();

        self::assertEmpty($migrations);
    }

    #[Test]
    public function findAllReturnsMigrationsArray(): void
    {
        $migrationData = [
            [
                'uid' => 123,
                'migration_id' => 'test-id-1',
                'name' => 'Test Migration 1',
                'description' => 'Test Description 1',
                'author' => 'Test Author',
                'source_hash' => 'abc123',
                'data_file' => '/path/to/data1.json',
                'status' => 'pending',
                'created_at' => time(),
                'updated_at' => time(),
                'metadata' => '{}',
            ],
            [
                'uid' => 124,
                'migration_id' => 'test-id-2',
                'name' => 'Test Migration 2',
                'description' => 'Test Description 2',
                'author' => 'Test Author',
                'source_hash' => 'def456',
                'data_file' => '/path/to/data2.json',
                'status' => 'applied',
                'created_at' => time(),
                'updated_at' => time(),
                'metadata' => '{}',
            ],
        ];

        $queryBuilder = $this->createQueryBuilderMock();
        $result = $this->createMock(Result::class);

        $queryBuilder->expects(self::once())
            ->method('select')
            ->with('*')
            ->willReturnSelf();

        $queryBuilder->expects(self::once())
            ->method('from')
            ->with('tx_t3hauler_migrations')
            ->willReturnSelf();

        $queryBuilder->expects(self::once())
            ->method('orderBy')
            ->with('created_at', 'DESC')
            ->willReturnSelf();

        $queryBuilder->expects(self::once())
            ->method('executeQuery')
            ->willReturn($result);

        $result->expects(self::once())
            ->method('fetchAllAssociative')
            ->willReturn($migrationData);

        $this->connectionMock->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $migrations = $this->subject->findAll();

        self::assertCount(2, $migrations);
        self::assertContainsOnlyInstancesOf(Migration::class, $migrations);
        self::assertEquals('test-id-1', $migrations[0]->getMigrationId());
        self::assertEquals('test-id-2', $migrations[1]->getMigrationId());
    }

    #[Test]
    public function findAllAcceptsCustomOrderingParameters(): void
    {
        $queryBuilder = $this->createQueryBuilderMock();
        $result = $this->createMock(Result::class);

        $queryBuilder->expects(self::once())
            ->method('select')
            ->with('*')
            ->willReturnSelf();

        $queryBuilder->expects(self::once())
            ->method('from')
            ->with('tx_t3hauler_migrations')
            ->willReturnSelf();

        $queryBuilder->expects(self::once())
            ->method('orderBy')
            ->with('name', 'ASC')
            ->willReturnSelf();

        $queryBuilder->expects(self::once())
            ->method('executeQuery')
            ->willReturn($result);

        $result->expects(self::once())
            ->method('fetchAllAssociative')
            ->willReturn([]);

        $this->connectionMock->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $this->subject->findAll('name', 'ASC');
    }

    #[Test]
    public function findByStatusReturnsFilteredMigrations(): void
    {
        $migrationData = [
            [
                'uid' => 123,
                'migration_id' => 'test-id',
                'name' => 'Test Migration',
                'description' => 'Test Description',
                'author' => 'Test Author',
                'source_hash' => 'abc123',
                'data_file' => '/path/to/data.json',
                'status' => 'pending',
                'created_at' => time(),
                'updated_at' => time(),
                'metadata' => '{}',
            ],
        ];

        $queryBuilder = $this->createQueryBuilderMock();
        $result = $this->createMock(Result::class);

        $queryBuilder->expects(self::once())
            ->method('select')
            ->with('*')
            ->willReturnSelf();

        $queryBuilder->expects(self::once())
            ->method('from')
            ->with('tx_t3hauler_migrations')
            ->willReturnSelf();

        $queryBuilder->expects(self::once())
            ->method('where')
            ->willReturnSelf();

        $queryBuilder->expects(self::once())
            ->method('orderBy')
            ->with('created_at', 'DESC')
            ->willReturnSelf();

        $queryBuilder->expects(self::once())
            ->method('executeQuery')
            ->willReturn($result);

        $result->expects(self::once())
            ->method('fetchAllAssociative')
            ->willReturn($migrationData);

        $this->connectionMock->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $migrations = $this->subject->findByStatus('pending');

        self::assertCount(1, $migrations);
        self::assertContainsOnlyInstancesOf(Migration::class, $migrations);
        self::assertEquals('pending', $migrations[0]->getStatus());
    }

    #[Test]
    public function findPendingCallsFindByStatusWithPendingStatus(): void
    {
        $queryBuilder = $this->createQueryBuilderMock();
        $result = $this->createMock(Result::class);

        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('executeQuery')->willReturn($result);
        $result->method('fetchAllAssociative')->willReturn([]);

        $this->connectionMock->method('createQueryBuilder')->willReturn($queryBuilder);

        $migrations = $this->subject->findPending();

        self::assertEmpty($migrations);
    }

    #[Test]
    public function findAppliedCallsFindByStatusWithAppliedStatus(): void
    {
        $queryBuilder = $this->createQueryBuilderMock();
        $result = $this->createMock(Result::class);

        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('executeQuery')->willReturn($result);
        $result->method('fetchAllAssociative')->willReturn([]);

        $this->connectionMock->method('createQueryBuilder')->willReturn($queryBuilder);

        $migrations = $this->subject->findApplied();

        self::assertEmpty($migrations);
    }

    #[Test]
    public function findFailedCallsFindByStatusWithFailedStatus(): void
    {
        $queryBuilder = $this->createQueryBuilderMock();
        $result = $this->createMock(Result::class);

        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('executeQuery')->willReturn($result);
        $result->method('fetchAllAssociative')->willReturn([]);

        $this->connectionMock->method('createQueryBuilder')->willReturn($queryBuilder);

        $migrations = $this->subject->findFailed();

        self::assertEmpty($migrations);
    }

    #[Test]
    public function findLatestReturnsNullWhenNoMigrations(): void
    {
        $queryBuilder = $this->createQueryBuilderMock();
        $result = $this->createMock(Result::class);

        $queryBuilder->expects(self::once())
            ->method('select')
            ->with('*')
            ->willReturnSelf();

        $queryBuilder->expects(self::once())
            ->method('from')
            ->with('tx_t3hauler_migrations')
            ->willReturnSelf();

        $queryBuilder->expects(self::once())
            ->method('orderBy')
            ->with('created_at', 'DESC')
            ->willReturnSelf();

        $queryBuilder->expects(self::once())
            ->method('setMaxResults')
            ->with(1)
            ->willReturnSelf();

        $queryBuilder->expects(self::once())
            ->method('executeQuery')
            ->willReturn($result);

        $result->expects(self::once())
            ->method('fetchAssociative')
            ->willReturn(false);

        $this->connectionMock->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $migration = $this->subject->findLatest();

        self::assertNull($migration);
    }

    #[Test]
    public function findLatestReturnsLatestMigration(): void
    {
        $migrationData = [
            'uid' => 123,
            'migration_id' => 'latest-id',
            'name' => 'Latest Migration',
            'description' => 'Latest Description',
            'author' => 'Test Author',
            'source_hash' => 'abc123',
            'data_file' => '/path/to/data.json',
            'status' => 'applied',
            'created_at' => time(),
            'updated_at' => time(),
            'metadata' => '{}',
        ];

        $queryBuilder = $this->createQueryBuilderMock();
        $result = $this->createMock(Result::class);

        $queryBuilder->expects(self::once())
            ->method('select')
            ->with('*')
            ->willReturnSelf();

        $queryBuilder->expects(self::once())
            ->method('from')
            ->with('tx_t3hauler_migrations')
            ->willReturnSelf();

        $queryBuilder->expects(self::once())
            ->method('orderBy')
            ->with('created_at', 'DESC')
            ->willReturnSelf();

        $queryBuilder->expects(self::once())
            ->method('setMaxResults')
            ->with(1)
            ->willReturnSelf();

        $queryBuilder->expects(self::once())
            ->method('executeQuery')
            ->willReturn($result);

        $result->expects(self::once())
            ->method('fetchAssociative')
            ->willReturn($migrationData);

        $this->connectionMock->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $migration = $this->subject->findLatest();

        self::assertInstanceOf(Migration::class, $migration);
        self::assertEquals('latest-id', $migration->getMigrationId());
    }

    #[Test]
    public function existsReturnsFalseWhenMigrationNotFound(): void
    {
        $queryBuilder = $this->createQueryBuilderMock();
        $result = $this->createMock(Result::class);

        $queryBuilder->expects(self::once())
            ->method('count')
            ->with('uid')
            ->willReturnSelf();

        $queryBuilder->expects(self::once())
            ->method('from')
            ->with('tx_t3hauler_migrations')
            ->willReturnSelf();

        $queryBuilder->expects(self::once())
            ->method('where')
            ->willReturnSelf();

        $queryBuilder->expects(self::once())
            ->method('executeQuery')
            ->willReturn($result);

        $result->expects(self::once())
            ->method('fetchOne')
            ->willReturn(0);

        $this->connectionMock->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $exists = $this->subject->exists('nonexistent-id');

        self::assertFalse($exists);
    }

    #[Test]
    public function existsReturnsTrueWhenMigrationFound(): void
    {
        $queryBuilder = $this->createQueryBuilderMock();
        $result = $this->createMock(Result::class);

        $queryBuilder->expects(self::once())
            ->method('count')
            ->with('uid')
            ->willReturnSelf();

        $queryBuilder->expects(self::once())
            ->method('from')
            ->with('tx_t3hauler_migrations')
            ->willReturnSelf();

        $queryBuilder->expects(self::once())
            ->method('where')
            ->willReturnSelf();

        $queryBuilder->expects(self::once())
            ->method('executeQuery')
            ->willReturn($result);

        $result->expects(self::once())
            ->method('fetchOne')
            ->willReturn(1);

        $this->connectionMock->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $exists = $this->subject->exists('existing-id');

        self::assertTrue($exists);
    }

    #[Test]
    public function countByStatusReturnsCount(): void
    {
        $queryBuilder = $this->createQueryBuilderMock();
        $result = $this->createMock(Result::class);

        $queryBuilder->expects(self::once())
            ->method('count')
            ->with('uid')
            ->willReturnSelf();

        $queryBuilder->expects(self::once())
            ->method('from')
            ->with('tx_t3hauler_migrations')
            ->willReturnSelf();

        $queryBuilder->expects(self::once())
            ->method('where')
            ->willReturnSelf();

        $queryBuilder->expects(self::once())
            ->method('executeQuery')
            ->willReturn($result);

        $result->expects(self::once())
            ->method('fetchOne')
            ->willReturn(5);

        $this->connectionMock->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $count = $this->subject->countByStatus('pending');

        self::assertEquals(5, $count);
    }

    #[Test]
    public function countReturnsTotal(): void
    {
        $queryBuilder = $this->createQueryBuilderMock();
        $result = $this->createMock(Result::class);

        $queryBuilder->expects(self::once())
            ->method('count')
            ->with('uid')
            ->willReturnSelf();

        $queryBuilder->expects(self::once())
            ->method('from')
            ->with('tx_t3hauler_migrations')
            ->willReturnSelf();

        $queryBuilder->expects(self::once())
            ->method('executeQuery')
            ->willReturn($result);

        $result->expects(self::once())
            ->method('fetchOne')
            ->willReturn(10);

        $this->connectionMock->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $count = $this->subject->count();

        self::assertEquals(10, $count);
    }

    #[Test]
    public function getSummaryReturnsStatistics(): void
    {
        $queryBuilder = $this->createQueryBuilderMock();
        $result = $this->createMock(Result::class);

        $queryBuilder->method('count')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('executeQuery')->willReturn($result);
        $result->method('fetchOne')->willReturnOnConsecutiveCalls(20, 5, 10, 3, 2);

        $this->connectionMock->method('createQueryBuilder')->willReturn($queryBuilder);

        $summary = $this->subject->getSummary();

        self::assertArrayHasKey('total', $summary);
        self::assertArrayHasKey('total', $summary);
        self::assertArrayHasKey('pending', $summary);
        self::assertArrayHasKey('applied', $summary);
        self::assertArrayHasKey('failed', $summary);
        self::assertArrayHasKey('rolled_back', $summary);
    }

    #[Test]
    public function deleteOlderThanDeletesOldMigrations(): void
    {
        $date = new \DateTime('2023-01-01');
        $queryBuilder = $this->createQueryBuilderMock();

        $queryBuilder->expects(self::once())
            ->method('delete')
            ->with('tx_t3hauler_migrations')
            ->willReturnSelf();

        $queryBuilder->expects(self::once())
            ->method('where')
            ->willReturnSelf();

        $queryBuilder->expects(self::once())
            ->method('executeStatement')
            ->willReturn(3);

        $this->connectionMock->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $deletedCount = $this->subject->deleteOlderThan($date);

        self::assertEquals(3, $deletedCount);
    }

    private function createQueryBuilderMock(): QueryBuilder&MockObject
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $expressionBuilder = $this->createMock(ExpressionBuilder::class);

        $queryBuilder->method('expr')->willReturn($expressionBuilder);
        $expressionBuilder->method('eq')->willReturn('expr');
        $expressionBuilder->method('lt')->willReturn('expr');
        $queryBuilder->method('createNamedParameter')->willReturn('param');

        return $queryBuilder;
    }

    private function createMigration(): Migration
    {
        return new Migration(
            'test-migration-id',
            'Test Migration',
            'A test migration',
            'test-author',
            'abc123',
            '/path/to/data.json'
        );
    }
}
