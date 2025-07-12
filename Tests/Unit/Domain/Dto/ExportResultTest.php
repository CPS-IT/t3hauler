<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Tests\Unit\Domain\Dto;

use Cpsit\T3hauler\Domain\Dto\ExportResult;
use Cpsit\T3hauler\Domain\Enumeration\ExportStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ExportResult DTO
 */
final class ExportResultTest extends TestCase
{
    #[Test]
    public function constructorSetsAllProperties(): void
    {
        $status = ExportStatus::SUCCESS;
        $message = 'Export completed successfully';
        $recordCount = 42;
        $exportedTables = ['pages' => 10, 'tt_content' => 32];
        $filePath = '/path/to/export.json';
        $fileSize = 1024;
        $format = 'json';
        $metadata = ['version' => '1.0', 'charset' => 'utf-8'];
        $exception = 'Some error message';
        $exceptionCode = 500;

        $result = new ExportResult(
            status: $status,
            message: $message,
            recordCount: $recordCount,
            exportedTables: $exportedTables,
            filePath: $filePath,
            fileSize: $fileSize,
            format: $format,
            metadata: $metadata,
            exception: $exception,
            exceptionCode: $exceptionCode
        );

        self::assertSame($status, $result->status);
        self::assertSame($message, $result->message);
        self::assertSame($recordCount, $result->recordCount);
        self::assertSame($exportedTables, $result->exportedTables);
        self::assertSame($filePath, $result->filePath);
        self::assertSame($fileSize, $result->fileSize);
        self::assertSame($format, $result->format);
        self::assertSame($metadata, $result->metadata);
        self::assertSame($exception, $result->exception);
        self::assertSame($exceptionCode, $result->exceptionCode);
    }

    #[Test]
    public function constructorUsesDefaultValues(): void
    {
        $result = new ExportResult(
            status: ExportStatus::NO_RECORDS,
            message: 'No records found'
        );

        self::assertSame(ExportStatus::NO_RECORDS, $result->status);
        self::assertSame('No records found', $result->message);
        self::assertSame(0, $result->recordCount);
        self::assertSame([], $result->exportedTables);
        self::assertNull($result->filePath);
        self::assertNull($result->fileSize);
        self::assertSame('json', $result->format);
        self::assertSame([], $result->metadata);
        self::assertNull($result->exception);
        self::assertNull($result->exceptionCode);
    }

    #[Test]
    public function isSuccessReturnsTrueForSuccessStatus(): void
    {
        $result = new ExportResult(
            status: ExportStatus::SUCCESS,
            message: 'Export completed'
        );

        self::assertTrue($result->isSuccess());
    }

    /**
     * @return array<string, array{ExportStatus}>
     */
    public static function nonSuccessStatusProvider(): array
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
    #[DataProvider('nonSuccessStatusProvider')]
    public function isSuccessReturnsFalseForNonSuccessStatus(ExportStatus $status): void
    {
        $result = new ExportResult(
            status: $status,
            message: 'Some message'
        );

        self::assertFalse($result->isSuccess());
    }

    #[Test]
    public function isFailureReturnsFalseForSuccessStatus(): void
    {
        $result = new ExportResult(
            status: ExportStatus::SUCCESS,
            message: 'Export completed'
        );

        self::assertFalse($result->isFailure());
    }

    #[Test]
    #[DataProvider('nonSuccessStatusProvider')]
    public function isFailureReturnsTrueForNonSuccessStatus(ExportStatus $status): void
    {
        $result = new ExportResult(
            status: $status,
            message: 'Some message'
        );

        self::assertTrue($result->isFailure());
    }

    #[Test]
    public function hasRecordsReturnsTrueWhenRecordCountIsGreaterThanZero(): void
    {
        $result = new ExportResult(
            status: ExportStatus::SUCCESS,
            message: 'Export completed',
            recordCount: 1
        );

        self::assertTrue($result->hasRecords());
    }

    #[Test]
    public function hasRecordsReturnsFalseWhenRecordCountIsZero(): void
    {
        $result = new ExportResult(
            status: ExportStatus::SUCCESS,
            message: 'Export completed',
            recordCount: 0
        );

        self::assertFalse($result->hasRecords());
    }

    #[Test]
    public function hasFileReturnsTrueWhenBothFilePathAndFileSizeAreSet(): void
    {
        $result = new ExportResult(
            status: ExportStatus::SUCCESS,
            message: 'Export completed',
            filePath: '/path/to/file.json',
            fileSize: 1024
        );

        self::assertTrue($result->hasFile());
    }

    #[Test]
    public function hasFileReturnsFalseWhenFilePathIsNull(): void
    {
        $result = new ExportResult(
            status: ExportStatus::SUCCESS,
            message: 'Export completed',
            filePath: null,
            fileSize: 1024
        );

        self::assertFalse($result->hasFile());
    }

    #[Test]
    public function hasFileReturnsFalseWhenFileSizeIsNull(): void
    {
        $result = new ExportResult(
            status: ExportStatus::SUCCESS,
            message: 'Export completed',
            filePath: '/path/to/file.json',
            fileSize: null
        );

        self::assertFalse($result->hasFile());
    }

