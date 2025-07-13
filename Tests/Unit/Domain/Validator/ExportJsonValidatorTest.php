<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Tests\Unit\Domain\Validator;

use Cpsit\T3hauler\Domain\Enumeration\ExportValidationStatus;
use Cpsit\T3hauler\Domain\Validator\ExportJsonValidator;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ExportJsonValidator
 */
final class ExportJsonValidatorTest extends TestCase
{
    private ExportJsonValidator $subject;
    private vfsStreamDirectory $vfsRoot; // @phpstan-ignore-line

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new ExportJsonValidator();
        $this->vfsRoot = vfsStream::setup('test');
    }

    #[Test]
    public function validateReturnsFileNotFoundForNonExistentFile(): void
    {
        $result = $this->subject->validate(vfsStream::url('test/nonexistent.json'));

        self::assertFalse($result->isValid());
        self::assertEquals(ExportValidationStatus::FILE_NOT_FOUND, $result->status);
        self::assertStringStartsWith('Export file not found', $result->message);
    }

    #[Test]
    public function validateReturnsFileEmptyForEmptyFile(): void
    {
        $filePath = vfsStream::url('test/empty.json');
        file_put_contents($filePath, '');

        $result = $this->subject->validate($filePath);

        self::assertFalse($result->isValid());
        self::assertEquals(ExportValidationStatus::FILE_EMPTY, $result->status);
        self::assertEquals('Export file is empty', $result->message);
    }

    #[Test]
    public function validateReturnsInvalidJsonForMalformedJson(): void
    {
        $filePath = vfsStream::url('test/invalid.json');
        file_put_contents($filePath, '{invalid json');

        $result = $this->subject->validate($filePath);

        self::assertFalse($result->isValid());
        self::assertEquals(ExportValidationStatus::INVALID_JSON, $result->status);
        self::assertStringStartsWith('Export file contains invalid JSON', $result->message);
    }

    #[Test]
    public function validateReturnsInvalidStructureForNonObjectRoot(): void
    {
        $filePath = vfsStream::url('test/array.json');
        file_put_contents($filePath, '["not", "an", "object"]');

        $result = $this->subject->validate($filePath);

        self::assertFalse($result->isValid());
        self::assertEquals(ExportValidationStatus::INVALID_STRUCTURE, $result->status);
        self::assertStringStartsWith('Export file does not conform to schema', $result->message);
    }

    #[Test]
    public function validateReturnsInvalidStructureForMissingRequiredFields(): void
    {
        $data = ['metadata' => [], 'records' => []]; // missing relations
        $filePath = vfsStream::url('test/missing_relations.json');
        file_put_contents($filePath, json_encode($data));

        $result = $this->subject->validate($filePath);

        self::assertFalse($result->isValid());
        self::assertEquals(ExportValidationStatus::INVALID_STRUCTURE, $result->status);
        self::assertStringStartsWith('Export file does not conform to schema', $result->message);
    }

    #[Test]
    public function validateAcceptsValidMinimalExportFile(): void
    {
        $data = [
            'metadata' => [
                'created_at' => 1234567890,
                'created_by' => 't3hauler',
                'format_version' => '1.0',
                'charset' => 'utf-8',
            ],
            'records' => new \stdClass(),
            'relations' => [],
        ];

        $filePath = vfsStream::url('test/valid_minimal.json');
        file_put_contents($filePath, json_encode($data));

        $result = $this->subject->validate($filePath);

        self::assertTrue($result->isValid());
        self::assertEquals('Export file is valid', $result->message);
        self::assertEquals(0, $result->recordCount);
        self::assertEquals('json', $result->format);
    }

    #[Test]
    public function validateAcceptsValidExportFileWithRecords(): void
    {
        $data = [
            'metadata' => [
                'created_at' => 1234567890,
                'created_by' => 't3hauler',
                'format_version' => '1.0',
                'charset' => 'utf-8',
                'total_records' => 2,
                'exported_tables' => ['pages', 'tt_content'],
            ],
            'records' => [
                'pages' => [
                    '1' => [
                        'metadata' => [
                            'changeType' => 'insert',
                        ],
                        'fields' => [
                            'uid' => 1,
                            'pid' => 0,
                            'title' => 'Home',
                            'tstamp' => 1234567890,
                        ],
                    ],
                ],
                'tt_content' => [
                    'NEW123abc' => [
                        'metadata' => [
                            'changeType' => 'insert',
                        ],
                        'fields' => [
                            'uid' => 'NEW123abc',
                            'pid' => 1,
                            'header' => 'Content',
                            'CType' => 'text',
                        ],
                    ],
                ],
            ],
            'relations' => [
                [
                    'source_table' => 'tt_content',
                    'source_uid' => 'NEW123abc',
                    'target_table' => 'pages',
                    'target_uid' => 1,
                    'field_name' => 'pid',
                ],
            ],
        ];

        $filePath = vfsStream::url('test/valid_with_records.json');
        file_put_contents($filePath, json_encode($data));

        $result = $this->subject->validate($filePath);

        self::assertTrue($result->isValid());
        self::assertEquals('Export file is valid', $result->message);
        self::assertEquals(2, $result->recordCount);
        self::assertEquals($data['metadata'], $result->metadata);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidMetadataProvider(): array
    {
        return [
            'non_array_metadata' => [
                ['metadata' => 'not_array', 'records' => [], 'relations' => []],
                'Export file does not conform to schema',
            ],
            'missing_created_at' => [
                ['metadata' => ['created_by' => 't3hauler', 'format_version' => '1.0', 'charset' => 'utf-8'], 'records' => [], 'relations' => []],
                'Export file does not conform to schema',
            ],
            'invalid_created_at_type' => [
                ['metadata' => ['created_at' => 'not_int', 'created_by' => 't3hauler', 'format_version' => '1.0', 'charset' => 'utf-8'], 'records' => [], 'relations' => []],
                'Export file does not conform to schema',
            ],
            'negative_created_at' => [
                ['metadata' => ['created_at' => -1, 'created_by' => 't3hauler', 'format_version' => '1.0', 'charset' => 'utf-8'], 'records' => [], 'relations' => []],
                'Export file does not conform to schema',
            ],
            'empty_created_by' => [
                ['metadata' => ['created_at' => 123, 'created_by' => '', 'format_version' => '1.0', 'charset' => 'utf-8'], 'records' => [], 'relations' => []],
                'Export file does not conform to schema',
            ],
            'invalid_format_version' => [
                ['metadata' => ['created_at' => 123, 'created_by' => 't3hauler', 'format_version' => 'invalid', 'charset' => 'utf-8'], 'records' => [], 'relations' => []],
                'Export file does not conform to schema',
            ],
            'invalid_charset' => [
                ['metadata' => ['created_at' => 123, 'created_by' => 't3hauler', 'format_version' => '1.0', 'charset' => 'invalid'], 'records' => [], 'relations' => []],
                'Export file does not conform to schema',
            ],
        ];
    }

    #[Test]
    #[DataProvider('invalidMetadataProvider')]
    public function validateRejectsInvalidMetadata(array $data, string $expectedMessage): void
    {
        $filePath = vfsStream::url('test/invalid_metadata.json');
        file_put_contents($filePath, json_encode($data));

        $result = $this->subject->validate($filePath);

        self::assertFalse($result->isValid());
        self::assertEquals(ExportValidationStatus::INVALID_STRUCTURE, $result->status);
        self::assertStringStartsWith($expectedMessage, $result->message);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidRecordsProvider(): array
    {
        return [
            'non_array_records' => [
                ['metadata' => ['created_at' => 123, 'created_by' => 't3hauler', 'format_version' => '1.0', 'charset' => 'utf-8'], 'records' => 'not_array', 'relations' => []],
                'Export file does not conform to schema',
            ],
            'invalid_table_name' => [
                ['metadata' => ['created_at' => 123, 'created_by' => 't3hauler', 'format_version' => '1.0', 'charset' => 'utf-8'], 'records' => ['123invalid' => []], 'relations' => []],
                'Export file does not conform to schema',
            ],
            'non_array_table_records' => [
                ['metadata' => ['created_at' => 123, 'created_by' => 't3hauler', 'format_version' => '1.0', 'charset' => 'utf-8'], 'records' => ['pages' => 'not_array'], 'relations' => []],
                'Export file does not conform to schema',
            ],
            'invalid_record_id' => [
                ['metadata' => ['created_at' => 123, 'created_by' => 't3hauler', 'format_version' => '1.0', 'charset' => 'utf-8'], 'records' => ['pages' => ['invalid_id' => []]], 'relations' => []],
                'Export file does not conform to schema',
            ],
            'non_array_record_data' => [
                ['metadata' => ['created_at' => 123, 'created_by' => 't3hauler', 'format_version' => '1.0', 'charset' => 'utf-8'], 'records' => ['pages' => ['1' => 'not_array']], 'relations' => []],
                'Export file does not conform to schema',
            ],
            'missing_uid_field' => [
                ['metadata' => ['created_at' => 123, 'created_by' => 't3hauler', 'format_version' => '1.0', 'charset' => 'utf-8'], 'records' => ['pages' => ['1' => ['title' => 'Test']]], 'relations' => []],
                'Export file does not conform to schema',
            ],
        ];
    }

    #[Test]
    #[DataProvider('invalidRecordsProvider')]
    public function validateRejectsInvalidRecords(array $data, string $expectedMessage): void
    {
        $filePath = vfsStream::url('test/invalid_records.json');
        file_put_contents($filePath, json_encode($data));

        $result = $this->subject->validate($filePath);

        self::assertFalse($result->isValid());
        self::assertEquals(ExportValidationStatus::INVALID_STRUCTURE, $result->status);
        self::assertStringStartsWith($expectedMessage, $result->message);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidRelationsProvider(): array
    {
        return [
            'non_array_relations' => [
                ['metadata' => ['created_at' => 123, 'created_by' => 't3hauler', 'format_version' => '1.0', 'charset' => 'utf-8'], 'records' => [], 'relations' => 'not_array'],
                'Export file does not conform to schema',
            ],
            'non_array_relation' => [
                ['metadata' => ['created_at' => 123, 'created_by' => 't3hauler', 'format_version' => '1.0', 'charset' => 'utf-8'], 'records' => [], 'relations' => ['not_array']],
                'Export file does not conform to schema',
            ],
        ];
    }

    #[Test]
    #[DataProvider('invalidRelationsProvider')]
    public function validateRejectsInvalidRelations(array $data, string $expectedMessage): void
    {
        $filePath = vfsStream::url('test/invalid_relations.json');
        file_put_contents($filePath, json_encode($data));

        $result = $this->subject->validate($filePath);

        self::assertFalse($result->isValid());
        self::assertEquals(ExportValidationStatus::INVALID_STRUCTURE, $result->status);
        self::assertStringStartsWith($expectedMessage, $result->message);
    }

    #[Test]
    public function validateCalculatesRecordCountCorrectly(): void
    {
        $data = [
            'metadata' => [
                'created_at' => 1234567890,
                'created_by' => 't3hauler',
                'format_version' => '1.0',
                'charset' => 'utf-8',
            ],
            'records' => [
                'pages' => [
                    '1' => [
                        'metadata' => ['changeType' => 'insert'],
                        'fields' => ['uid' => 1],
                    ],
                    '2' => [
                        'metadata' => ['changeType' => 'update'],
                        'fields' => ['uid' => 2],
                    ],
                ],
                'tt_content' => [
                    'NEW123abc' => [
                        'metadata' => ['changeType' => 'insert'],
                        'fields' => ['uid' => 'NEW123abc'],
                    ],
                ],
                'sys_file' => new \stdClass(),
            ],
            'relations' => [],
        ];

        $filePath = vfsStream::url('test/count_test.json');
        file_put_contents($filePath, json_encode($data));

        $result = $this->subject->validate($filePath);

        self::assertTrue($result->isValid());
        self::assertEquals(3, $result->recordCount);
    }

    #[Test]
    public function validateHandlesComplexValidExportFile(): void
    {
        $data = [
            'metadata' => [
                'created_at' => 1751837906,
                'created_by' => 't3hauler',
                'format_version' => '1.0',
                'charset' => 'utf-8',
                'page_id' => 0,
                'export_type' => 'changed_data',
                'exclude_disabled' => false,
                'processed_at' => 1751837906,
                'total_records' => 1,
                'exported_tables' => ['tt_content'],
            ],
            'records' => [
                'tt_content' => [
                    'NEW686aecd2efc2d' => [
                        'metadata' => [
                            'changeType' => 'insert',
                        ],
                        'fields' => [
                            'uid' => 'NEW686aecd2efc2d',
                            'pid' => 1482,
                            'tstamp' => 1751837875,
                            'crdate' => 1751837847,
                            'deleted' => 0,
                            'hidden' => 0,
                            'CType' => 'text',
                            'header' => 'Test content',
                            'sorting' => 1024,
                        ],
                    ],
                ],
            ],
            'relations' => [],
        ];

        $filePath = vfsStream::url('test/complex_valid.json');
        file_put_contents($filePath, json_encode($data));

        $result = $this->subject->validate($filePath);

        self::assertTrue($result->isValid());
        self::assertEquals('Export file is valid', $result->message);
        self::assertEquals(1, $result->recordCount);
        self::assertEquals($data['metadata'], $result->metadata);
        self::assertGreaterThan(0, $result->fileSize);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function validRecordIdProvider(): array
    {
        return [
            'numeric_id' => ['123', 'uid'],
            'new_style_id' => ['NEW686aecd2efc2d', 'uid'],
            'new_style_lowercase' => ['NEWabcdef123456', 'uid'],
        ];
    }

    #[Test]
    #[DataProvider('validRecordIdProvider')]
    public function validateAcceptsValidRecordIds(string $recordId, string $uidField): void
    {
        $data = [
            'metadata' => [
                'created_at' => 1234567890,
                'created_by' => 't3hauler',
                'format_version' => '1.0',
                'charset' => 'utf-8',
            ],
            'records' => [
                'tt_content' => [
                    $recordId => [
                        'metadata' => [
                            'changeType' => 'insert',
                        ],
                        'fields' => [
                            $uidField => $recordId,
                            'pid' => 1,
                        ],
                    ],
                ],
            ],
            'relations' => [],
        ];

        $filePath = vfsStream::url('test/valid_record_id.json');
        file_put_contents($filePath, json_encode($data));

        $result = $this->subject->validate($filePath);

        self::assertTrue($result->isValid());
        self::assertEquals(1, $result->recordCount);
    }

    #[Test]
    public function validateHandlesEmptyRecordsAndRelations(): void
    {
        $data = [
            'metadata' => [
                'created_at' => 1234567890,
                'created_by' => 't3hauler',
                'format_version' => '1.0',
                'charset' => 'utf-8',
                'total_records' => 0,
                'exported_tables' => [],
            ],
            'records' => new \stdClass(),
            'relations' => [],
        ];

        $filePath = vfsStream::url('test/empty_data.json');
        file_put_contents($filePath, json_encode($data));

        $result = $this->subject->validate($filePath);

        self::assertTrue($result->isValid());
        self::assertEquals(0, $result->recordCount);
        self::assertEquals($data['metadata'], $result->metadata);
    }
}
