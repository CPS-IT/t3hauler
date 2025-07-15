<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Tests\Unit\Domain\Validator;

use Cpsit\T3hauler\Domain\Enumeration\ExportValidationStatus;
use Cpsit\T3hauler\Domain\Validator\ExportJsonValidator;
use Cpsit\T3hauler\Service\FilesystemInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Simple unit tests for ExportJsonValidator
 */
final class ExportJsonValidatorSimpleTest extends TestCase
{
    private ExportJsonValidator $subject;
    private MockObject&FilesystemInterface $filesystem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filesystem = $this->createMock(FilesystemInterface::class);
        $this->subject = new ExportJsonValidator($this->filesystem);
    }

    #[Test]
    public function validateReturnsFileNotFoundForNonExistentFile(): void
    {
        $filePath = '/path/to/nonexistent.json';
        $this->filesystem->expects(self::once())
            ->method('exists')
            ->with($filePath)
            ->willReturn(false);

        $result = $this->subject->validate($filePath);

        self::assertFalse($result->isValid());
        self::assertEquals(ExportValidationStatus::FILE_NOT_FOUND, $result->status);
        self::assertStringStartsWith('Export file not found', $result->message);
    }

    #[Test]
    public function validateReturnsFileEmptyForEmptyFile(): void
    {
        $filePath = '/path/to/empty.json';
        $this->filesystem->expects(self::once())
            ->method('exists')
            ->with($filePath)
            ->willReturn(true);
        $this->filesystem->expects(self::once())
            ->method('getFileContents')
            ->with($filePath)
            ->willReturn('');

        $result = $this->subject->validate($filePath);

        self::assertFalse($result->isValid());
        self::assertEquals(ExportValidationStatus::FILE_EMPTY, $result->status);
        self::assertEquals('Export file is empty', $result->message);
    }

    #[Test]
    public function validateReturnsInvalidJsonForMalformedJson(): void
    {
        $filePath = '/path/to/invalid.json';
        $this->filesystem->expects(self::once())
            ->method('exists')
            ->with($filePath)
            ->willReturn(true);
        $this->filesystem->expects(self::once())
            ->method('getFileContents')
            ->with($filePath)
            ->willReturn('{invalid json');

        $result = $this->subject->validate($filePath);

        self::assertFalse($result->isValid());
        self::assertEquals(ExportValidationStatus::INVALID_JSON, $result->status);
        self::assertStringStartsWith('Export file contains invalid JSON', $result->message);
    }

    #[Test]
    public function validateAcceptsValidExportFile(): void
    {
        $data = [
            'metadata' => [
                'created_at' => 1234567890,
                'created_by' => 't3hauler',
                'format_version' => '1.0',
                'charset' => 'utf-8',
                'total_records' => 1,
                'exported_tables' => ['tt_content'],
            ],
            'records' => [
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
            'relations' => [],
        ];

        $filePath = '/path/to/valid.json';
        $jsonContent = json_encode($data);
        // Mock schema file access
        $schemaPath = '/Users/d.wenzel/projekt/zug13/app/vendor/cpsit/t3hauler/Classes/Domain/Validator/../../../Resources/Public/Spec/export.schema.json';

        $this->filesystem->expects(self::exactly(2))
            ->method('exists')
            ->willReturnCallback(function ($path) use ($filePath, $schemaPath) {
                if ($path === $filePath) {
                    return true;
                }
                if ($path === $schemaPath) {
                    return true;
                }
                return false;
            });

        $this->filesystem->expects(self::exactly(2))
            ->method('getFileContents')
            ->willReturnCallback(function ($path) use ($filePath, $schemaPath, $jsonContent) {
                if ($path === $filePath) {
                    return $jsonContent;
                }
                if ($path === $schemaPath) {
                    return file_get_contents($schemaPath);
                }
                return false;
            });

        $this->filesystem->expects(self::once())
            ->method('getFileSize')
            ->with($filePath)
            ->willReturn(strlen($jsonContent));

        $result = $this->subject->validate($filePath);

        self::assertTrue($result->isValid());
        self::assertEquals('Export file is valid', $result->message);
        self::assertEquals(1, $result->recordCount);
        self::assertEquals($data['metadata'], $result->metadata);
        self::assertGreaterThan(0, $result->fileSize);
    }
}
