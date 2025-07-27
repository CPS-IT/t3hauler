<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Service;

/**
 * Interface for filesystem operations
 *
 * Provides an abstraction layer for filesystem operations to improve testability
 */
interface FilesystemInterface
{
    /**
     * Check if a file or directory exists
     */
    public function exists(string $path): bool;

    /**
     * Check if a path is a directory
     */
    public function isDirectory(string $path): bool;

    /**
     * Check if a path is writable
     */
    public function isWritable(string $path): bool;

    /**
     * Get file contents
     */
    public function getFileContents(string $path): string|false;

    /**
     * Write content to file
     */
    public function putFileContents(string $path, string $content): int|false;

    /**
     * Delete a file
     */
    public function deleteFile(string $path): bool;

    /**
     * Get file size in bytes
     */
    public function getFileSize(string $path): int|false;

    /**
     * Get file modification time
     */
    public function getFileModificationTime(string $path): int|false;

    /**
     * Find files matching a pattern
     *
     * @return array<string> Array of file paths
     */
    public function glob(string $pattern): array;

    /**
     * Get absolute file path using TYPO3 conventions
     */
    public function getAbsoluteFilePath(string $relativePath): string;

    /**
     * Create directory with permissions
     */
    public function createDirectory(string $path, int $permissions = 0755): bool;
}
