<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Tests\Unit\Domain\Model;

use Cpsit\T3hauler\Domain\Enumeration\MigrationStatus;
use Cpsit\T3hauler\Domain\Model\MigrationFile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MigrationFileTest extends TestCase
{
    private MigrationFile $subject;
    private array $sampleData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sampleData = [
            'metadata' => [
                'format_version' => '1.0',
                'migration_id' => '2024-07-14_07:30:15_a1b2c3d4',
                'description' => 'Test migration file',
                'author' => 'Test Author',
                'created_at' => 1720945815,
                'source_hash' => 'test_hash_123',
                'name' => 'Test Migration',
                'exported_tables' => ['pages', 'tt_content'],
                'total_records' => 25,
            ],
            'records' => [
                'pages' => [
                    ['uid' => 1, 'title' => 'Page 1'],
                    ['uid' => 2, 'title' => 'Page 2'],
                ],
                'tt_content' => [
                    ['uid' => 101, 'header' => 'Content 1'],
                    ['uid' => 102, 'header' => 'Content 2'],
                    ['uid' => 103, 'header' => 'Content 3'],
                ],
            ],
        ];

        $this->subject = new MigrationFile(
            '2024-07-14_07:30:15_a1b2c3d4',
            '2024-07-14_07:30:15_a1b2c3d4.json',
            'fileadmin/migrations',
            '/var/www/fileadmin/migrations/2024-07-14_07:30:15_a1b2c3d4.json',
            $this->sampleData,
            MigrationStatus::PENDING
        );
    }

    #[Test]
    public function constructorSetsPropertiesCorrectly(): void
    {
        self::assertSame('2024-07-14_07:30:15_a1b2c3d4', $this->subject->getId());
        self::assertSame('2024-07-14_07:30:15_a1b2c3d4.json', $this->subject->getFilename());
        self::assertSame('fileadmin/migrations', $this->subject->getRelativePath());
        self::assertSame('/var/www/fileadmin/migrations/2024-07-14_07:30:15_a1b2c3d4.json', $this->subject->getAbsolutePath());
        self::assertSame($this->sampleData, $this->subject->getData());
        self::assertSame(MigrationStatus::PENDING, $this->subject->getStatus());
    }

    #[Test]
    public function getMetadataReturnsCorrectData(): void
    {
        $metadata = $this->subject->getMetadata();

        self::assertIsArray($metadata);
        self::assertSame('Test migration file', $metadata['description']);
        self::assertSame('Test Author', $metadata['author']);
        self::assertSame(1720945815, $metadata['created_at']);
    }

    #[Test]
    public function getRecordsReturnsCorrectData(): void
    {
        $records = $this->subject->getRecords();

        self::assertIsArray($records);
        self::assertArrayHasKey('pages', $records);
        self::assertArrayHasKey('tt_content', $records);
        self::assertCount(2, $records['pages']);
        self::assertCount(3, $records['tt_content']);
    }

    #[Test]
    public function metadataHelperMethodsWorkCorrectly(): void
    {
        self::assertSame('Test migration file', $this->subject->getDescription());
        self::assertSame('Test Author', $this->subject->getAuthor());
        self::assertSame(1720945815, $this->subject->getCreatedAt());
        self::assertSame('2024-07-14 08:30:15', $this->subject->getCreatedAtFormatted());
        self::assertSame('Test Migration', $this->subject->getName());
        self::assertSame('test_hash_123', $this->subject->getSourceHash());
        self::assertSame('1.0', $this->subject->getFormatVersion());
    }

    #[Test]
    public function recordCountMethodsWorkCorrectly(): void
    {
        self::assertSame(2, $this->subject->getTableCount());
        self::assertSame(25, $this->subject->getRecordCount());
        self::assertSame(['pages', 'tt_content'], $this->subject->getExportedTables());
    }

    #[Test]
    public function recordHelperMethodsWorkCorrectly(): void
    {
        $pagesRecords = $this->subject->getRecordsForTable('pages');
        self::assertCount(2, $pagesRecords);
        self::assertSame('Page 1', $pagesRecords[0]['title']);

        $contentRecords = $this->subject->getRecordsForTable('tt_content');
        self::assertCount(3, $contentRecords);
        self::assertSame('Content 1', $contentRecords[0]['header']);

        $nonExistentRecords = $this->subject->getRecordsForTable('non_existent');
        self::assertSame([], $nonExistentRecords);
    }

    #[Test]
    public function hasRecordsForTableWorkCorrectly(): void
    {
        self::assertTrue($this->subject->hasRecordsForTable('pages'));
        self::assertTrue($this->subject->hasRecordsForTable('tt_content'));
        self::assertFalse($this->subject->hasRecordsForTable('non_existent'));
    }

    #[Test]
    public function getTablesWithRecordsReturnsCorrectTables(): void
    {
        $tables = $this->subject->getTablesWithRecords();

        self::assertCount(2, $tables);
        self::assertContains('pages', $tables);
        self::assertContains('tt_content', $tables);
    }

    #[Test]
    public function validationMethodsWorkCorrectly(): void
    {
        self::assertTrue($this->subject->isValid());
        self::assertTrue($this->subject->hasValidStructure());
        self::assertTrue($this->subject->hasValidMetadata());
    }

    #[Test]
    public function validationFailsForInvalidData(): void
    {
        $invalidData = [
            'metadata' => [
                'format_version' => '2.0', // Invalid version
                'migration_id' => '2024-07-14_07:30:15_a1b2c3d4',
            ],
            'records' => [],
        ];

        $invalidFile = new MigrationFile(
            '2024-07-14_07:30:15_invalid',
            'invalid.json',
            'fileadmin/migrations',
            '/var/www/fileadmin/migrations/invalid.json',
            $invalidData
        );

        self::assertFalse($invalidFile->isValid());
        self::assertTrue($invalidFile->hasValidStructure());
        self::assertFalse($invalidFile->hasValidMetadata());
    }

    #[Test]
    public function validationFailsForMissingStructure(): void
    {
        $incompleteData = [
            'metadata' => $this->sampleData['metadata'],
            // Missing 'records' section
        ];

        $incompleteFile = new MigrationFile(
            '2024-07-14_07:30:15_incomplete',
            'incomplete.json',
            'fileadmin/migrations',
            '/var/www/fileadmin/migrations/incomplete.json',
            $incompleteData
        );

        self::assertFalse($incompleteFile->isValid());
        self::assertFalse($incompleteFile->hasValidStructure());
    }

    #[Test]
    public function statusCanBeChanged(): void
    {
        self::assertSame(MigrationStatus::PENDING, $this->subject->getStatus());

        $this->subject->setStatus(MigrationStatus::APPLIED);
        self::assertSame(MigrationStatus::APPLIED, $this->subject->getStatus());

        $this->subject->setStatus(MigrationStatus::FAILED);
        self::assertSame(MigrationStatus::FAILED, $this->subject->getStatus());
    }

    #[Test]
    public function getSummaryReturnsCompleteInformation(): void
    {
        $summary = $this->subject->getSummary();

        self::assertIsArray($summary);
        self::assertSame('2024-07-14_07:30:15_a1b2c3d4', $summary['id']);
        self::assertSame('2024-07-14_07:30:15_a1b2c3d4.json', $summary['filename']);
        self::assertSame('fileadmin/migrations', $summary['path']);
        self::assertSame('pending', $summary['status']);
        self::assertSame('Test migration file', $summary['description']);
        self::assertSame('Test Author', $summary['author']);
        self::assertSame('2024-07-14 08:30:15', $summary['created_at']);
        self::assertSame('1.0', $summary['format_version']);
        self::assertSame(2, $summary['total_tables']);
        self::assertSame(25, $summary['total_records']);
        self::assertTrue($summary['valid']);
        self::assertCount(2, $summary['tables_with_records']);
        self::assertArrayHasKey('pages', $summary['record_counts']);
        self::assertArrayHasKey('tt_content', $summary['record_counts']);
        self::assertSame(2, $summary['record_counts']['pages']);
        self::assertSame(3, $summary['record_counts']['tt_content']);
    }

    #[Test]
    public function toArrayReturnsLegacyFormat(): void
    {
        $array = $this->subject->toArray();

        self::assertIsArray($array);
        self::assertSame('2024-07-14_07:30:15_a1b2c3d4', $array['id']);
        self::assertSame('2024-07-14_07:30:15_a1b2c3d4.json', $array['file']);
        self::assertSame('fileadmin/migrations', $array['path']);
        self::assertSame('/var/www/fileadmin/migrations/2024-07-14_07:30:15_a1b2c3d4.json', $array['absolute_path']);
        self::assertSame($this->sampleData, $array['data']);
        self::assertSame(MigrationStatus::PENDING, $array['status']);
    }

    #[Test]
    public function fromArrayCreatesCorrectInstance(): void
    {
        $arrayData = [
            'id' => '2024-07-14_08:00:00_xyz98765',
            'file' => '2024-07-14_08:00:00_xyz98765.json',
            'path' => 'uploads/migrations',
            'absolute_path' => '/var/www/uploads/migrations/2024-07-14_08:00:00_xyz98765.json',
            'data' => $this->sampleData,
            'status' => MigrationStatus::APPLIED,
        ];

        $migrationFile = MigrationFile::fromArray($arrayData);

        self::assertSame('2024-07-14_08:00:00_xyz98765', $migrationFile->getId());
        self::assertSame('2024-07-14_08:00:00_xyz98765.json', $migrationFile->getFilename());
        self::assertSame('uploads/migrations', $migrationFile->getRelativePath());
        self::assertSame('/var/www/uploads/migrations/2024-07-14_08:00:00_xyz98765.json', $migrationFile->getAbsolutePath());
        self::assertSame($this->sampleData, $migrationFile->getData());
        self::assertSame(MigrationStatus::APPLIED, $migrationFile->getStatus());
    }

    #[Test]
    public function handlesEmptyMetadataGracefully(): void
    {
        $dataWithoutMetadata = [
            'metadata' => [],
            'records' => [],
        ];

        $fileWithoutMetadata = new MigrationFile(
            '2024-07-14_07:30:15_empty',
            'empty.json',
            'fileadmin/migrations',
            '/var/www/fileadmin/migrations/empty.json',
            $dataWithoutMetadata
        );

        self::assertSame('No description', $fileWithoutMetadata->getDescription());
        self::assertSame('Unknown', $fileWithoutMetadata->getAuthor());
        self::assertSame(0, $fileWithoutMetadata->getCreatedAt());
        self::assertSame('Unknown', $fileWithoutMetadata->getCreatedAtFormatted());
        self::assertSame('2024-07-14_07:30:15_empty', $fileWithoutMetadata->getName());
        self::assertSame('', $fileWithoutMetadata->getSourceHash());
        self::assertSame('unknown', $fileWithoutMetadata->getFormatVersion());
        self::assertSame(0, $fileWithoutMetadata->getTableCount());
        self::assertSame(0, $fileWithoutMetadata->getRecordCount());
        self::assertSame([], $fileWithoutMetadata->getExportedTables());
    }

    #[Test]
    public function handlesEmptyRecordsGracefully(): void
    {
        $dataWithEmptyRecords = [
            'metadata' => $this->sampleData['metadata'],
            'records' => [],
        ];

        $fileWithEmptyRecords = new MigrationFile(
            '2024-07-14_07:30:15_empty_records',
            'empty_records.json',
            'fileadmin/migrations',
            '/var/www/fileadmin/migrations/empty_records.json',
            $dataWithEmptyRecords
        );

        self::assertSame([], $fileWithEmptyRecords->getTablesWithRecords());
        self::assertSame([], $fileWithEmptyRecords->getRecordsForTable('pages'));
        self::assertFalse($fileWithEmptyRecords->hasRecordsForTable('pages'));
    }
}
