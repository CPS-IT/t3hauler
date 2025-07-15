<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Tests\Unit\Domain\Repository;

use Cpsit\T3hauler\Configuration\T3HaulerConfiguration;
use Cpsit\T3hauler\Domain\Enumeration\MigrationStatus;
use Cpsit\T3hauler\Domain\Model\MigrationFile;
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
        self::assertInstanceOf(MigrationFileRepository::class, $this->subject);
    }

    #[Test]
    public function findAllAsObjectsReturnsEmptyWhenNoDirectories(): void
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
            ->willReturn(false);

        $result = $this->subject->findAllAsObjects();

        self::assertEmpty($result);
    }

    #[Test]
    public function findAllAsObjectsReturnsEmptyWhenNoFiles(): void
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
            ->willReturn([]);

        $result = $this->subject->findAllAsObjects();

        self::assertEmpty($result);
    }

    #[Test]
    public function findAllAsObjectsReturnsMigrationFiles(): void
    {
        $migrationData = [
            'metadata' => [
                'format_version' => '1.0',
                'migration_id' => '2024-07-14_07:30:15_a1b2c3d4',
                'description' => 'Test migration',
            ],
            'records' => ['pages' => []],
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
            ->expects(self::once())
            ->method('glob')
            ->with('/var/www/fileadmin/migrations/*.json')
            ->willReturn(['/var/www/fileadmin/migrations/2024-07-14_07:30:15_a1b2c3d4.json']);

        $this->filesystemMock
            ->expects(self::exactly(3))
            ->method('exists')
            ->willReturnCallback(function ($path) {
                return str_ends_with($path, '.json');
            });

        $this->filesystemMock
            ->expects(self::once())
            ->method('getFileContents')
            ->with('/var/www/fileadmin/migrations/2024-07-14_07:30:15_a1b2c3d4.json')
            ->willReturn(json_encode($migrationData));

        $result = $this->subject->findAllAsObjects();

        self::assertCount(1, $result);
        self::assertInstanceOf(MigrationFile::class, $result[0]);
        self::assertSame('2024-07-14_07:30:15_a1b2c3d4', $result[0]->getId());
    }

    #[Test]
    public function findByIdAsObjectReturnsNullWhenNotFound(): void
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
            ->method('exists')
            ->with('/var/www/fileadmin/migrations/2024-07-14_07:30:15_a1b2c3d4.json')
            ->willReturn(false);

        $result = $this->subject->findByIdAsObject('2024-07-14_07:30:15_a1b2c3d4');

        self::assertNull($result);
    }

    #[Test]
    public function findByIdAsObjectReturnsMigrationFile(): void
    {
        $migrationData = [
            'metadata' => [
                'format_version' => '1.0',
                'migration_id' => '2024-07-14_07:30:15_a1b2c3d4',
                'description' => 'Test migration',
            ],
            'records' => ['pages' => []],
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
            ->expects(self::exactly(4))
            ->method('exists')
            ->willReturnCallback(function ($path) {
                return str_ends_with($path, '.json');
            });

        $this->filesystemMock
            ->expects(self::once())
            ->method('getFileContents')
            ->with('/var/www/fileadmin/migrations/2024-07-14_07:30:15_a1b2c3d4.json')
            ->willReturn(json_encode($migrationData));

        $result = $this->subject->findByIdAsObject('2024-07-14_07:30:15_a1b2c3d4');

        self::assertInstanceOf(MigrationFile::class, $result);
        self::assertSame('2024-07-14_07:30:15_a1b2c3d4', $result->getId());
    }

    #[Test]
    public function findByIdReturnsMigrationArray(): void
    {
        $migrationData = [
            'metadata' => [
                'format_version' => '1.0',
                'migration_id' => '2024-07-14_07:30:15_a1b2c3d4',
                'description' => 'Test migration',
            ],
            'records' => ['pages' => []],
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
            ->expects(self::exactly(2))
            ->method('exists')
            ->willReturnCallback(function ($path) {
                return str_ends_with($path, '.json');
            });

        $this->filesystemMock
            ->expects(self::once())
            ->method('getFileContents')
            ->with('/var/www/fileadmin/migrations/2024-07-14_07:30:15_a1b2c3d4.json')
            ->willReturn(json_encode($migrationData));

        $result = $this->subject->findById('2024-07-14_07:30:15_a1b2c3d4');

        self::assertIsArray($result);
        self::assertSame('2024-07-14_07:30:15_a1b2c3d4', $result['id']);
        self::assertSame('2024-07-14_07:30:15_a1b2c3d4.json', $result['file']);
        self::assertSame('fileadmin/migrations', $result['path']);
        self::assertSame($migrationData, $result['data']);
    }

    #[Test]
    public function existsReturnsFalseWhenNotFound(): void
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
            ->method('exists')
            ->with('/var/www/fileadmin/migrations/2024-07-14_07:30:15_a1b2c3d4.json')
            ->willReturn(false);

        $result = $this->subject->exists('2024-07-14_07:30:15_a1b2c3d4');

        self::assertFalse($result);
    }

    #[Test]
    public function existsReturnsTrueWhenFound(): void
    {
        $migrationData = [
            'metadata' => [
                'format_version' => '1.0',
                'migration_id' => '2024-07-14_07:30:15_a1b2c3d4',
                'description' => 'Test migration',
            ],
            'records' => ['pages' => []],
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
            ->expects(self::exactly(2))
            ->method('exists')
            ->willReturnCallback(function ($path) {
                return str_ends_with($path, '.json');
            });

        $this->filesystemMock
            ->expects(self::once())
            ->method('getFileContents')
            ->with('/var/www/fileadmin/migrations/2024-07-14_07:30:15_a1b2c3d4.json')
            ->willReturn(json_encode($migrationData));

        $result = $this->subject->exists('2024-07-14_07:30:15_a1b2c3d4');

        self::assertTrue($result);
    }

    #[Test]
    public function getMigrationDataReturnsData(): void
    {
        $migrationData = [
            'metadata' => [
                'format_version' => '1.0',
                'migration_id' => '2024-07-14_07:30:15_a1b2c3d4',
                'description' => 'Test migration',
            ],
            'records' => ['pages' => []],
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
            ->expects(self::exactly(2))
            ->method('exists')
            ->willReturnCallback(function ($path) {
                return str_ends_with($path, '.json');
            });

        $this->filesystemMock
            ->expects(self::once())
            ->method('getFileContents')
            ->with('/var/www/fileadmin/migrations/2024-07-14_07:30:15_a1b2c3d4.json')
            ->willReturn(json_encode($migrationData));

        $result = $this->subject->getMigrationData('2024-07-14_07:30:15_a1b2c3d4');

        self::assertSame($migrationData, $result);
    }

    #[Test]
    public function getMigrationMetadataReturnsMetadata(): void
    {
        $migrationData = [
            'metadata' => [
                'format_version' => '1.0',
                'migration_id' => '2024-07-14_07:30:15_a1b2c3d4',
                'description' => 'Test migration',
            ],
            'records' => ['pages' => []],
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
            ->expects(self::exactly(2))
            ->method('exists')
            ->willReturnCallback(function ($path) {
                return str_ends_with($path, '.json');
            });

        $this->filesystemMock
            ->expects(self::once())
            ->method('getFileContents')
            ->with('/var/www/fileadmin/migrations/2024-07-14_07:30:15_a1b2c3d4.json')
            ->willReturn(json_encode($migrationData));

        $result = $this->subject->getMigrationMetadata('2024-07-14_07:30:15_a1b2c3d4');

        self::assertSame($migrationData['metadata'], $result);
    }

    #[Test]
    public function getMigrationFilePathReturnsPath(): void
    {
        $migrationData = [
            'metadata' => [
                'format_version' => '1.0',
                'migration_id' => '2024-07-14_07:30:15_a1b2c3d4',
                'description' => 'Test migration',
            ],
            'records' => ['pages' => []],
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
            ->expects(self::exactly(2))
            ->method('exists')
            ->willReturnCallback(function ($path) {
                return str_ends_with($path, '.json');
            });

        $this->filesystemMock
            ->expects(self::once())
            ->method('getFileContents')
            ->with('/var/www/fileadmin/migrations/2024-07-14_07:30:15_a1b2c3d4.json')
            ->willReturn(json_encode($migrationData));

        $result = $this->subject->getMigrationFilePath('2024-07-14_07:30:15_a1b2c3d4');

        self::assertSame('/var/www/fileadmin/migrations/2024-07-14_07:30:15_a1b2c3d4.json', $result);
    }

    #[Test]
    public function getMigrationStatusReturnsInvalidWhenNotFound(): void
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
            ->method('exists')
            ->with('/var/www/fileadmin/migrations/2024-07-14_07:30:15_a1b2c3d4.json')
            ->willReturn(false);

        $result = $this->subject->getMigrationStatus('2024-07-14_07:30:15_a1b2c3d4');

        self::assertSame(MigrationStatus::INVALID, $result);
    }

    #[Test]
    public function getMigrationStatusReturnsInvalidWhenDataInvalid(): void
    {
        $migrationData = [
            'metadata' => [
                'format_version' => '2.0', // Invalid version
                'migration_id' => '2024-07-14_07:30:15_a1b2c3d4',
                'description' => 'Test migration',
            ],
            'records' => ['pages' => []],
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
            ->expects(self::exactly(2))
            ->method('exists')
            ->willReturnCallback(function ($path) {
                return str_ends_with($path, '.json');
            });

        $this->filesystemMock
            ->expects(self::once())
            ->method('getFileContents')
            ->with('/var/www/fileadmin/migrations/2024-07-14_07:30:15_a1b2c3d4.json')
            ->willReturn(json_encode($migrationData));

        $result = $this->subject->getMigrationStatus('2024-07-14_07:30:15_a1b2c3d4');

        self::assertSame(MigrationStatus::INVALID, $result);
    }

    #[Test]
    public function getMigrationStatusReturnsAppliedWhenMarkerExists(): void
    {
        $migrationData = [
            'metadata' => [
                'format_version' => '1.0',
                'migration_id' => '2024-07-14_07:30:15_a1b2c3d4',
                'description' => 'Test migration',
            ],
            'records' => ['pages' => []],
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
            ->expects(self::exactly(3))
            ->method('exists')
            ->willReturnCallback(function ($path) {
                return str_ends_with($path, '.json') || str_ends_with($path, '.applied');
            });

        $this->filesystemMock
            ->expects(self::once())
            ->method('getFileContents')
            ->with('/var/www/fileadmin/migrations/2024-07-14_07:30:15_a1b2c3d4.json')
            ->willReturn(json_encode($migrationData));

        $result = $this->subject->getMigrationStatus('2024-07-14_07:30:15_a1b2c3d4');

        self::assertSame(MigrationStatus::APPLIED, $result);
    }

    #[Test]
    public function getMigrationStatusReturnsPendingWhenNoMarkers(): void
    {
        $migrationData = [
            'metadata' => [
                'format_version' => '1.0',
                'migration_id' => '2024-07-14_07:30:15_a1b2c3d4',
                'description' => 'Test migration',
            ],
            'records' => ['pages' => []],
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
            ->expects(self::exactly(4))
            ->method('exists')
            ->willReturnCallback(function ($path) {
                return str_ends_with($path, '.json');
            });

        $this->filesystemMock
            ->expects(self::once())
            ->method('getFileContents')
            ->with('/var/www/fileadmin/migrations/2024-07-14_07:30:15_a1b2c3d4.json')
            ->willReturn(json_encode($migrationData));

        $result = $this->subject->getMigrationStatus('2024-07-14_07:30:15_a1b2c3d4');

        self::assertSame(MigrationStatus::PENDING, $result);
    }

    #[Test]
    public function markAsAppliedReturnsTrueWhenSuccessful(): void
    {
        $migrationData = [
            'metadata' => [
                'format_version' => '1.0',
                'migration_id' => '2024-07-14_07:30:15_a1b2c3d4',
                'description' => 'Test migration',
            ],
            'records' => ['pages' => []],
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
            ->expects(self::exactly(2))
            ->method('exists')
            ->willReturnCallback(function ($path) {
                return str_ends_with($path, '.json');
            });

        $this->filesystemMock
            ->expects(self::once())
            ->method('getFileContents')
            ->with('/var/www/fileadmin/migrations/2024-07-14_07:30:15_a1b2c3d4.json')
            ->willReturn(json_encode($migrationData));

        $this->filesystemMock
            ->expects(self::once())
            ->method('putFileContents')
            ->with('/var/www/fileadmin/migrations/2024-07-14_07:30:15_a1b2c3d4.applied', self::anything())
            ->willReturn(19);

        $result = $this->subject->markAsApplied('2024-07-14_07:30:15_a1b2c3d4');

        self::assertTrue($result);
    }

    #[Test]
    public function markAsFailedReturnsTrueWhenSuccessful(): void
    {
        $migrationData = [
            'metadata' => [
                'format_version' => '1.0',
                'migration_id' => '2024-07-14_07:30:15_a1b2c3d4',
                'description' => 'Test migration',
            ],
            'records' => ['pages' => []],
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
            ->expects(self::exactly(2))
            ->method('exists')
            ->willReturnCallback(function ($path) {
                return str_ends_with($path, '.json');
            });

        $this->filesystemMock
            ->expects(self::once())
            ->method('getFileContents')
            ->with('/var/www/fileadmin/migrations/2024-07-14_07:30:15_a1b2c3d4.json')
            ->willReturn(json_encode($migrationData));

        $this->filesystemMock
            ->expects(self::once())
            ->method('putFileContents')
            ->with('/var/www/fileadmin/migrations/2024-07-14_07:30:15_a1b2c3d4.failed', self::anything())
            ->willReturn(100);

        $result = $this->subject->markAsFailed('2024-07-14_07:30:15_a1b2c3d4', 'Test reason');

        self::assertTrue($result);
    }

    #[Test]
    public function clearStatusReturnsTrueWhenSuccessful(): void
    {
        $migrationData = [
            'metadata' => [
                'format_version' => '1.0',
                'migration_id' => '2024-07-14_07:30:15_a1b2c3d4',
                'description' => 'Test migration',
            ],
            'records' => ['pages' => []],
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
            ->expects(self::exactly(4))
            ->method('exists')
            ->willReturnCallback(function ($path) {
                if (str_ends_with($path, '.json')) {
                    return true;
                }
                return str_ends_with($path, '.applied') || str_ends_with($path, '.failed');
            });

        $this->filesystemMock
            ->expects(self::once())
            ->method('getFileContents')
            ->with('/var/www/fileadmin/migrations/2024-07-14_07:30:15_a1b2c3d4.json')
            ->willReturn(json_encode($migrationData));

        $this->filesystemMock
            ->expects(self::exactly(2))
            ->method('deleteFile')
            ->willReturn(true);

        $result = $this->subject->clearStatus('2024-07-14_07:30:15_a1b2c3d4');

        self::assertTrue($result);
    }

    #[Test]
    public function findByStatusReturnsFilteredMigrations(): void
    {
        $migrationData = [
            'metadata' => [
                'format_version' => '1.0',
                'migration_id' => '2024-07-14_07:30:15_a1b2c3d4',
                'description' => 'Test migration',
            ],
            'records' => ['pages' => []],
        ];

        // findAll() calls getMigrationPaths() once
        // getMigrationStatus() calls getMigrationPaths() once for each migration
        $this->configurationMock
            ->expects(self::exactly(2))
            ->method('getMigrationPaths')
            ->willReturn(['fileadmin/migrations']);

        $this->filesystemMock
            ->expects(self::exactly(2))
            ->method('getAbsoluteFilePath')
            ->with('fileadmin/migrations')
            ->willReturn('/var/www/fileadmin/migrations');

        $this->filesystemMock
            ->expects(self::exactly(2))
            ->method('isDirectory')
            ->with('/var/www/fileadmin/migrations')
            ->willReturn(true);

        $this->filesystemMock
            ->expects(self::once())
            ->method('glob')
            ->with('/var/www/fileadmin/migrations/*.json')
            ->willReturn(['/var/www/fileadmin/migrations/2024-07-14_07:30:15_a1b2c3d4.json']);

        $this->filesystemMock
            ->expects(self::exactly(5))
            ->method('exists')
            ->willReturnCallback(function ($path) {
                if (str_ends_with($path, '.json')) {
                    return true;
                }
                return false; // .applied and .failed markers don't exist
            });

        $this->filesystemMock
            ->expects(self::exactly(2))
            ->method('getFileContents')
            ->with('/var/www/fileadmin/migrations/2024-07-14_07:30:15_a1b2c3d4.json')
            ->willReturn(json_encode($migrationData));

        $result = $this->subject->findByStatus(MigrationStatus::PENDING);

        self::assertCount(1, $result);
        self::assertSame('2024-07-14_07:30:15_a1b2c3d4', $result[0]['id']);
        self::assertSame(MigrationStatus::PENDING, $result[0]['status']);
    }

    #[Test]
    public function getSummaryReturnsStatistics(): void
    {
        $migrationData = [
            'metadata' => [
                'format_version' => '1.0',
                'migration_id' => '2024-07-14_07:30:15_a1b2c3d4',
                'description' => 'Test migration',
            ],
            'records' => ['pages' => [['uid' => 1]]],
        ];

        // getSummary() calls findAll() once and getMigrationStatus() once per migration
        $this->configurationMock
            ->expects(self::exactly(2))
            ->method('getMigrationPaths')
            ->willReturn(['fileadmin/migrations']);

        $this->filesystemMock
            ->expects(self::exactly(2))
            ->method('getAbsoluteFilePath')
            ->with('fileadmin/migrations')
            ->willReturn('/var/www/fileadmin/migrations');

        $this->filesystemMock
            ->expects(self::exactly(2))
            ->method('isDirectory')
            ->with('/var/www/fileadmin/migrations')
            ->willReturn(true);

        $this->filesystemMock
            ->expects(self::once())
            ->method('glob')
            ->with('/var/www/fileadmin/migrations/*.json')
            ->willReturn(['/var/www/fileadmin/migrations/2024-07-14_07:30:15_a1b2c3d4.json']);

        $this->filesystemMock
            ->expects(self::exactly(6))
            ->method('exists')
            ->willReturnCallback(function ($path) {
                if (str_ends_with($path, '.json')) {
                    return true;
                }
                return false; // .applied and .failed markers don't exist
            });

        $this->filesystemMock
            ->expects(self::exactly(2))
            ->method('getFileContents')
            ->with('/var/www/fileadmin/migrations/2024-07-14_07:30:15_a1b2c3d4.json')
            ->willReturn(json_encode($migrationData));

        $this->filesystemMock
            ->expects(self::once())
            ->method('getFileSize')
            ->with('/var/www/fileadmin/migrations/2024-07-14_07:30:15_a1b2c3d4.json')
            ->willReturn(1024);

        $result = $this->subject->getSummary();

        self::assertSame(1, $result['total_migrations']);
        self::assertSame(1, $result['status_counts']['pending']);
        self::assertSame(1024, $result['total_size']);
        self::assertSame(1, $result['total_records']);
    }

    #[Test]
    public function validatePathsReturnsPathInformation(): void
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
            ->willReturn(['/var/www/fileadmin/migrations/2024-07-14_07:30:15_a1b2c3d4.json']);

        $result = $this->subject->validatePaths();

        self::assertCount(1, $result);
        self::assertSame('fileadmin/migrations', $result[0]['path']);
        self::assertSame('/var/www/fileadmin/migrations', $result[0]['absolute_path']);
        self::assertTrue($result[0]['exists']);
        self::assertTrue($result[0]['writable']);
        self::assertSame(1, $result[0]['migration_count']);
    }
}
