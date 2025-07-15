<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Tests\Unit\Domain\Repository;

use Cpsit\T3hauler\Configuration\T3HaulerConfiguration;
use Cpsit\T3hauler\Domain\Repository\MigrationFileRepository;
use Cpsit\T3hauler\Service\FilesystemInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MigrationFileRepositoryTest extends TestCase
{
    private MigrationFileRepository $subject;
    private MockObject&T3HaulerConfiguration $configurationMock;
    private MockObject&FilesystemInterface $filesystemMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configurationMock = $this->createMock(T3HaulerConfiguration::class);
        $this->filesystemMock = $this->createMock(FilesystemInterface::class);

        $this->subject = new MigrationFileRepository(
            $this->configurationMock,
            $this->filesystemMock
        );
    }

    #[Test]
    public function constructorInjectsFilesystemDependency(): void
    {
        // This test verifies that the repository can be instantiated with filesystem dependency
        self::assertInstanceOf(MigrationFileRepository::class, $this->subject);
    }

    #[Test]
    public function findAllAsObjectsUsesFilesystemAbstractionForPathResolution(): void
    {
        $this->configurationMock
            ->expects(self::once())
            ->method('getMigrationPaths')
            ->willReturn(['fileadmin/migrations']);

        $this->filesystemMock
            ->expects(self::once())
            ->method('getAbsoluteFilePath')
            ->with('fileadmin/migrations')
            ->willReturn('/var/www/fileadmin/migrations');

        $this->filesystemMock
            ->expects(self::once())
            ->method('isDirectory')
            ->with('/var/www/fileadmin/migrations')
            ->willReturn(false); // Directory doesn't exist

        $result = $this->subject->findAllAsObjects();

        self::assertIsArray($result);
        self::assertEmpty($result);
    }

    #[Test]
    public function findAllAsObjectsUsesFilesystemAbstractionForFileDiscovery(): void
    {
        $this->configurationMock
            ->expects(self::once())
            ->method('getMigrationPaths')
            ->willReturn(['fileadmin/migrations']);

        $this->filesystemMock
            ->expects(self::once())
            ->method('getAbsoluteFilePath')
            ->with('fileadmin/migrations')
            ->willReturn('/var/www/fileadmin/migrations');

        $this->filesystemMock
            ->expects(self::once())
            ->method('isDirectory')
            ->with('/var/www/fileadmin/migrations')
            ->willReturn(true);

        $this->filesystemMock
            ->expects(self::once())
            ->method('glob')
            ->with('/var/www/fileadmin/migrations/*.json')
            ->willReturn([]); // No files found

        $result = $this->subject->findAllAsObjects();

        self::assertIsArray($result);
        self::assertEmpty($result);
    }

    #[Test]
    public function existsUsesFilesystemAbstractionForFileChecks(): void
    {
        $migrationId = '2024-07-14_07:30:15_a1b2c3d4';

        $this->configurationMock
            ->expects(self::once())
            ->method('getMigrationPaths')
            ->willReturn(['fileadmin/migrations']);

        $this->filesystemMock
            ->expects(self::once())
            ->method('getAbsoluteFilePath')
            ->with('fileadmin/migrations')
            ->willReturn('/var/www/fileadmin/migrations');

        $this->filesystemMock
            ->expects(self::once())
            ->method('isDirectory')
            ->with('/var/www/fileadmin/migrations')
            ->willReturn(true);

        $this->filesystemMock
            ->expects(self::once())
            ->method('exists')
            ->with('/var/www/fileadmin/migrations/2024-07-14_07:30:15_a1b2c3d4.json')
            ->willReturn(false);

        $result = $this->subject->exists($migrationId);

        self::assertFalse($result);
    }

    #[Test]
    public function markAsAppliedUsesFilesystemAbstractionForFileWrites(): void
    {
        $migrationId = '2024-07-14_07:30:15_a1b2c3d4';
        $sampleData = [
            'metadata' => [
                'format_version' => '1.0',
                'migration_id' => $migrationId,
                'description' => 'Test migration',
            ],
            'records' => [],
        ];

        $this->configurationMock
            ->expects(self::once())
            ->method('getMigrationPaths')
            ->willReturn(['fileadmin/migrations']);

        $this->filesystemMock
            ->expects(self::once())
            ->method('getAbsoluteFilePath')
            ->with('fileadmin/migrations')
            ->willReturn('/var/www/fileadmin/migrations');

        $this->filesystemMock
            ->expects(self::once())
            ->method('isDirectory')
            ->with('/var/www/fileadmin/migrations')
            ->willReturn(true);

        $this->filesystemMock
            ->expects(self::atLeastOnce())
            ->method('exists')
            ->with('/var/www/fileadmin/migrations/2024-07-14_07:30:15_a1b2c3d4.json')
            ->willReturn(true);

        $this->filesystemMock
            ->expects(self::once())
            ->method('getFileContents')
            ->with('/var/www/fileadmin/migrations/2024-07-14_07:30:15_a1b2c3d4.json')
            ->willReturn(json_encode($sampleData));

        $this->filesystemMock
            ->expects(self::once())
            ->method('putFileContents')
            ->with('/var/www/fileadmin/migrations/2024-07-14_07:30:15_a1b2c3d4.applied', self::isType('string'))
            ->willReturn(19);

        $result = $this->subject->markAsApplied($migrationId);

        self::assertTrue($result);
    }

    #[Test]
    public function clearStatusUsesFilesystemAbstractionForFileDeletion(): void
    {
        $migrationId = '2024-07-14_07:30:15_a1b2c3d4';
        $sampleData = [
            'metadata' => [
                'format_version' => '1.0',
                'migration_id' => $migrationId,
                'description' => 'Test migration',
            ],
            'records' => [],
        ];

        $this->configurationMock
            ->expects(self::once())
            ->method('getMigrationPaths')
            ->willReturn(['fileadmin/migrations']);

        $this->filesystemMock
            ->expects(self::once())
            ->method('getAbsoluteFilePath')
            ->with('fileadmin/migrations')
            ->willReturn('/var/www/fileadmin/migrations');

        $this->filesystemMock
            ->expects(self::once())
            ->method('isDirectory')
            ->with('/var/www/fileadmin/migrations')
            ->willReturn(true);

        // Mock file existence checks
        $this->filesystemMock
            ->expects(self::atLeastOnce())
            ->method('exists')
            ->willReturnCallback(function ($path) {
                if (str_ends_with($path, '.json')) {
                    return true; // Main migration file exists
                }
                return true; // Marker files exist
            });

        $this->filesystemMock
            ->expects(self::once())
            ->method('getFileContents')
            ->with('/var/www/fileadmin/migrations/2024-07-14_07:30:15_a1b2c3d4.json')
            ->willReturn(json_encode($sampleData));

        $this->filesystemMock
            ->expects(self::exactly(2))
            ->method('deleteFile')
            ->willReturn(true);

        $result = $this->subject->clearStatus($migrationId);

        self::assertTrue($result);
    }

    #[Test]
    public function validatePathsUsesFilesystemAbstractionForPathValidation(): void
    {
        $this->configurationMock
            ->expects(self::once())
            ->method('getMigrationPaths')
            ->willReturn(['fileadmin/migrations']);

        $this->filesystemMock
            ->expects(self::once())
            ->method('getAbsoluteFilePath')
            ->with('fileadmin/migrations')
            ->willReturn('/var/www/fileadmin/migrations');

        $this->filesystemMock
            ->expects(self::once())
            ->method('isDirectory')
            ->with('/var/www/fileadmin/migrations')
            ->willReturn(true);

        $this->filesystemMock
            ->expects(self::once())
            ->method('isWritable')
            ->with('/var/www/fileadmin/migrations')
            ->willReturn(true);

        $this->filesystemMock
            ->expects(self::once())
            ->method('glob')
            ->with('/var/www/fileadmin/migrations/*.json')
            ->willReturn(['file1.json', 'file2.json']);

        $result = $this->subject->validatePaths();

        self::assertIsArray($result);
        self::assertCount(1, $result);
        self::assertSame('fileadmin/migrations', $result[0]['path']);
        self::assertSame('/var/www/fileadmin/migrations', $result[0]['absolute_path']);
        self::assertTrue($result[0]['exists']);
        self::assertTrue($result[0]['writable']);
        self::assertSame(2, $result[0]['migration_count']);
    }
}
