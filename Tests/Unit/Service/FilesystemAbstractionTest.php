<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Tests\Unit\Service;

use Cpsit\T3hauler\Service\FilesystemAdapter;
use Cpsit\T3hauler\Service\FilesystemInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Test to verify filesystem abstraction works correctly
 */
final class FilesystemAbstractionTest extends TestCase
{
    private FilesystemInterface $filesystem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filesystem = new FilesystemAdapter();
    }

    #[Test]
    public function filesystemAdapterImplementsInterface(): void
    {
        self::assertInstanceOf(FilesystemInterface::class, $this->filesystem);
    }

    #[Test]
    public function existsReturnsFalseForNonExistentFile(): void
    {
        $result = $this->filesystem->exists('/non/existent/file.txt');
        self::assertFalse($result);
    }

    #[Test]
    public function isDirectoryReturnsFalseForNonExistentDirectory(): void
    {
        $result = $this->filesystem->isDirectory('/non/existent/directory');
        self::assertFalse($result);
    }

    #[Test]
    public function globReturnsEmptyArrayForNonExistentPattern(): void
    {
        $result = $this->filesystem->glob('/non/existent/*.txt');
        self::assertEmpty($result);
    }

    #[Test]
    public function getFileSizeReturnsFalseForNonExistentFile(): void
    {
        $result = $this->filesystem->getFileSize('/non/existent/file.txt');
        self::assertFalse($result);
    }

    #[Test]
    public function getFileModificationTimeReturnsFalseForNonExistentFile(): void
    {
        $result = $this->filesystem->getFileModificationTime('/non/existent/file.txt');
        self::assertFalse($result);
    }

    #[Test]
    public function getFileContentsReturnsFalseForNonExistentFile(): void
    {
        $result = $this->filesystem->getFileContents('/non/existent/file.txt');
        self::assertFalse($result);
    }

    #[Test]
    public function getAbsoluteFilePathWorksWithRelativePath(): void
    {
        $result = $this->filesystem->getAbsoluteFilePath('fileadmin/test.txt');
        self::assertStringContainsString('fileadmin/test.txt', $result);
    }
}
