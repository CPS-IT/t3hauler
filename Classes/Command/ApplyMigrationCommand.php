<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Command;

use Cpsit\T3hauler\Command\Argument\MigrationArgument;
use Cpsit\T3hauler\Command\Option\DryRunOption;
use Cpsit\T3hauler\Command\Option\ForceOption;
use Cpsit\T3hauler\Command\Option\FormatOption;
use Cpsit\T3hauler\Command\Option\ValidateOption;
use Cpsit\T3hauler\Configuration\T3HaulerConfiguration;
use Cpsit\T3hauler\Domain\Model\Migration;
use Cpsit\T3hauler\Domain\Repository\MigrationFileRepository;
use Cpsit\T3hauler\Domain\Repository\MigrationRepository;
use Cpsit\T3hauler\Exception\MigrationNotFoundException;
use Cpsit\T3hauler\Service\ChangeDetectionService;
use Cpsit\T3hauler\Service\ImportService;
use Cpsit\T3hauler\Traits\Command\CommandInputOutputTrait;
use Cpsit\T3hauler\Traits\Command\CommandErrorHandlingTrait;
use Cpsit\T3hauler\Traits\Command\CommandProgressTrait;
use Cpsit\T3hauler\Traits\Command\CommandUtilityTrait;
use Cpsit\T3hauler\Traits\Command\CommandOptionsTrait;
use DWenzel\T3extensionTools\Command\ArgumentAwareInterface;
use DWenzel\T3extensionTools\Command\OptionAwareInterface;
use DWenzel\T3extensionTools\Traits\Command\ArgumentAwareTrait;
use DWenzel\T3extensionTools\Traits\Command\ConfigureTrait;
use DWenzel\T3extensionTools\Traits\Command\OptionAwareTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to apply migration from filesystem with validation
 *
 * Searches for migration files in configured paths and applies them to the target environment.
 * The MigrationRepository tracks applied migrations for the current instance only.
 */
#[AsCommand(
    name: 't3hauler:migration:apply',
    description: 'Apply migration from filesystem with validation',
    aliases: ['haul:apply'],
    help: 'This command finds migration files in configured paths and applies them to the target environment with integrity validation. The local repository tracks applied migrations for this instance only.'
)]
class ApplyMigrationCommand extends Command implements ArgumentAwareInterface, OptionAwareInterface
{
    use ArgumentAwareTrait;
    use OptionAwareTrait;
    use ConfigureTrait;
    use CommandInputOutputTrait;
    use CommandErrorHandlingTrait;
    use CommandProgressTrait;
    use CommandUtilityTrait;
    use CommandOptionsTrait;

    public const string MESSAGE_DESCRIPTION_COMMAND = 'Apply migration from filesystem with validation';
    public const string MESSAGE_HELP_COMMAND = 'This command finds migration files in configured paths and applies them to the target environment with integrity validation. The local repository tracks applied migrations for this instance only.';

    protected const array ARGUMENTS = [
        MigrationArgument::class,
    ];

    protected const array OPTIONS = [
        ValidateOption::class,
        DryRunOption::class,
        ForceOption::class,
        FormatOption::class,
    ];

    protected static array $argumentsToConfigure = self::ARGUMENTS;
    protected static array $optionsToConfigure = self::OPTIONS;

