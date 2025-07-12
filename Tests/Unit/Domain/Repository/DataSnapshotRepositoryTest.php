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

    private function createDataSnapshot(): DataSnapshot
    {
        return new DataSnapshot(
            'test-snapshot-identifier',
            'test_table',
            'abc123hash'
        );
    }
}
