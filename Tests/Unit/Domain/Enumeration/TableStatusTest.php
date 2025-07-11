<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Tests\Unit\Domain\Enumeration;

use Cpsit\T3hauler\Domain\Enumeration\TableStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for TableStatus enumeration
 */
final class TableStatusTest extends TestCase
{
    #[Test]
    public function enumHasCorrectValues(): void
    {
        self::assertSame('changed', TableStatus::CHANGED->value);
        self::assertSame('unchanged', TableStatus::UNCHANGED->value);
        self::assertSame('no_baseline', TableStatus::NO_BASELINE->value);
    }

    #[Test]
    #[DataProvider('descriptionDataProvider')]
    public function getDescriptionReturnsCorrectText(TableStatus $status, string $expectedDescription): void
    {
        self::assertSame($expectedDescription, $status->getDescription());
    }

    #[Test]
    #[DataProvider('hasChangesDataProvider')]
    public function hasChangesReturnsCorrectValue(TableStatus $status, bool $expectedHasChanges): void
    {
        self::assertSame($expectedHasChanges, $status->hasChanges());
    }

    /**
     * @return array<string, array{TableStatus, string}>
     */
    public static function descriptionDataProvider(): array
    {
        return [
            'changed' => [TableStatus::CHANGED, 'Changes detected'],
            'unchanged' => [TableStatus::UNCHANGED, 'No changes detected'],
            'no_baseline' => [TableStatus::NO_BASELINE, 'No baseline snapshot found'],
        ];
    }

    /**
     * @return array<string, array{TableStatus, bool}>
     */
    public static function hasChangesDataProvider(): array
    {
        return [
            'changed has changes' => [TableStatus::CHANGED, true],
            'unchanged has no changes' => [TableStatus::UNCHANGED, false],
            'no_baseline has changes' => [TableStatus::NO_BASELINE, true],
        ];
    }
}
