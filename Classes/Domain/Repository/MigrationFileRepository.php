<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Domain\Repository;

use Cpsit\T3hauler\Configuration\T3HaulerConfiguration;
use Cpsit\T3hauler\Domain\Enumeration\MigrationStatus;
use Cpsit\T3hauler\Domain\Model\MigrationFile;
use Cpsit\T3hauler\Service\FilesystemInterface;

/**
 * Repository for accessing migration files from the filesystem
 *
 * Centralizes all filesystem access for migration files across all configured paths
 */
class MigrationFileRepository
{
    public function __construct(
        private readonly T3HaulerConfiguration $configuration,
        private readonly FilesystemInterface $filesystem
    ) {}

    /**
     * Find all migration files across all configured paths
     *
     * @return array<MigrationFile>
     */
    public function findAllAsObjects(): array
    {
        $migrations = [];
        $migrationPaths = $this->configuration->getMigrationPaths();

        foreach ($migrationPaths as $migrationPath) {
            $absolutePath = $this->filesystem->getAbsoluteFilePath($migrationPath);

            if (!$this->filesystem->isDirectory($absolutePath)) {
                continue;
            }

            $pathMigrations = $this->scanMigrationPathAsObjects($absolutePath, $migrationPath);
            $migrations = array_merge($migrations, $pathMigrations);
        }

        // Sort by migration ID (timestamp)
        usort($migrations, fn(MigrationFile $a, MigrationFile $b) => strcmp($a->getId(), $b->getId()));

        return $migrations;
    }

    /**
     * Find all migration files across all configured paths (legacy array format)
     *
     * @return array<array{id: string, file: string, path: string, absolute_path: string, data: array|null}>
     */
    public function findAll(): array
    {
        $migrations = [];
        $migrationPaths = $this->configuration->getMigrationPaths();

        foreach ($migrationPaths as $migrationPath) {
            $absolutePath = $this->filesystem->getAbsoluteFilePath($migrationPath);

            if (!$this->filesystem->isDirectory($absolutePath)) {
                continue;
            }

            $pathMigrations = $this->scanMigrationPath($absolutePath, $migrationPath);
            $migrations = array_merge($migrations, $pathMigrations);
        }

        // Sort by migration ID (timestamp)
        usort($migrations, fn($a, $b) => strcmp($a['id'], $b['id']));

        return $migrations;
    }

    /**
     * Find a specific migration file by ID as object
     */
    public function findByIdAsObject(string $migrationId): ?MigrationFile
    {
        $migrationPaths = $this->configuration->getMigrationPaths();

        foreach ($migrationPaths as $migrationPath) {
            $absolutePath = $this->filesystem->getAbsoluteFilePath($migrationPath);

            if (!$this->filesystem->isDirectory($absolutePath)) {
                continue;
            }

            $migrationFile = $absolutePath . '/' . $migrationId . '.json';
            if ($this->filesystem->exists($migrationFile)) {
                $data = $this->loadMigrationData($migrationFile);
                if (!$data) {
                    continue;
                }

                $status = $this->determineStatusFromFilesystem($migrationFile, $data);

                return new MigrationFile(
                    $migrationId,
                    $migrationId . '.json',
                    $migrationPath,
                    $migrationFile,
                    $data,
                    $status
                );
            }
        }

        return null;
    }

    /**
     * Find a specific migration file by ID (legacy array format)
     *
     * @return array{id: string, file: string, path: string, absolute_path: string, data: array|null}|null
     */
    public function findById(string $migrationId): ?array
    {
        $migrationPaths = $this->configuration->getMigrationPaths();

        foreach ($migrationPaths as $migrationPath) {
            $absolutePath = $this->filesystem->getAbsoluteFilePath($migrationPath);

            if (!$this->filesystem->isDirectory($absolutePath)) {
                continue;
            }

            $migrationFile = $absolutePath . '/' . $migrationId . '.json';
            if ($this->filesystem->exists($migrationFile)) {
                return [
                    'id' => $migrationId,
                    'file' => $migrationId . '.json',
                    'path' => $migrationPath,
                    'absolute_path' => $migrationFile,
                    'data' => $this->loadMigrationData($migrationFile),
                ];
            }
        }

        return null;
    }

    /**
     * Check if a migration file exists
     */
    public function exists(string $migrationId): bool
    {
        return $this->findById($migrationId) !== null;
    }

    /**
     * Get migration file data
     */
    public function getMigrationData(string $migrationId): ?array
    {
        $migration = $this->findById($migrationId);
        return $migration['data'] ?? null;
    }

