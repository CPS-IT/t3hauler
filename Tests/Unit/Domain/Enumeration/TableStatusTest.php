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

    #[Test]
    public function allStatusesAreDefinedAndHaveDescriptions(): void
    {
        $allCases = TableStatus::cases();

        self::assertCount(3, $allCases);

        foreach ($allCases as $status) {
            $description = $status->getDescription();
            self::assertNotEmpty($description);
        }
    }

    #[Test]
    public function statusValuesAreUnique(): void
    {
        $allCases = TableStatus::cases();
        $values = array_map(fn(TableStatus $status) => $status->value, $allCases);

        self::assertCount(count($values), array_unique($values));
    }

    #[Test]
    public function statusCanBeCreatedFromValue(): void
    {
        self::assertSame(TableStatus::CHANGED, TableStatus::from('changed'));
        self::assertSame(TableStatus::UNCHANGED, TableStatus::from('unchanged'));
        self::assertSame(TableStatus::NO_BASELINE, TableStatus::from('no_baseline'));
    }

    #[Test]
    public function tryFromReturnsNullForInvalidValue(): void
    {
        self::assertNull(TableStatus::tryFrom('invalid_status'));
        self::assertNull(TableStatus::tryFrom(''));
        self::assertNull(TableStatus::tryFrom('CHANGED')); // Case sensitive
    }

    #[Test]
    public function tryFromReturnsStatusForValidValue(): void
    {
        self::assertSame(TableStatus::CHANGED, TableStatus::tryFrom('changed'));
        self::assertSame(TableStatus::UNCHANGED, TableStatus::tryFrom('unchanged'));
    }
}
