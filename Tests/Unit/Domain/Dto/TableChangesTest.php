<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Tests\Unit\Domain\Dto;

use Cpsit\T3hauler\Domain\Dto\TableChanges;
use Cpsit\T3hauler\Domain\Enumeration\TableStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for TableChanges DTO
 */
final class TableChangesTest extends TestCase
{
    #[Test]
    public function constructorSetsPropertiesCorrectly(): void
    {
        $tableName = 'pages';
        $status = TableStatus::CHANGED;
        $hasChanges = true;
        $message = 'Changes detected';
        $currentHash = 'hash123';
        $baselineHash = 'hash456';
        $baselineCreatedAt = new \DateTimeImmutable('2023-01-01 12:00:00');

        $dto = new TableChanges(
            $tableName,
            $status,
            $hasChanges,
            $message,
            $currentHash,
            $baselineHash,
            $baselineCreatedAt
        );

        self::assertSame($tableName, $dto->tableName);
        self::assertSame($status, $dto->status);
        self::assertSame($hasChanges, $dto->hasChanges);
        self::assertSame($message, $dto->message);
        self::assertSame($currentHash, $dto->currentHash);
        self::assertSame($baselineHash, $dto->baselineHash);
        self::assertSame($baselineCreatedAt, $dto->baselineCreatedAt);
    }

    #[Test]
    public function createNoBaselineCreatesCorrectInstance(): void
    {
        $tableName = 'pages';
        $currentHash = 'hash123';

        $dto = TableChanges::createNoBaseline($tableName, $currentHash);

        self::assertSame($tableName, $dto->tableName);
        self::assertSame(TableStatus::NO_BASELINE, $dto->status);
        self::assertTrue($dto->hasChanges);
        self::assertSame("No baseline snapshot found for table {$tableName}", $dto->message);
        self::assertSame($currentHash, $dto->currentHash);
        self::assertNull($dto->baselineHash);
        self::assertNull($dto->baselineCreatedAt);
    }

    #[Test]
    public function createChangedCreatesCorrectInstance(): void
    {
        $tableName = 'pages';
        $currentHash = 'hash123';
        $baselineHash = 'hash456';
        $baselineCreatedAt = new \DateTimeImmutable('2023-01-01 12:00:00');

        $dto = TableChanges::createChanged($tableName, $currentHash, $baselineHash, $baselineCreatedAt);

        self::assertSame($tableName, $dto->tableName);
        self::assertSame(TableStatus::CHANGED, $dto->status);
        self::assertTrue($dto->hasChanges);
        self::assertSame("Changes detected in table {$tableName}", $dto->message);
        self::assertSame($currentHash, $dto->currentHash);
        self::assertSame($baselineHash, $dto->baselineHash);
        self::assertSame($baselineCreatedAt, $dto->baselineCreatedAt);
    }

    #[Test]
    public function createChangedWithoutBaselineCreatedAtCreatesCorrectInstance(): void
    {
        $tableName = 'pages';
        $currentHash = 'hash123';
        $baselineHash = 'hash456';

        $dto = TableChanges::createChanged($tableName, $currentHash, $baselineHash);

        self::assertSame($tableName, $dto->tableName);
        self::assertSame(TableStatus::CHANGED, $dto->status);
        self::assertTrue($dto->hasChanges);
        self::assertSame("Changes detected in table {$tableName}", $dto->message);
        self::assertSame($currentHash, $dto->currentHash);
        self::assertSame($baselineHash, $dto->baselineHash);
        self::assertNull($dto->baselineCreatedAt);
    }

    #[Test]
    public function createUnchangedCreatesCorrectInstance(): void
    {
        $tableName = 'pages';
        $currentHash = 'hash123';
        $baselineHash = 'hash123'; // Same hash for unchanged
        $baselineCreatedAt = new \DateTimeImmutable('2023-01-01 12:00:00');

        $dto = TableChanges::createUnchanged($tableName, $currentHash, $baselineHash, $baselineCreatedAt);

        self::assertSame($tableName, $dto->tableName);
        self::assertSame(TableStatus::UNCHANGED, $dto->status);
        self::assertFalse($dto->hasChanges);
        self::assertSame("No changes detected in table {$tableName}", $dto->message);
        self::assertSame($currentHash, $dto->currentHash);
        self::assertSame($baselineHash, $dto->baselineHash);
        self::assertSame($baselineCreatedAt, $dto->baselineCreatedAt);
    }

    #[Test]
    public function createUnchangedWithoutBaselineCreatedAtCreatesCorrectInstance(): void
    {
        $tableName = 'pages';
        $currentHash = 'hash123';
        $baselineHash = 'hash123';

        $dto = TableChanges::createUnchanged($tableName, $currentHash, $baselineHash);

        self::assertSame($tableName, $dto->tableName);
        self::assertSame(TableStatus::UNCHANGED, $dto->status);
        self::assertFalse($dto->hasChanges);
        self::assertSame("No changes detected in table {$tableName}", $dto->message);
        self::assertSame($currentHash, $dto->currentHash);
        self::assertSame($baselineHash, $dto->baselineHash);
        self::assertNull($dto->baselineCreatedAt);
    }
}
