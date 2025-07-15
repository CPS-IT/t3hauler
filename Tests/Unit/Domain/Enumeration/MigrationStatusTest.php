<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Tests\Unit\Domain\Enumeration;

use Cpsit\T3hauler\Domain\Enumeration\MigrationStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for MigrationStatus enumeration
 */
final class MigrationStatusTest extends TestCase
{
    #[Test]
    public function enumHasCorrectValues(): void
    {
        self::assertSame('pending', MigrationStatus::PENDING->value);
        self::assertSame('applied', MigrationStatus::APPLIED->value);
        self::assertSame('failed', MigrationStatus::FAILED->value);
        self::assertSame('rolled_back', MigrationStatus::ROLLED_BACK->value);
        self::assertSame('incomplete', MigrationStatus::INCOMPLETE->value);
        self::assertSame('invalid', MigrationStatus::INVALID->value);
    }

    #[Test]
    public function getValuesReturnsAllStatusValues(): void
    {
        $expectedValues = ['pending', 'applied', 'failed', 'rolled_back', 'incomplete', 'invalid'];
        $actualValues = MigrationStatus::getValues();

        self::assertCount(6, $actualValues);
        self::assertSame($expectedValues, $actualValues);
    }

    #[Test]
    public function isSuccessfulReturnsTrueForAppliedStatus(): void
    {
        self::assertTrue(MigrationStatus::APPLIED->isSuccessful());
    }

    /**
     * @return array<string, array{MigrationStatus}>
     */
    public static function nonSuccessfulStatusProvider(): array
    {
        return [
            'pending' => [MigrationStatus::PENDING],
            'failed' => [MigrationStatus::FAILED],
            'rolled_back' => [MigrationStatus::ROLLED_BACK],
            'incomplete' => [MigrationStatus::INCOMPLETE],
            'invalid' => [MigrationStatus::INVALID],
        ];
    }

    #[Test]
    #[DataProvider('nonSuccessfulStatusProvider')]
    public function isSuccessfulReturnsFalseForNonSuccessfulStatuses(MigrationStatus $status): void
    {
        self::assertFalse($status->isSuccessful());
    }

    /**
     * @return array<string, array{MigrationStatus}>
     */
    public static function failedStatusProvider(): array
    {
        return [
            'failed' => [MigrationStatus::FAILED],
            'invalid' => [MigrationStatus::INVALID],
            'incomplete' => [MigrationStatus::INCOMPLETE],
        ];
    }

    #[Test]
    #[DataProvider('failedStatusProvider')]
    public function isFailedReturnsTrueForFailedStatuses(MigrationStatus $status): void
    {
        self::assertTrue($status->isFailed());
    }

    /**
     * @return array<string, array{MigrationStatus}>
     */
    public static function nonFailedStatusProvider(): array
    {
        return [
            'pending' => [MigrationStatus::PENDING],
            'applied' => [MigrationStatus::APPLIED],
            'rolled_back' => [MigrationStatus::ROLLED_BACK],
        ];
    }

    #[Test]
    #[DataProvider('nonFailedStatusProvider')]
    public function isFailedReturnsFalseForNonFailedStatuses(MigrationStatus $status): void
    {
        self::assertFalse($status->isFailed());
    }

    #[Test]
    public function isPendingReturnsTrueForPendingStatus(): void
    {
        self::assertTrue(MigrationStatus::PENDING->isPending());
    }

    #[Test]
    #[DataProvider('nonPendingStatusProvider')]
    public function isPendingReturnsFalseForNonPendingStatuses(MigrationStatus $status): void
    {
        self::assertFalse($status->isPending());
    }

    /**
     * @return array<string, array{MigrationStatus}>
     */
    public static function nonPendingStatusProvider(): array
    {
        return [
            'applied' => [MigrationStatus::APPLIED],
            'failed' => [MigrationStatus::FAILED],
            'rolled_back' => [MigrationStatus::ROLLED_BACK],
            'incomplete' => [MigrationStatus::INCOMPLETE],
            'invalid' => [MigrationStatus::INVALID],
        ];
    }

    #[Test]
    public function isRolledBackReturnsTrueForRolledBackStatus(): void
    {
        self::assertTrue(MigrationStatus::ROLLED_BACK->isRolledBack());
    }