    public function __construct(
        private readonly T3HaulerConfiguration $configuration,
        private readonly MigrationRepository $migrationRepository,
        private readonly MigrationFileRepository $migrationFileRepository,
        private readonly ImportService $importService,
        private readonly ChangeDetectionService $changeDetectionService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initializeIO($input, $output);

        return $this->safeExecute(function () use ($input): int {
            $migrationId = $input->getArgument(MigrationArgument::NAME);
            $validate = $this->isValidateEnabled($input);
            $dryRun = $this->isDryRun($input);
            $force = $this->isForceMode($input);

            $this->displayCommandTitle('t3hauler - Apply Migration');

            // Find migration file from filesystem
            $migrationFile = $this->migrationFileRepository->findById($migrationId);
            if (!$migrationFile) {
                throw new MigrationNotFoundException("Migration file '{$migrationId}' not found in any migration path", 8178776127);
            }

            $this->displaySection("Migration: {$migrationId}");
            $this->reportInfo("Migration file: {$migrationFile['absolute_path']}");

            // Load migration metadata if available
            $migrationData = $migrationFile['data']['metadata'] ?? null;
            if ($migrationData) {
                $this->reportInfo("Description: {$migrationData['description']}");
                $this->reportInfo("Author: {$migrationData['author']}");
                $this->reportInfo('Created: ' . date('Y-m-d H:i:s', $migrationData['created_at']));
            }

            // Check if migration was already applied in this instance
            $existingMigration = $this->migrationRepository->findByMigrationId($migrationId);
            if ($existingMigration) {
                $status = $existingMigration->getStatusEnum();
                $this->reportInfo("Local status: {$status->value}");

                if ($status->isSuccessful()) {
                    $this->reportWarning('Migration has already been applied in this instance');
                    if (!$force) {
                        return Command::FAILURE;
                    }
                    $this->reportNote('Force mode enabled - proceeding anyway');
                } elseif ($status->isFailed()) {
                    $this->reportWarning('Migration failed previously in this instance');
                    if (!$force) {
                        $this->reportNote('Use --force to retry the migration');
                        return Command::FAILURE;
                    }
                    $this->reportNote('Force mode enabled - retrying migration');
                }
            } else {
                $this->reportInfo('Local status: not applied');
            }

            if ($dryRun) {
                $this->reportDryRunMode();
            }

            $this->reportInfo("Data file: {$migrationFile['absolute_path']}");

            // Integrity validation
            if ($validate && !$force) {
                $this->displaySection('Integrity Validation');

                // Load migration data for validation
                $migrationFileData = $migrationFile['data'];
                $sourceHash = $migrationData['source_hash'] ?? '';

                if (!$migrationFileData || !$sourceHash) {
                    $this->reportWarning('Cannot validate integrity: missing migration data or source hash');
                    $this->reportNote('Use --force to apply anyway, or ensure migration file contains proper metadata');
                    return Command::FAILURE;
                }

                $validation = $this->importService->validateTargetIntegrity(
                    $migrationFileData,
                    $sourceHash
                );

                if (!$validation['valid']) {
                    $this->reportError('Integrity validation failed: ' . $validation['message']);
                    $this->reportNote('Use --force to apply anyway, or resolve conflicts manually');
                    return Command::FAILURE;
                }

                $this->reportSuccess('Integrity validation passed');
            }

            // Apply migration
            $this->displaySection($dryRun ? 'Migration Preview' : 'Applying Migration');

            $result = $this->importService->importFromFile($migrationFile['absolute_path'], $dryRun);

            if (!$result['success']) {
                $this->reportError('Migration application failed: ' . $result['message']);
                if (isset($result['errors'])) {
                    foreach ($result['errors'] as $error) {
                        $this->reportInfo('  - ' . $error);
                    }
                }

                // Mark migration as failed if not in dry run
                if (!$dryRun) {
                    $this->markMigrationAsFailed($migrationId, $migrationFile, $result['message']);
                }

                return Command::FAILURE;
            }

            // Display results
            if ($dryRun) {
                $this->reportSuccess('Migration preview completed');
                $this->reportInfo("Would import {$result['would_import']} records");

                if (isset($result['tables_summary'])) {
                    $tableRows = [];
                    foreach ($result['tables_summary'] as $tableName => $summary) {
                        $tableRows[] = [$tableName, $summary['records'], implode(', ', array_slice($summary['uids'], 0, 5)) . (count($summary['uids']) > 5 ? '...' : '')];
                    }
                    $this->displaySummaryTable(['Table', 'Records', 'UIDs (first 5)'], $tableRows, 'Tables Summary');
                }

                if (!empty($result['issues'])) {
                    $this->reportWarning('Potential issues found:');
                    foreach ($result['issues'] as $issue) {
                        $this->reportInfo('  - ' . $issue);
                    }
                }
            } else {
                $this->reportSuccess('Migration applied successfully');
                $this->reportInfo("Imported {$result['imported_records']} records");

                if (isset($result['imported_tables'])) {
                    $tableRows = [];
                    foreach ($result['imported_tables'] as $tableName => $count) {
                        $tableRows[] = [$tableName, $count];
                    }
                    $this->displaySummaryTable(['Table', 'Imported Records'], $tableRows, 'Imported Tables');
                }

                // Mark migration as applied in local repository
                $this->markMigrationAsApplied($migrationId, $migrationFile, $migrationData);

                // Create post-migration snapshot
                $this->createPostMigrationSnapshot($migrationId);

                if (!empty($result['errors'])) {
                    $this->reportWarning('Some errors occurred during import:');
                    foreach ($result['errors'] as $error) {
                        $this->reportInfo('  - ' . $error);
                    }
                }
            }

            return Command::SUCCESS;

        }, 'Applying migration');
    }

    /**
     * Mark migration as applied in local repository
     */
    private function markMigrationAsApplied(string $migrationId, array $migrationFile, ?array $migrationData): void
    {
        $existingMigration = $this->migrationRepository->findByMigrationId($migrationId);

        if ($existingMigration) {
            $existingMigration->markAsApplied();
            $this->migrationRepository->save($existingMigration);
        } else {
            // Create new migration record for this instance
            $migration = new Migration(
                $migrationId,
                $migrationData['description'] ?? 'Applied migration',
                $migrationData['name'] ?? $migrationId,
                $migrationData['author'] ?? 'System',
                $migrationData['source_hash'] ?? '',
                basename($migrationFile['absolute_path'])
            );

            $migration->markAsApplied();
            $this->migrationRepository->save($migration);
        }
    }

    /**
     * Mark migration as failed in local repository
     */
    private function markMigrationAsFailed(string $migrationId, array $migrationFile, string $errorMessage): void
    {
        $existingMigration = $this->migrationRepository->findByMigrationId($migrationId);

        if ($existingMigration) {
            $existingMigration->markAsFailed();
            $this->migrationRepository->save($existingMigration);
        } else {
            // Create new migration record for this instance
            $migration = new Migration(
                $migrationId,
                'Failed migration: ' . $errorMessage,
                $migrationId,
                'System',
                '',
                basename($migrationFile['absolute_path'])
            );

            $migration->markAsFailed();
            $this->migrationRepository->save($migration);
        }
    }

    /**
     * Create post-migration snapshot
     */
    private function createPostMigrationSnapshot(string $migrationId): void
    {
        try {
            $enabledTables = $this->configuration->getEnabledTables();

            if (empty($enabledTables)) {
                $this->reportNote('No tables configured for snapshot creation');
                return;
            }

            $this->reportInfo('Creating post-migration snapshot...');

            $snapshotData = $this->changeDetectionService->createSnapshot(
                'post_migration_' . $migrationId,
                $migrationId
            );

            if ($snapshotData['success']) {
                $this->reportInfo('✓ Post-migration snapshot created');
            } else {
                $this->reportWarning('Failed to create post-migration snapshot: ' . $snapshotData['message']);
            }

        } catch (\Exception $e) {
            $this->reportWarning('Failed to create post-migration snapshot: ' . $e->getMessage());
        }
    }
}
