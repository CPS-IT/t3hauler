<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Tests\Unit\Domain\Model;

use Cpsit\T3hauler\Domain\Model\Migration;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MigrationTest extends TestCase
{
    private Migration $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new Migration(
            'T3H_20241201120000_abc12345',
            'Test Migration',
            'Test migration description',
            'Test Author',
            'test_source_hash',
            'test_migration.t3d'
        );
    }

    #[Test]
    public function constructorSetsPropertiesCorrectly(): void
    {
        self::assertSame('T3H_20241201120000_abc12345', $this->subject->getMigrationId());
        self::assertSame('Test Migration', $this->subject->getName());
        self::assertSame('Test migration description', $this->subject->getDescription());
        self::assertSame('Test Author', $this->subject->getAuthor());
        self::assertSame('test_source_hash', $this->subject->getSourceHash());
        self::assertSame('test_migration.t3d', $this->subject->getDataFile());
        self::assertSame('pending', $this->subject->getStatus());
        self::assertInstanceOf(\DateTimeImmutable::class, $this->subject->getCreatedAt());
    }

    #[Test]
    public function settersWorkCorrectly(): void
    {
        $this->subject->setName('Updated Name');
        $this->subject->setDescription('Updated Description');
        $this->subject->setAuthor('Updated Author');
        $this->subject->setSourceHash('updated_hash');
        $this->subject->setDataFile('updated.t3d');
        $this->subject->setTargetHash('target_hash');

        self::assertSame('Updated Name', $this->subject->getName());
        self::assertSame('Updated Description', $this->subject->getDescription());
        self::assertSame('Updated Author', $this->subject->getAuthor());
        self::assertSame('updated_hash', $this->subject->getSourceHash());
        self::assertSame('updated.t3d', $this->subject->getDataFile());
        self::assertSame('target_hash', $this->subject->getTargetHash());
    }

    #[Test]
    public function statusMethodsWorkCorrectly(): void
    {
        self::assertTrue($this->subject->isPending());
        self::assertFalse($this->subject->isApplied());
        self::assertFalse($this->subject->isFailed());
        self::assertFalse($this->subject->isRolledBack());

        $this->subject->setStatus('applied');
        self::assertFalse($this->subject->isPending());
        self::assertTrue($this->subject->isApplied());
        self::assertFalse($this->subject->isFailed());
        self::assertFalse($this->subject->isRolledBack());
    }

    #[Test]
    public function setStatusThrowsExceptionForInvalidStatus(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid migration status: invalid');

        $this->subject->setStatus('invalid');
    }

    #[Test]
    public function markAsAppliedSetsStatusAndDate(): void
    {
        $appliedAt = new \DateTimeImmutable('2024-01-01 12:00:00');
        $this->subject->markAsApplied($appliedAt);

        self::assertSame('applied', $this->subject->getStatus());
        self::assertSame($appliedAt, $this->subject->getAppliedAt());
    }

    #[Test]
    public function markAsAppliedUsesCurrentDateWhenNotProvided(): void
    {
        $beforeTime = time();
        $this->subject->markAsApplied();
        $afterTime = time();

        self::assertSame('applied', $this->subject->getStatus());
        self::assertGreaterThanOrEqual($beforeTime, $this->subject->getAppliedAt()->getTimestamp());
        self::assertLessThanOrEqual($afterTime, $this->subject->getAppliedAt()->getTimestamp());
    }

    #[Test]
    public function markAsFailedSetsStatus(): void
    {
        $this->subject->markAsFailed();

        self::assertSame('failed', $this->subject->getStatus());
        self::assertTrue($this->subject->isFailed());
    }

    #[Test]
    public function markAsRolledBackSetsStatus(): void
    {
        $this->subject->markAsRolledBack();

        self::assertSame('rolled_back', $this->subject->getStatus());
        self::assertTrue($this->subject->isRolledBack());
    }

    #[Test]
    public function metadataMethodsWorkCorrectly(): void
    {
        self::assertSame([], $this->subject->getMetadata());
        self::assertFalse($this->subject->hasMetadata('test_key'));
        self::assertNull($this->subject->getMetadataValue('test_key'));
        self::assertSame('default', $this->subject->getMetadataValue('test_key', 'default'));

        $this->subject->addMetadata('test_key', 'test_value');

        self::assertTrue($this->subject->hasMetadata('test_key'));
        self::assertSame('test_value', $this->subject->getMetadataValue('test_key'));
        self::assertSame(['test_key' => 'test_value'], $this->subject->getMetadata());

        $this->subject->removeMetadata('test_key');

        self::assertFalse($this->subject->hasMetadata('test_key'));
        self::assertSame([], $this->subject->getMetadata());
    }

    #[Test]
    public function toArrayReturnsCorrectData(): void
    {
        $this->subject->setUid(123);
        $this->subject->addMetadata('test', 'value');

        $array = $this->subject->toArray();

        self::assertSame(123, $array['uid']);
        self::assertSame('T3H_20241201120000_abc12345', $array['migration_id']);
        self::assertSame('Test Migration', $array['name']);
        self::assertSame('Test migration description', $array['description']);
        self::assertSame('Test Author', $array['author']);
        self::assertSame('test_source_hash', $array['source_hash']);
        self::assertSame('test_migration.t3d', $array['data_file']);
        self::assertSame('pending', $array['status']);
        self::assertSame('{"test":"value"}', $array['metadata']);
        self::assertIsInt($array['created_at']);
        self::assertNull($array['applied_at']);
        self::assertNull($array['target_hash']);
    }

    #[Test]
    public function fromArrayCreatesCorrectInstance(): void
    {
        $data = [
            'uid' => 456,
            'migration_id' => 'T3H_20241201130000_def67890',
            'name' => 'From Array Migration',
            'description' => 'Migration from array',
            'author' => 'Array Author',
            'source_hash' => 'array_source_hash',
            'data_file' => 'array_migration.t3d',
            'status' => 'applied',
            'target_hash' => 'array_target_hash',
            'created_at' => 1701432000, // 2023-12-01 12:00:00
            'applied_at' => 1701435600, // 2023-12-01 13:00:00
            'metadata' => '{"key":"value"}',
        ];

        $migration = Migration::fromArray($data);

        self::assertSame(456, $migration->getUid());
        self::assertSame('T3H_20241201130000_def67890', $migration->getMigrationId());
        self::assertSame('From Array Migration', $migration->getName());
        self::assertSame('Migration from array', $migration->getDescription());
        self::assertSame('Array Author', $migration->getAuthor());
        self::assertSame('array_source_hash', $migration->getSourceHash());
        self::assertSame('array_migration.t3d', $migration->getDataFile());
        self::assertSame('applied', $migration->getStatus());
        self::assertSame('array_target_hash', $migration->getTargetHash());
        self::assertSame(['key' => 'value'], $migration->getMetadata());
        self::assertSame(1701432000, $migration->getCreatedAt()->getTimestamp());
        self::assertSame(1701435600, $migration->getAppliedAt()->getTimestamp());
    }

    #[Test]
    public function fromArrayHandlesMissingOptionalFields(): void
    {
        $data = [
            'migration_id' => 'T3H_20241201140000_ghi01234',
            'name' => 'Minimal Migration',
            'description' => 'Minimal migration data',
            'author' => 'Minimal Author',
            'source_hash' => 'minimal_hash',
            'data_file' => 'minimal.t3d',
        ];

        $migration = Migration::fromArray($data);

        self::assertNull($migration->getUid());
        self::assertNull($migration->getTargetHash());
        self::assertNull($migration->getAppliedAt());
        self::assertSame('pending', $migration->getStatus());
        self::assertSame([], $migration->getMetadata());
    }
}
