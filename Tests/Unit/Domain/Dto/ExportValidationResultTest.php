<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Tests\Unit\Domain\Dto;

use Cpsit\T3hauler\Domain\Dto\ExportValidationResult;
use Cpsit\T3hauler\Domain\Enumeration\ExportValidationStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ExportValidationResult DTO
 */
final class ExportValidationResultTest extends TestCase
{
    #[Test]
    public function constructorSetsAllProperties(): void
    {
        $status = ExportValidationStatus::VALID;
        $message = 'Export file is valid';
        $fileSize = 2048;
        $recordCount = 50;
        $format = 'json';
        $metadata = ['version' => '1.0', 'charset' => 'utf-8'];

        $result = new ExportValidationResult(
            status: $status,
            message: $message,
            fileSize: $fileSize,
            recordCount: $recordCount,
            format: $format,
            metadata: $metadata
        );

        self::assertSame($status, $result->status);
        self::assertSame($message, $result->message);
        self::assertSame($fileSize, $result->fileSize);
        self::assertSame($recordCount, $result->recordCount);
        self::assertSame($format, $result->format);
        self::assertSame($metadata, $result->metadata);
    }

    #[Test]
    public function constructorUsesDefaultValues(): void
    {
        $result = new ExportValidationResult(
            status: ExportValidationStatus::INVALID,
            message: 'Invalid export file'
        );

        self::assertSame(ExportValidationStatus::INVALID, $result->status);
        self::assertSame('Invalid export file', $result->message);
        self::assertNull($result->fileSize);
        self::assertSame(0, $result->recordCount);
        self::assertSame('json', $result->format);
        self::assertSame([], $result->metadata);
    }

