<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Tests\Unit\Domain\Enumeration;

use Cpsit\T3hauler\Domain\Enumeration\RecordChangeType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for RecordChangeType enumeration
 */
final class RecordChangeTypeTest extends TestCase
{
    #[Test]
    public function enumHasCorrectValues(): void
    {
        self::assertSame('insert', RecordChangeType::INSERT->value);
        self::assertSame('update', RecordChangeType::UPDATE->value);
        self::assertSame('delete', RecordChangeType::DELETE->value);
        self::assertSame('move', RecordChangeType::MOVE->value);
    }

    /**
     * @return array<string, array{RecordChangeType, string}>
     */
    public static function descriptionDataProvider(): array
    {
        return [
            'insert' => [RecordChangeType::INSERT, 'new record inserted'],
            'update' => [RecordChangeType::UPDATE, 'record fields updated'],
            'delete' => [RecordChangeType::DELETE, 'record deleted'],
            'move' => [RecordChangeType::MOVE, 'record moved'],
        ];
    }

    #[Test]
    #[DataProvider('descriptionDataProvider')]
    public function getDescriptionReturnsCorrectText(RecordChangeType $changeType, string $expectedDescription): void
    {
        self::assertSame($expectedDescription, $changeType->getDescription());
    }

    #[Test]
    public function allChangeTypesAreDefinedAndHaveDescriptions(): void
    {
        $allCases = RecordChangeType::cases();

        self::assertCount(4, $allCases);

        foreach ($allCases as $changeType) {
            $description = $changeType->getDescription();
            self::assertNotEmpty($description);
        }
    }

    #[Test]
    public function changeTypeValuesAreUnique(): void
    {
        $allCases = RecordChangeType::cases();
        $values = array_map(fn(RecordChangeType $changeType) => $changeType->value, $allCases);

        self::assertSame(count($values), count(array_unique($values)));
    }

    #[Test]
    public function changeTypeCanBeCreatedFromValue(): void
    {
        self::assertSame(RecordChangeType::INSERT, RecordChangeType::from('insert'));
        self::assertSame(RecordChangeType::UPDATE, RecordChangeType::from('update'));
        self::assertSame(RecordChangeType::DELETE, RecordChangeType::from('delete'));
        self::assertSame(RecordChangeType::MOVE, RecordChangeType::from('move'));
    }

    #[Test]
    public function tryFromReturnsNullForInvalidValue(): void
    {
        self::assertNull(RecordChangeType::tryFrom('invalid_type'));
        self::assertNull(RecordChangeType::tryFrom(''));
        self::assertNull(RecordChangeType::tryFrom('INSERT')); // Case sensitive
        self::assertNull(RecordChangeType::tryFrom('create')); // Different value
    }

    #[Test]
    public function tryFromReturnsChangeTypeForValidValue(): void
    {
        self::assertSame(RecordChangeType::INSERT, RecordChangeType::tryFrom('insert'));
        self::assertSame(RecordChangeType::UPDATE, RecordChangeType::tryFrom('update'));
        self::assertSame(RecordChangeType::DELETE, RecordChangeType::tryFrom('delete'));
        self::assertSame(RecordChangeType::MOVE, RecordChangeType::tryFrom('move'));
    }

    #[Test]
    public function fromThrowsExceptionForInvalidValue(): void
    {
        $this->expectException(\ValueError::class);
        RecordChangeType::from('invalid_type');
    }

    #[Test]
    public function enumCasesAreCorrect(): void
    {
        $cases = RecordChangeType::cases();
        $caseNames = array_map(fn(RecordChangeType $case) => $case->name, $cases);

        self::assertSame(['INSERT', 'UPDATE', 'DELETE', 'MOVE'], $caseNames);
    }

    #[Test]
    public function enumValuePropertiesAreCorrect(): void
    {
        self::assertSame('insert', RecordChangeType::INSERT->value);
        self::assertSame('update', RecordChangeType::UPDATE->value);
        self::assertSame('delete', RecordChangeType::DELETE->value);
        self::assertSame('move', RecordChangeType::MOVE->value);
    }
}
