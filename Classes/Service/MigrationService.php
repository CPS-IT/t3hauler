<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Service;

use Cpsit\T3hauler\Configuration\T3HaulerConfiguration;
use Cpsit\T3hauler\Domain\Model\Migration;
use Cpsit\T3hauler\Domain\Repository\MigrationRepository;
use Cpsit\T3hauler\Utility\HashUtility;

/**
 * Service for creating and managing t3hauler migrations
 *
 * Orchestrates the migration generation process with T3D export and Doctrine migrations
 */
class MigrationService
{
    public function __construct(
        private readonly ChangeDetectionService $changeDetectionService,
        private readonly ExportService $exportService,
        private readonly MigrationRepository $migrationRepository,
        private readonly T3HaulerConfiguration $configuration,
        private readonly HashUtility $hashUtility,
        private readonly FilesystemInterface $filesystem
    ) {}

    /**
     * Create a new migration from detected changes
     */
    public function createMigration(
        string $description,
        string $author = 'Developer',
        ?string $site = null,
        bool $dryRun = false
    ): array {
        // Check for changes
        $changesSummary = $this->changeDetectionService->getChangesSummary();

        if (!$changesSummary->hasChanges) {
            return [
                'success' => false,
                'message' => 'No changes detected since last snapshot. Nothing to migrate.',
                'migration' => null,
            ];
        }

        $migrationId = $this->generateMigrationId();
        $migrationPaths = $this->configuration->getMigrationPaths();

        if (empty($migrationPaths)) {
            throw new \RuntimeException('No migration paths configured', 1909123457);
        }

        $migrationPath = $migrationPaths[0];
        $migrationPath = $this->filesystem->getAbsoluteFilePath($migrationPath);

        //@todo Check and create path with TYPO3 core utilities
        if (!$dryRun && !$this->filesystem->isDirectory($migrationPath)) {
            if (!$this->filesystem->createDirectory($migrationPath)) {
                throw new \RuntimeException('Failed to create migration directory: ' . $migrationPath, 1909123458);
            }
        }

        // Generate migration file paths
        $exportFileName = $migrationId . '.json';
        $exportFilePath = $migrationPath . '/' . $exportFileName;

        if ($dryRun) {
            return [
                'success' => true,
                'message' => 'DRY RUN: Migration would be created',
                'migration' => [
                    'migration_id' => $migrationId,
                    'description' => $description,
                    'author' => $author,
                    'site' => $site,
                    'changes' => $changesSummary,
                    'export_file' => $exportFilePath,
                    'format' => 'json',
                ],
            ];
        }

        try {
            // Calculate source hash from current state
            $sourceHash = $this->calculateCurrentStateHash();

            // Export changed data to JSON format
            $exportResult = $this->exportService->exportChangedData(
                $changesSummary->changedTables,
                $exportFilePath
            );

            if (!$exportResult->isSuccess()) {
                throw new \RuntimeException('Failed to export data: ' . $exportResult->message, 1909123459);
            }

            // Create migration record
            $migration = new Migration(
                $migrationId,
                $description,
                $description,
                $author,
                $sourceHash,
                $exportFileName
            );

            $migration->addMetadata('site', $site);
            $migration->addMetadata('export_file', $exportFileName);
            $migration->addMetadata('export_format', 'json');
            $migration->addMetadata('changed_tables', $changesSummary->changedTables);
            $migration->addMetadata('export_records', $exportResult->recordCount);
            $migration->addMetadata('file_size', $exportResult->fileSize ?? 0);

            // Save migration to database
            $savedMigration = $this->migrationRepository->save($migration);

            // Create post-migration snapshot
            $snapshotIdentifier = 'migration_' . $migrationId;
            $snapshots = $this->changeDetectionService->createSnapshot($snapshotIdentifier, $migrationId);

            return [
                'success' => true,
                'message' => 'Migration created successfully',
                'migration' => $savedMigration,
                'files' => [
                    'export_file' => $exportFilePath,
                ],
                'snapshots' => $snapshots,
            ];

        } catch (\Exception $e) {
            // Cleanup on failure
            $this->cleanupFailedMigration($exportFilePath);

            throw new \RuntimeException(
                'Failed to create migration: ' . $e->getMessage(),
                1909123461,
                $e
            );
        }
    }

    /**
     * Get migration by ID
     */
    public function getMigration(string $migrationId): ?Migration
    {
        return $this->migrationRepository->findByMigrationId($migrationId);
    }

    /**
     * List all migrations
     */
    public function listMigrations(): array
    {
        return $this->migrationRepository->findAll();
    }

    /**
     * Get pending migrations
     */
    public function getPendingMigrations(): array
    {
        return $this->migrationRepository->findPending();
    }

    /**
     * Get applied migrations
     */
    public function getAppliedMigrations(): array
    {
        return $this->migrationRepository->findApplied();
    }

    /**
     * Get migration statistics
     */
    public function getStatistics(): array
    {
        return $this->migrationRepository->getSummary();
    }

    /**
     * Validate migration exists and is accessible
     */
    public function validateMigration(string $migrationId): array
    {
        $migration = $this->getMigration($migrationId);

        if (!$migration) {
            return [
                'valid' => false,
                'message' => 'Migration not found: ' . $migrationId,
            ];
        }

        $migrationPaths = $this->configuration->getMigrationPaths();
        $migrationPath = $migrationPaths[0] ?? '';

        $exportFile = $migrationPath . '/' . $migration->getDataFile();
        $metadataFile = $migrationPath . '/' . $migration->getMigrationId() . '_metadata.json';

        $issues = [];

        if (!$this->filesystem->exists($exportFile)) {
            $issues[] = 'Export file not found: ' . $exportFile;
        }

        if (!$this->filesystem->exists($metadataFile)) {
            $issues[] = 'Metadata file not found: ' . $metadataFile;
        }

        return [
            'valid' => empty($issues),
            'message' => empty($issues) ? 'Migration is valid' : implode(', ', $issues),
            'migration' => $migration,
            'files' => [
                'export_file' => $exportFile,
            ],
            'issues' => $issues,
        ];
    }

    /**
     * Generate unique migration ID
     */
    private function generateMigrationId(): string
    {
        $timestamp = date('Y-m-d_H:i:s');
        $random = substr(md5(uniqid((string)mt_rand(), true)), 0, 8);
        return $timestamp . '_' . $random;
    }

    /**
     * Calculate hash of current database state
     */
    private function calculateCurrentStateHash(): string
    {
        $enabledTables = $this->configuration->getEnabledTables();
        $excludedFields = $this->configuration->getExcludedFields();

        $tableConfigs = [];
        foreach ($enabledTables as $tableName) {
            $tableConfigs[$tableName] = [
                'excludeFields' => $excludedFields,
            ];
        }

        return $this->hashUtility->calculateMultiTableHash(
            $tableConfigs,
            $this->configuration->getHashAlgorithm()
        );
    }

    /**
     * Clean up files from failed migration creation
     */
    private function cleanupFailedMigration(string $exportFilePath): void
    {
        if ($this->filesystem->exists($exportFilePath)) {
            $this->filesystem->deleteFile($exportFilePath);
        }
    }
}
