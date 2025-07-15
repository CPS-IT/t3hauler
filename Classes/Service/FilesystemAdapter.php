<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Service;

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Concrete implementation of filesystem operations
 * 
 * Wraps PHP filesystem functions and TYPO3 utilities for better testability
 */
class FilesystemAdapter implements FilesystemInterface
{
    public function exists(string $path): bool
    {
        return file_exists($path);
    }

    public function isDirectory(string $path): bool
    {
        return is_dir($path);
    }

    public function isWritable(string $path): bool
    {
        return is_writable($path);
    }

    public function getFileContents(string $path): string|false
    {
        return file_get_contents($path);
    }

    public function putFileContents(string $path, string $content): int|false
    {
        return file_put_contents($path, $content);
    }

    public function deleteFile(string $path): bool
    {
        return unlink($path);
    }

    public function getFileSize(string $path): int|false
    {
        return filesize($path);
    }

    public function getFileModificationTime(string $path): int|false
    {
        return filemtime($path);
    }

    public function glob(string $pattern): array
    {
        return glob($pattern) ?: [];
    }

    public function getAbsoluteFilePath(string $relativePath): string
    {
        return GeneralUtility::getFileAbsFileName($relativePath);
    }
}