    #[Test]
    public function isValidReturnsTrueForValidStatus(): void
    {
        $result = new ExportValidationResult(
            status: ExportValidationStatus::VALID,
            message: 'File is valid'
        );

        self::assertTrue($result->isValid());
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
    public function isValidReturnsFalseForInvalidStatus(ExportValidationStatus $status): void
    {
        $result = new ExportValidationResult(
            status: $status,
            message: 'Some error message'
        );

        self::assertFalse($result->isValid());
    }

    #[Test]
    public function isInvalidReturnsFalseForValidStatus(): void
    {
        $result = new ExportValidationResult(
            status: ExportValidationStatus::VALID,
            message: 'File is valid'
        );

        self::assertFalse($result->isInvalid());
    }

    #[Test]
    #[DataProvider('invalidStatusProvider')]
    public function isInvalidReturnsTrueForInvalidStatus(ExportValidationStatus $status): void
    {
        $result = new ExportValidationResult(
            status: $status,
            message: 'Some error message'
        );

        self::assertTrue($result->isInvalid());
    }

    #[Test]
    public function hasFileReturnsTrueWhenFileSizeIsSet(): void
    {
        $result = new ExportValidationResult(
            status: ExportValidationStatus::VALID,
            message: 'File is valid',
            fileSize: 1024
        );

        self::assertTrue($result->hasFile());
    }

    #[Test]
    public function hasFileReturnsFalseWhenFileSizeIsNull(): void
    {
        $result = new ExportValidationResult(
            status: ExportValidationStatus::FILE_NOT_FOUND,
            message: 'File not found',
            fileSize: null
        );

        self::assertFalse($result->hasFile());
    }

    #[Test]
    public function hasFileReturnsTrueWhenFileSizeIsZero(): void
    {
        $result = new ExportValidationResult(
            status: ExportValidationStatus::FILE_EMPTY,
            message: 'File is empty',
            fileSize: 0
        );

        self::assertTrue($result->hasFile());
    }

    #[Test]
    public function hasRecordsReturnsTrueWhenRecordCountIsGreaterThanZero(): void
    {
        $result = new ExportValidationResult(
            status: ExportValidationStatus::VALID,
            message: 'File is valid',
            recordCount: 10
        );

        self::assertTrue($result->hasRecords());
    }

    #[Test]
    public function hasRecordsReturnsFalseWhenRecordCountIsZero(): void
    {
        $result = new ExportValidationResult(
            status: ExportValidationStatus::VALID,
            message: 'File is valid',
            recordCount: 0
        );

        self::assertFalse($result->hasRecords());
    }

    #[Test]
    public function hasMetadataReturnsTrueWhenMetadataIsNotEmpty(): void
    {
        $result = new ExportValidationResult(
            status: ExportValidationStatus::VALID,
            message: 'File is valid',
            metadata: ['version' => '1.0']
        );

        self::assertTrue($result->hasMetadata());
    }

    #[Test]
    public function hasMetadataReturnsFalseWhenMetadataIsEmpty(): void
    {
        $result = new ExportValidationResult(
            status: ExportValidationStatus::VALID,
            message: 'File is valid',
            metadata: []
        );

        self::assertFalse($result->hasMetadata());
    }

    #[Test]
    public function hasMetadataReturnsTrueWhenMetadataContainsElements(): void
    {
        $result = new ExportValidationResult(
            status: ExportValidationStatus::VALID,
            message: 'File is valid',
            metadata: ['empty' => '', 'null_value' => null, 'zero' => 0, 'false' => false]
        );

        self::assertTrue($result->hasMetadata());
    }

    #[Test]
    public function validFactoryMethodCreatesValidResult(): void
    {
        $message = 'Export file is valid';
        $fileSize = 4096;
        $recordCount = 200;
        $format = 'xml';
        $metadata = ['created_at' => 1234567890, 'created_by' => 't3hauler'];

        $result = ExportValidationResult::valid(
            message: $message,
            fileSize: $fileSize,
            recordCount: $recordCount,
            format: $format,
            metadata: $metadata
        );

        self::assertSame(ExportValidationStatus::VALID, $result->status);
        self::assertSame($message, $result->message);
        self::assertSame($fileSize, $result->fileSize);
        self::assertSame($recordCount, $result->recordCount);
        self::assertSame($format, $result->format);
        self::assertSame($metadata, $result->metadata);
        self::assertTrue($result->isValid());
    }

    #[Test]
    public function validFactoryMethodUsesDefaultValues(): void
    {
        $result = ExportValidationResult::valid(
            message: 'File is valid',
            fileSize: 1024
        );

        self::assertSame(0, $result->recordCount);
        self::assertSame('json', $result->format);
        self::assertSame([], $result->metadata);
    }

    #[Test]
    public function invalidFactoryMethodCreatesInvalidResult(): void
    {
        $status = ExportValidationStatus::INVALID_STRUCTURE;
        $message = 'Export file has invalid structure';

        $result = ExportValidationResult::invalid(
            status: $status,
            message: $message
        );

        self::assertSame($status, $result->status);
        self::assertSame($message, $result->message);
        self::assertNull($result->fileSize);
        self::assertSame(0, $result->recordCount);
        self::assertSame('json', $result->format);
        self::assertSame([], $result->metadata);
        self::assertTrue($result->isInvalid());
    }

    #[Test]
    public function toArrayReturnsCorrectStructureForValidResult(): void
    {
        $result = new ExportValidationResult(
            status: ExportValidationStatus::VALID,
            message: 'Export file is valid',
            fileSize: 2048,
            recordCount: 75,
            format: 'json',
            metadata: ['version' => '1.0', 'charset' => 'utf-8']
        );

        $expected = [
            'valid' => true,
            'status' => 'valid',
            'message' => 'Export file is valid',
            'file_size' => 2048,
            'record_count' => 75,
            'format' => 'json',
            'metadata' => ['version' => '1.0', 'charset' => 'utf-8'],
        ];

        self::assertSame($expected, $result->toArray());
    }

    #[Test]
    public function toArrayReturnsCorrectStructureForInvalidResult(): void
    {
        $result = new ExportValidationResult(
            status: ExportValidationStatus::FILE_NOT_FOUND,
            message: 'Export file not found'
        );

        $expected = [
            'valid' => false,
            'status' => 'file_not_found',
            'message' => 'Export file not found',
            'file_size' => null,
            'record_count' => 0,
            'format' => 'json',
            'metadata' => [],
        ];

        self::assertSame($expected, $result->toArray());
    }

    #[Test]
    public function toArrayHandlesNullFileSize(): void
    {
        $result = new ExportValidationResult(
            status: ExportValidationStatus::VALID,
            message: 'Valid without file size',
            fileSize: null,
            recordCount: 10,
            format: 'xml',
            metadata: ['test' => 'value']
        );

        $array = $result->toArray();

        self::assertNull($array['file_size']);
        self::assertSame(10, $array['record_count']);
        self::assertSame('xml', $array['format']);
        self::assertSame(['test' => 'value'], $array['metadata']);
    }

    /**
     * @return array<string, array{ExportValidationStatus, string}>
     */
    public static function allStatusProvider(): array
    {
        return [
            'valid' => [ExportValidationStatus::VALID, 'Valid status'],
            'invalid' => [ExportValidationStatus::INVALID, 'Invalid status'],
            'file_not_found' => [ExportValidationStatus::FILE_NOT_FOUND, 'File not found'],
            'file_empty' => [ExportValidationStatus::FILE_EMPTY, 'File is empty'],
            'invalid_json' => [ExportValidationStatus::INVALID_JSON, 'Invalid JSON'],
            'invalid_structure' => [ExportValidationStatus::INVALID_STRUCTURE, 'Invalid structure'],
            'parse_error' => [ExportValidationStatus::PARSE_ERROR, 'Parse error'],
        ];
    }

    #[Test]
    #[DataProvider('allStatusProvider')]
    public function toArrayIncludesCorrectStatusValue(ExportValidationStatus $status, string $message): void
    {
        $result = new ExportValidationResult(
            status: $status,
            message: $message
        );

        $array = $result->toArray();

        self::assertSame($status->value, $array['status']);
        self::assertSame($status->isValid(), $array['valid']);
    }

    #[Test]
    public function constructorAcceptsZeroRecordCount(): void
    {
        $result = new ExportValidationResult(
            status: ExportValidationStatus::VALID,
            message: 'Valid with zero records',
            recordCount: 0
        );

        self::assertSame(0, $result->recordCount);
        self::assertFalse($result->hasRecords());
    }

    #[Test]
    public function constructorAcceptsNegativeRecordCount(): void
    {
        $result = new ExportValidationResult(
            status: ExportValidationStatus::INVALID,
            message: 'Invalid with negative count',
            recordCount: -1
        );

        self::assertSame(-1, $result->recordCount);
        self::assertFalse($result->hasRecords());
    }
}
