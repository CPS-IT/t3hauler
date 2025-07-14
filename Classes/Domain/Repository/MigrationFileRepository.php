<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Domain\Repository;

use Cpsit\T3hauler\Configuration\T3HaulerConfiguration;
use Cpsit\T3hauler\Domain\Model\MigrationStatus;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Repository for accessing migration files from the filesystem
 *
 * Centralizes all filesystem access for migration files across all configured paths
 */
class MigrationFileRepository
{
    public function __construct(
        private readonly T3HaulerConfiguration $configuration
    ) {}

    /**
     * Find all migration files across all configured paths
     *
     * @return array<array{id: string, file: string, path: string, absolute_path: string, data: array|null}>
     */
    public function findAll(): array
    {
        $migrations = [];
        $migrationPaths = $this->configuration->getMigrationPaths();

        foreach ($migrationPaths as $migrationPath) {
            $absolutePath = GeneralUtility::getFileAbsFileName($migrationPath);

            if (!is_dir($absolutePath)) {
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
     * Find a specific migration file by ID
     *
     * @return array{id: string, file: string, path: string, absolute_path: string, data: array|null}|null
     */
    public function findById(string $migrationId): ?array
    {
        $migrationPaths = $this->configuration->getMigrationPaths();

        foreach ($migrationPaths as $migrationPath) {
            $absolutePath = GeneralUtility::getFileAbsFileName($migrationPath);

            if (!is_dir($absolutePath)) {
                continue;
            }

            $migrationFile = $absolutePath . '/' . $migrationId . '.json';
            if (file_exists($migrationFile)) {
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
        if (file_exists($appliedMarker)) {
            return MigrationStatus::APPLIED;
        }

        // Check if migration has failed
        $failedMarker = str_replace('.json', '.failed', $migration['absolute_path']);
        if (file_exists($failedMarker)) {
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
        return file_put_contents($appliedMarker, date('Y-m-d H:i:s')) !== false;
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

        return file_put_contents($failedMarker, $content) !== false;
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
            if (file_exists($markerFile)) {
                $success = $success && unlink($markerFile);
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
            if (file_exists($migration['absolute_path'])) {
                $totalSize += filesize($migration['absolute_path']);
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
            $absolutePath = GeneralUtility::getFileAbsFileName($migrationPath);
            $exists = is_dir($absolutePath);
            $writable = $exists && is_writable($absolutePath);

            $migrationCount = 0;
            if ($exists) {
                $files = glob($absolutePath . '/*.json') ?: [];
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
        $files = glob($absolutePath . '/*.json') ?: [];

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
            if (!file_exists($filePath)) {
                return null;
            }

            $content = file_get_contents($filePath);
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
}