    #[Test]
    public function hasFileReturnsFalseWhenBothFilePathAndFileSizeAreNull(): void
    {
        $result = new ExportResult(
            status: ExportStatus::SUCCESS,
            message: 'Export completed',
            filePath: null,
            fileSize: null
        );

        self::assertFalse($result->hasFile());
    }

    #[Test]
    public function hasExceptionReturnsTrueWhenExceptionIsSet(): void
    {
        $result = new ExportResult(
            status: ExportStatus::FAILURE,
            message: 'Export failed',
            exception: 'Database connection failed'
        );

        self::assertTrue($result->hasException());
    }

    #[Test]
    public function hasExceptionReturnsFalseWhenExceptionIsNull(): void
    {
        $result = new ExportResult(
            status: ExportStatus::SUCCESS,
            message: 'Export completed',
            exception: null
        );

        self::assertFalse($result->hasException());
    }

    #[Test]
    public function successFactoryMethodCreatesSuccessfulResult(): void
    {
        $message = 'Export completed successfully';
        $recordCount = 100;
        $exportedTables = ['pages' => 20, 'tt_content' => 80];
        $filePath = '/exports/data.json';
        $fileSize = 2048;
        $format = 'json';
        $metadata = ['version' => '2.0'];

        $result = ExportResult::success(
            message: $message,
            recordCount: $recordCount,
            exportedTables: $exportedTables,
            filePath: $filePath,
            fileSize: $fileSize,
            format: $format,
            metadata: $metadata
        );

        self::assertSame(ExportStatus::SUCCESS, $result->status);
        self::assertSame($message, $result->message);
        self::assertSame($recordCount, $result->recordCount);
        self::assertSame($exportedTables, $result->exportedTables);
        self::assertSame($filePath, $result->filePath);
        self::assertSame($fileSize, $result->fileSize);
        self::assertSame($format, $result->format);
        self::assertSame($metadata, $result->metadata);
        self::assertNull($result->exception);
        self::assertNull($result->exceptionCode);
        self::assertTrue($result->isSuccess());
    }

    #[Test]
    public function successFactoryMethodUsesDefaultFormatAndMetadata(): void
    {
        $result = ExportResult::success(
            message: 'Export completed',
            recordCount: 50,
            exportedTables: ['pages' => 50],
            filePath: '/tmp/export.json',
            fileSize: 1024
        );

        self::assertSame('json', $result->format);
        self::assertSame([], $result->metadata);
    }

    #[Test]
    public function failureFactoryMethodCreatesFailedResult(): void
    {
        $status = ExportStatus::FILE_WRITE_FAILED;
        $message = 'Failed to write export file';
        $exception = 'Permission denied';
        $exceptionCode = 403;

        $result = ExportResult::failure(
            status: $status,
            message: $message,
            exception: $exception,
            exceptionCode: $exceptionCode
        );

        self::assertSame($status, $result->status);
        self::assertSame($message, $result->message);
        self::assertSame($exception, $result->exception);
        self::assertSame($exceptionCode, $result->exceptionCode);
        self::assertSame(0, $result->recordCount);
        self::assertSame([], $result->exportedTables);
        self::assertNull($result->filePath);
        self::assertNull($result->fileSize);
        self::assertSame('json', $result->format);
        self::assertSame([], $result->metadata);
        self::assertTrue($result->isFailure());
    }

    #[Test]
    public function failureFactoryMethodUsesNullForOptionalExceptionFields(): void
    {
        $result = ExportResult::failure(
            status: ExportStatus::NO_TABLES,
            message: 'No tables to export'
        );

        self::assertNull($result->exception);
        self::assertNull($result->exceptionCode);
    }

    #[Test]
    public function toArrayReturnsCorrectStructure(): void
    {
        $result = new ExportResult(
            status: ExportStatus::SUCCESS,
            message: 'Export completed',
            recordCount: 25,
            exportedTables: ['pages' => 10, 'tt_content' => 15],
            filePath: '/exports/test.json',
            fileSize: 512,
            format: 'json',
            metadata: ['charset' => 'utf-8'],
            exception: null,
            exceptionCode: null
        );

        $expected = [
            'success' => true,
            'status' => 'success',
            'message' => 'Export completed',
            'record_count' => 25,
            'exported_tables' => ['pages' => 10, 'tt_content' => 15],
            'file_path' => '/exports/test.json',
            'file_size' => 512,
            'format' => 'json',
            'metadata' => ['charset' => 'utf-8'],
            'exception' => null,
            'exception_code' => null,
        ];

        self::assertSame($expected, $result->toArray());
    }

    #[Test]
    public function toArrayHandlesFailureCase(): void
    {
        $result = new ExportResult(
            status: ExportStatus::EXPORT_FAILED,
            message: 'Export generation failed',
            exception: 'Database error',
            exceptionCode: 500
        );

        $expected = [
            'success' => false,
            'status' => 'export_failed',
            'message' => 'Export generation failed',
            'record_count' => 0,
            'exported_tables' => [],
            'file_path' => null,
            'file_size' => null,
            'format' => 'json',
            'metadata' => [],
            'exception' => 'Database error',
            'exception_code' => 500,
        ];

        self::assertSame($expected, $result->toArray());
    }
}