    /**
     * Get migration file metadata
     */
    public function getMigrationMetadata(string $migrationId): ?array
    {
        $data = $this->getMigrationData($migrationId);
        return $data['metadata'] ?? null;
    }

    /**
     * Get the absolute file path for a migration
     */
    public function getMigrationFilePath(string $migrationId): ?string
    {
        $migration = $this->findById($migrationId);
        return $migration['absolute_path'] ?? null;
    }

    /**
     * Determine the status of a migration file
     */
    public function getMigrationStatus(string $migrationId): MigrationStatus
    {
        $migration = $this->findById($migrationId);

        if (!$migration) {
            return MigrationStatus::INVALID;
        }

        $data = $migration['data'];
        if (!$data || !$this->isValidMigrationData($data)) {
            return MigrationStatus::INVALID;
        }

        // Check if migration has been applied (simplified check for now)
        $appliedMarker = str_replace('.json', '.applied', $migration['absolute_path']);
        if ($this->filesystem->exists($appliedMarker)) {
            return MigrationStatus::APPLIED;
        }

        // Check if migration has failed
        $failedMarker = str_replace('.json', '.failed', $migration['absolute_path']);
        if ($this->filesystem->exists($failedMarker)) {
            return MigrationStatus::FAILED;
        }

        return MigrationStatus::PENDING;
    }

    /**
     * Mark a migration as applied by creating a marker file
     */
    public function markAsApplied(string $migrationId): bool
    {
        $migration = $this->findById($migrationId);
        if (!$migration) {
            return false;
        }

        $appliedMarker = str_replace('.json', '.applied', $migration['absolute_path']);
        return $this->filesystem->putFileContents($appliedMarker, date('Y-m-d H:i:s')) !== false;
    }

    /**
     * Mark a migration as failed by creating a marker file
     */
    public function markAsFailed(string $migrationId, string $reason = ''): bool
    {
        $migration = $this->findById($migrationId);
        if (!$migration) {
            return false;
        }

        $failedMarker = str_replace('.json', '.failed', $migration['absolute_path']);
        $content = json_encode([
            'failed_at' => date('Y-m-d H:i:s'),
            'reason' => $reason,
        ]);

        return $this->filesystem->putFileContents($failedMarker, $content) !== false;
    }

    /**
     * Remove status markers for a migration
     */
    public function clearStatus(string $migrationId): bool
    {
        $migration = $this->findById($migrationId);
        if (!$migration) {
            return false;
        }

        $basePath = str_replace('.json', '', $migration['absolute_path']);
        $markers = ['.applied', '.failed'];
        $success = true;

        foreach ($markers as $marker) {
            $markerFile = $basePath . $marker;
            if ($this->filesystem->exists($markerFile)) {
                $success = $success && $this->filesystem->deleteFile($markerFile);
            }
        }

        return $success;
    }

    /**
     * Get migrations by status
     *
     * @return array<array{id: string, file: string, path: string, absolute_path: string, data: array|null, status: MigrationStatus}>
     */
    public function findByStatus(MigrationStatus $status): array
    {
        $allMigrations = $this->findAll();
        $filteredMigrations = [];

        foreach ($allMigrations as $migration) {
            $migrationStatus = $this->getMigrationStatus($migration['id']);
            if ($migrationStatus === $status) {
                $migration['status'] = $migrationStatus;
                $filteredMigrations[] = $migration;
            }
        }

        return $filteredMigrations;
    }

    /**
     * Get summary statistics for all migrations
     */
    public function getSummary(): array
    {
        $allMigrations = $this->findAll();
        $statusCounts = [];
        $totalSize = 0;
        $totalRecords = 0;

        foreach (MigrationStatus::cases() as $status) {
            $statusCounts[$status->value] = 0;
        }

        foreach ($allMigrations as $migration) {
            $status = $this->getMigrationStatus($migration['id']);
            $statusCounts[$status->value]++;

            // Calculate file size and record count
            if ($this->filesystem->exists($migration['absolute_path'])) {
                $totalSize += $this->filesystem->getFileSize($migration['absolute_path']);
            }

            if ($migration['data'] && isset($migration['data']['records'])) {
                foreach ($migration['data']['records'] as $table => $records) {
                    $totalRecords += count($records);
                }
            }
        }

        return [
            'total_migrations' => count($allMigrations),
            'status_counts' => $statusCounts,
            'total_size' => $totalSize,
            'total_records' => $totalRecords,
        ];
    }

