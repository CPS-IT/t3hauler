<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Tests\Unit\Domain\Enumeration;

use Cpsit\T3hauler\Domain\Enumeration\ExportValidationStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ExportValidationStatus enumeration
 */
final class ExportValidationStatusTest extends TestCase
{
    #[Test]
    public function enumHasCorrectValues(): void
    {
        self::assertSame('valid', ExportValidationStatus::VALID->value);
        self::assertSame('invalid', ExportValidationStatus::INVALID->value);
        self::assertSame('file_not_found', ExportValidationStatus::FILE_NOT_FOUND->value);
        self::assertSame('file_empty', ExportValidationStatus::FILE_EMPTY->value);
        self::assertSame('invalid_json', ExportValidationStatus::INVALID_JSON->value);
        self::assertSame('invalid_structure', ExportValidationStatus::INVALID_STRUCTURE->value);
        self::assertSame('parse_error', ExportValidationStatus::PARSE_ERROR->value);
    }

    #[Test]
    public function isValidReturnsTrueForValidStatus(): void
    {
        self::assertTrue(ExportValidationStatus::VALID->isValid());
    }

    /**
     * @return array<string, array{ExportValidationStatus}>
     */
    public static function invalidStatusProvider(): array
    {
        return [
            'invalid' => [ExportValidationStatus::INVALID],
            'file_not_found' => [ExportValidationStatus::FILE_NOT_FOUND],
            'file_empty' => [ExportValidationStatus::FILE_EMPTY],
            'invalid_json' => [ExportValidationStatus::INVALID_JSON],
            'invalid_structure' => [ExportValidationStatus::INVALID_STRUCTURE],
            'parse_error' => [ExportValidationStatus::PARSE_ERROR],
        ];
    }

    #[Test]
    #[DataProvider('invalidStatusProvider')]
    public function isValidReturnsFalseForInvalidStatuses(ExportValidationStatus $status): void
    {
        self::assertFalse($status->isValid());
    }

    #[Test]
    public function isInvalidReturnsFalseForValidStatus(): void
    {
        self::assertFalse(ExportValidationStatus::VALID->isInvalid());
    }

    #[Test]
    #[DataProvider('invalidStatusProvider')]
    public function isInvalidReturnsTrueForInvalidStatuses(ExportValidationStatus $status): void
    {
        self::assertTrue($status->isInvalid());
    }

    /**
     * @return array<string, array{ExportValidationStatus, string}>
     */
    public static function descriptionDataProvider(): array
    {
        return [
            'valid' => [ExportValidationStatus::VALID, 'File is valid'],
            'invalid' => [ExportValidationStatus::INVALID, 'File is invalid'],
            'file_not_found' => [ExportValidationStatus::FILE_NOT_FOUND, 'File not found'],
            'file_empty' => [ExportValidationStatus::FILE_EMPTY, 'File is empty'],
            'invalid_json' => [ExportValidationStatus::INVALID_JSON, 'Invalid JSON format'],
            'invalid_structure' => [ExportValidationStatus::INVALID_STRUCTURE, 'Invalid file structure'],
            'parse_error' => [ExportValidationStatus::PARSE_ERROR, 'Failed to parse file'],
        ];
    }

    #[Test]
    #[DataProvider('descriptionDataProvider')]
    public function getDescriptionReturnsCorrectText(ExportValidationStatus $status, string $expectedDescription): void
    {
        self::assertSame($expectedDescription, $status->getDescription());
    }

    #[Test]
    public function allStatusesAreDefinedAndHaveDescriptions(): void
    {
        $allCases = ExportValidationStatus::cases();

        self::assertCount(7, $allCases);

        foreach ($allCases as $status) {
            $description = $status->getDescription();
            self::assertIsString($description);
            self::assertNotEmpty($description);
        }
    }

    #[Test]
    public function statusValuesAreUnique(): void
    {
        $allCases = ExportValidationStatus::cases();
        $values = array_map(fn(ExportValidationStatus $status) => $status->value, $allCases);

        self::assertSame(count($values), count(array_unique($values)));
    }

    #[Test]
    public function statusCanBeCreatedFromValue(): void
    {
        self::assertSame(ExportValidationStatus::VALID, ExportValidationStatus::from('valid'));
        self::assertSame(ExportValidationStatus::INVALID, ExportValidationStatus::from('invalid'));
        self::assertSame(ExportValidationStatus::FILE_NOT_FOUND, ExportValidationStatus::from('file_not_found'));
        self::assertSame(ExportValidationStatus::FILE_EMPTY, ExportValidationStatus::from('file_empty'));
        self::assertSame(ExportValidationStatus::INVALID_JSON, ExportValidationStatus::from('invalid_json'));
        self::assertSame(ExportValidationStatus::INVALID_STRUCTURE, ExportValidationStatus::from('invalid_structure'));
        self::assertSame(ExportValidationStatus::PARSE_ERROR, ExportValidationStatus::from('parse_error'));
    }

    #[Test]
    public function tryFromReturnsNullForInvalidValue(): void
    {
        self::assertNull(ExportValidationStatus::tryFrom('invalid_status'));
        self::assertNull(ExportValidationStatus::tryFrom(''));
        self::assertNull(ExportValidationStatus::tryFrom('VALID')); // Case sensitive
    }

    #[Test]
    public function tryFromReturnsStatusForValidValue(): void
    {
        self::assertSame(ExportValidationStatus::VALID, ExportValidationStatus::tryFrom('valid'));
        self::assertSame(ExportValidationStatus::INVALID, ExportValidationStatus::tryFrom('invalid'));
    }

    #[Test]
    public function validAndInvalidAreOpposite(): void
    {
        self::assertTrue(ExportValidationStatus::VALID->isValid());
        self::assertFalse(ExportValidationStatus::VALID->isInvalid());

        self::assertFalse(ExportValidationStatus::INVALID->isValid());
        self::assertTrue(ExportValidationStatus::INVALID->isInvalid());
    }
}
