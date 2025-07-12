<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Tests\Unit\Domain\Enumeration;

use Cpsit\T3hauler\Domain\Enumeration\ExportStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ExportStatus enumeration
 */
final class ExportStatusTest extends TestCase
{
    #[Test]
    public function enumHasCorrectValues(): void
    {
        self::assertSame('success', ExportStatus::SUCCESS->value);
        self::assertSame('failure', ExportStatus::FAILURE->value);
        self::assertSame('no_tables', ExportStatus::NO_TABLES->value);
        self::assertSame('no_records', ExportStatus::NO_RECORDS->value);
        self::assertSame('export_failed', ExportStatus::EXPORT_FAILED->value);
        self::assertSame('directory_creation_failed', ExportStatus::DIRECTORY_CREATION_FAILED->value);
        self::assertSame('file_write_failed', ExportStatus::FILE_WRITE_FAILED->value);
    }

    #[Test]
    public function isSuccessReturnsTrueForSuccessStatus(): void
    {
        self::assertTrue(ExportStatus::SUCCESS->isSuccess());
    }

    /**
     * @return array<string, array{ExportStatus}>
     */
    public static function failureStatusProvider(): array
    {
        return [
            'failure' => [ExportStatus::FAILURE],
            'no_tables' => [ExportStatus::NO_TABLES],
            'no_records' => [ExportStatus::NO_RECORDS],
            'export_failed' => [ExportStatus::EXPORT_FAILED],
            'directory_creation_failed' => [ExportStatus::DIRECTORY_CREATION_FAILED],
            'file_write_failed' => [ExportStatus::FILE_WRITE_FAILED],
        ];
    }

    #[Test]
    #[DataProvider('failureStatusProvider')]
    public function isSuccessReturnsFalseForFailureStatuses(ExportStatus $status): void
    {
        self::assertFalse($status->isSuccess());
    }

    #[Test]
    public function isFailureReturnsFalseForSuccessStatus(): void
    {
        self::assertFalse(ExportStatus::SUCCESS->isFailure());
    }

    #[Test]
    #[DataProvider('failureStatusProvider')]
    public function isFailureReturnsTrueForFailureStatuses(ExportStatus $status): void
    {
        self::assertTrue($status->isFailure());
    }

    /**
     * @return array<string, array{ExportStatus, string}>
     */
    public static function descriptionDataProvider(): array
    {
        return [
            'success' => [ExportStatus::SUCCESS, 'Export completed successfully'],
            'failure' => [ExportStatus::FAILURE, 'Export failed'],
            'no_tables' => [ExportStatus::NO_TABLES, 'No tables provided for export'],
            'no_records' => [ExportStatus::NO_RECORDS, 'No records found to export'],
            'export_failed' => [ExportStatus::EXPORT_FAILED, 'Export generation failed'],
            'directory_creation_failed' => [ExportStatus::DIRECTORY_CREATION_FAILED, 'Failed to create output directory'],
            'file_write_failed' => [ExportStatus::FILE_WRITE_FAILED, 'Failed to write export file'],
        ];
    }

    #[Test]
    #[DataProvider('descriptionDataProvider')]
    public function getDescriptionReturnsCorrectText(ExportStatus $status, string $expectedDescription): void
    {
        self::assertSame($expectedDescription, $status->getDescription());
    }

    #[Test]
    public function allStatusesAreDefinedAndHaveDescriptions(): void
    {
        $allCases = ExportStatus::cases();

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
        $allCases = ExportStatus::cases();
        $values = array_map(fn(ExportStatus $status) => $status->value, $allCases);

        self::assertSame(count($values), count(array_unique($values)));
    }

    #[Test]
    public function statusCanBeCreatedFromValue(): void
    {
        self::assertSame(ExportStatus::SUCCESS, ExportStatus::from('success'));
        self::assertSame(ExportStatus::FAILURE, ExportStatus::from('failure'));
        self::assertSame(ExportStatus::NO_TABLES, ExportStatus::from('no_tables'));
        self::assertSame(ExportStatus::NO_RECORDS, ExportStatus::from('no_records'));
        self::assertSame(ExportStatus::EXPORT_FAILED, ExportStatus::from('export_failed'));
        self::assertSame(ExportStatus::DIRECTORY_CREATION_FAILED, ExportStatus::from('directory_creation_failed'));
        self::assertSame(ExportStatus::FILE_WRITE_FAILED, ExportStatus::from('file_write_failed'));
    }

    #[Test]
    public function tryFromReturnsNullForInvalidValue(): void
    {
        self::assertNull(ExportStatus::tryFrom('invalid_status'));
        self::assertNull(ExportStatus::tryFrom(''));
        self::assertNull(ExportStatus::tryFrom('FAILURE')); // Case sensitive
    }

    #[Test]
    public function tryFromReturnsStatusForValidValue(): void
    {
        self::assertSame(ExportStatus::SUCCESS, ExportStatus::tryFrom('success'));
        self::assertSame(ExportStatus::FAILURE, ExportStatus::tryFrom('failure'));
    }
}