    /**
     * Validate migration paths and return status
     *
     * @return array<array{path: string, absolute_path: string, exists: bool, writable: bool, migration_count: int}>
     */
    public function validatePaths(): array
    {
        $migrationPaths = $this->configuration->getMigrationPaths();
        $pathInfo = [];

        foreach ($migrationPaths as $migrationPath) {
            $absolutePath = $this->filesystem->getAbsoluteFilePath($migrationPath);
            $exists = $this->filesystem->isDirectory($absolutePath);
            $writable = $exists && $this->filesystem->isWritable($absolutePath);

            $migrationCount = 0;
            if ($exists) {
                $files = $this->filesystem->glob($absolutePath . '/*.json');
                $migrationCount = count($files);
            }

            $pathInfo[] = [
                'path' => $migrationPath,
                'absolute_path' => $absolutePath,
                'exists' => $exists,
                'writable' => $writable,
                'migration_count' => $migrationCount,
            ];
        }

        return $pathInfo;
    }

    /**
     * Scan a single migration path for migration files
     *
     * @return array<array{id: string, file: string, path: string, absolute_path: string, data: array|null}>
     */
    private function scanMigrationPath(string $absolutePath, string $relativePath): array
    {
        $migrations = [];
        $files = $this->filesystem->glob($absolutePath . '/*.json');

        foreach ($files as $file) {
            $filename = basename($file);

            // Parse migration ID from filename (format: YYYY-MM-DD_HH:ii:ss_hash.json)
            if (!preg_match('/(\d{4}-\d{2}-\d{2}_\d{2}:\d{2}:\d{2}_[a-f0-9]{8})\.json/', $filename, $matches)) {
                continue;
            }

            $migrationId = $matches[1];
            $data = $this->loadMigrationData($file);

            $migrations[] = [
                'id' => $migrationId,
                'file' => $filename,
                'path' => $relativePath,
                'absolute_path' => $file,
                'data' => $data,
            ];
        }

        return $migrations;
    }

    /**
     * Load migration data from file
     */
    private function loadMigrationData(string $filePath): ?array
    {
        try {
            if (!$this->filesystem->exists($filePath)) {
                return null;
            }

            $content = $this->filesystem->getFileContents($filePath);
            if ($content === false) {
                return null;
            }

            $data = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return null;
            }

            return $data;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Validate migration data structure
     */
    private function isValidMigrationData(array $data): bool
    {
        // Basic validation - must have metadata and records sections
        if (!isset($data['metadata'], $data['records'])) {
            return false;
        }

        // Check for required metadata fields
        $metadata = $data['metadata'];
        $requiredFields = ['format_version', 'migration_id'];

        foreach ($requiredFields as $field) {
            if (!isset($metadata[$field])) {
                return false;
            }
        }

        // Validate format version
        if ($metadata['format_version'] !== '1.0') {
            return false;
        }

        return true;
    }

    /**
     * Scan a single migration path for migration files as objects
     *
     * @return array<MigrationFile>
     */
    private function scanMigrationPathAsObjects(string $absolutePath, string $relativePath): array
    {
        $migrations = [];
        $files = $this->filesystem->glob($absolutePath . '/*.json');

        foreach ($files as $file) {
            $filename = basename($file);

            // Parse migration ID from filename (format: YYYY-MM-DD_HH:ii:ss_hash.json)
            if (!preg_match('/(\d{4}-\d{2}-\d{2}_\d{2}:\d{2}:\d{2}_[a-f0-9]{8})\.json/', $filename, $matches)) {
                continue;
            }

            $migrationId = $matches[1];
            $data = $this->loadMigrationData($file);

            if (!$data) {
                continue;
            }

            $status = $this->determineStatusFromFilesystem($file, $data);

            $migrations[] = new MigrationFile(
                $migrationId,
                $filename,
                $relativePath,
                $file,
                $data,
                $status
            );
        }

        return $migrations;
    }

    /**
     * Determine migration status from filesystem markers and data
     */
    private function determineStatusFromFilesystem(string $filePath, array $data): MigrationStatus
    {
        if (!$this->isValidMigrationData($data)) {
            return MigrationStatus::INVALID;
        }

        // Check if migration has been applied
        $appliedMarker = str_replace('.json', '.applied', $filePath);
        if ($this->filesystem->exists($appliedMarker)) {
            return MigrationStatus::APPLIED;
        }

        // Check if migration has failed
        $failedMarker = str_replace('.json', '.failed', $filePath);
        if ($this->filesystem->exists($failedMarker)) {
            return MigrationStatus::FAILED;
        }

        return MigrationStatus::PENDING;
    }
}