    /**
     * @return array<string, array{MigrationStatus}>
     */
    public static function nonRolledBackStatusProvider(): array
    {
        return [
            'pending' => [MigrationStatus::PENDING],
            'applied' => [MigrationStatus::APPLIED],
            'failed' => [MigrationStatus::FAILED],
            'incomplete' => [MigrationStatus::INCOMPLETE],
            'invalid' => [MigrationStatus::INVALID],
        ];
    }

    #[Test]
    #[DataProvider('nonRolledBackStatusProvider')]
    public function isRolledBackReturnsFalseForNonRolledBackStatuses(MigrationStatus $status): void
    {
        self::assertFalse($status->isRolledBack());
    }

    /**
     * @return array<string, array{MigrationStatus, string}>
     */
    public static function displayNameDataProvider(): array
    {
        return [
            'pending' => [MigrationStatus::PENDING, 'Pending'],
            'applied' => [MigrationStatus::APPLIED, 'Applied'],
            'failed' => [MigrationStatus::FAILED, 'Failed'],
            'rolled_back' => [MigrationStatus::ROLLED_BACK, 'Rolled Back'],
            'incomplete' => [MigrationStatus::INCOMPLETE, 'Incomplete'],
            'invalid' => [MigrationStatus::INVALID, 'Invalid'],
        ];
    }

    #[Test]
    #[DataProvider('displayNameDataProvider')]
    public function getDisplayNameReturnsCorrectText(MigrationStatus $status, string $expectedDisplayName): void
    {
        self::assertSame($expectedDisplayName, $status->getDisplayName());
    }

    /**
     * @return array<string, array{MigrationStatus, string}>
     */
    public static function coloredStatusDataProvider(): array
    {
        return [
            'pending' => [MigrationStatus::PENDING, '<fg=yellow>pending</fg=yellow>'],
            'applied' => [MigrationStatus::APPLIED, '<fg=green>applied</fg=green>'],
            'failed' => [MigrationStatus::FAILED, '<fg=red>failed</fg=red>'],
            'rolled_back' => [MigrationStatus::ROLLED_BACK, '<fg=blue>rolled_back</fg=blue>'],
            'incomplete' => [MigrationStatus::INCOMPLETE, '<fg=red>incomplete</fg=red>'],
            'invalid' => [MigrationStatus::INVALID, '<fg=red>invalid</fg=red>'],
        ];
    }

    #[Test]
    #[DataProvider('coloredStatusDataProvider')]
    public function getColoredStatusReturnsCorrectColoredText(MigrationStatus $status, string $expectedColoredStatus): void
    {
        self::assertSame($expectedColoredStatus, $status->getColoredStatus());
    }

    #[Test]
    public function allStatusesAreDefinedAndHaveDisplayNames(): void
    {
        $allCases = MigrationStatus::cases();

        self::assertCount(6, $allCases);

        foreach ($allCases as $status) {
            $displayName = $status->getDisplayName();
            self::assertNotEmpty($displayName);
        }
    }

    #[Test]
    public function statusValuesAreUnique(): void
    {
        $allCases = MigrationStatus::cases();
        $values = array_map(fn(MigrationStatus $status) => $status->value, $allCases);

        self::assertSame(count($values), count(array_unique($values)));
    }

    #[Test]
    public function statusCanBeCreatedFromValue(): void
    {
        self::assertSame(MigrationStatus::PENDING, MigrationStatus::from('pending'));
        self::assertSame(MigrationStatus::APPLIED, MigrationStatus::from('applied'));
        self::assertSame(MigrationStatus::FAILED, MigrationStatus::from('failed'));
        self::assertSame(MigrationStatus::ROLLED_BACK, MigrationStatus::from('rolled_back'));
        self::assertSame(MigrationStatus::INCOMPLETE, MigrationStatus::from('incomplete'));
        self::assertSame(MigrationStatus::INVALID, MigrationStatus::from('invalid'));
    }

    #[Test]
    public function tryFromReturnsNullForInvalidValue(): void
    {
        self::assertNull(MigrationStatus::tryFrom('invalid_status'));
        self::assertNull(MigrationStatus::tryFrom(''));
        self::assertNull(MigrationStatus::tryFrom('APPLIED')); // Case sensitive
    }

    #[Test]
    public function tryFromReturnsStatusForValidValue(): void
    {
        self::assertSame(MigrationStatus::PENDING, MigrationStatus::tryFrom('pending'));
        self::assertSame(MigrationStatus::APPLIED, MigrationStatus::tryFrom('applied'));
        self::assertSame(MigrationStatus::FAILED, MigrationStatus::tryFrom('failed'));
    }
}
