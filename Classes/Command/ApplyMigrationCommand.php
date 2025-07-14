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
use DWenzel\T3extensionTools\Command\ArgumentAwareInterface;
use DWenzel\T3extensionTools\Command\OptionAwareInterface;
use DWenzel\T3extensionTools\Traits\Command\ArgumentAwareTrait;
use DWenzel\T3extensionTools\Traits\Command\ConfigureTrait;
use DWenzel\T3extensionTools\Traits\Command\OptionAwareTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

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
        $io = new SymfonyStyle($input, $output);
        $migrationId = $input->getArgument(MigrationArgument::NAME);
        $validate = $input->getOption(ValidateOption::NAME);
        $dryRun = $input->getOption(DryRunOption::NAME);
        $force = $input->getOption(ForceOption::NAME);

        $io->title('T3Hauler - Apply Migration');

        try {
            // Find migration file from filesystem
            $migrationFile = $this->migrationFileRepository->findById($migrationId);
            if (!$migrationFile) {
                throw new MigrationNotFoundException("Migration file '{$migrationId}' not found in any migration path", 8178776127);
            }

            $io->section("Migration: {$migrationId}");
            $io->text("Migration file: {$migrationFile['absolute_path']}");

            // Load migration metadata if available
            $migrationData = $migrationFile['data']['metadata'] ?? null;
            if ($migrationData) {
                $io->text("Description: {$migrationData['description']}");
                $io->text("Author: {$migrationData['author']}");
                $io->text('Created: ' . date('Y-m-d H:i:s', $migrationData['created_at']));
            }

            // Check if migration was already applied in this instance
            $existingMigration = $this->migrationRepository->findByMigrationId($migrationId);
            if ($existingMigration) {
                $status = $existingMigration->getStatusEnum();
                $io->text("Local status: {$status->value}");

                if ($status->isSuccessful()) {
                    $io->warning('Migration has already been applied in this instance');
                    if (!$force) {
                        return Command::FAILURE;
                    }
                    $io->note('Force mode enabled - proceeding anyway');
                } elseif ($status->isFailed()) {
                    $io->warning('Migration failed previously in this instance');
                    if (!$force) {
                        $io->note('Use --force to retry the migration');
                        return Command::FAILURE;
                    }
                    $io->note('Force mode enabled - retrying migration');
                }
            } else {
                $io->text('Local status: not applied');
            }

            if ($dryRun) {
                $io->note('DRY RUN MODE - No changes will be applied');
            }

            $io->text("Data file: {$migrationFile['absolute_path']}");

            // Integrity validation
            if ($validate && !$force) {
                $io->section('Integrity Validation');

                // Load migration data for validation
                $migrationFileData = $migrationFile['data'];
                $sourceHash = $migrationData['source_hash'] ?? '';

                if (!$migrationFileData || !$sourceHash) {
                    $io->warning('Cannot validate integrity: missing migration data or source hash');
                    $io->note('Use --force to apply anyway, or ensure migration file contains proper metadata');
                    return Command::FAILURE;
                }

                $validation = $this->importService->validateTargetIntegrity(
                    $migrationFileData,
                    $sourceHash
                );

                if (!$validation['valid']) {
                    $io->error('Integrity validation failed: ' . $validation['message']);
                    $io->note('Use --force to apply anyway, or resolve conflicts manually');
                    return Command::FAILURE;
                }

                $io->success('Integrity validation passed');
            }

            // Apply migration
            $io->section($dryRun ? 'Migration Preview' : 'Applying Migration');

            $result = $this->importService->importFromFile($migrationFile['absolute_path'], $dryRun);

            if (!$result['success']) {
                $io->error('Migration application failed: ' . $result['message']);
                if (isset($result['errors'])) {
                    foreach ($result['errors'] as $error) {
                        $io->text('  - ' . $error);
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
                $io->success('Migration preview completed');
                $io->text("Would import {$result['would_import']} records");

                if (isset($result['tables_summary'])) {
                    $tableRows = [];
                    foreach ($result['tables_summary'] as $tableName => $summary) {
                        $tableRows[] = [$tableName, $summary['records'], implode(', ', array_slice($summary['uids'], 0, 5)) . (count($summary['uids']) > 5 ? '...' : '')];
                    }
                    $io->table(['Table', 'Records', 'UIDs (first 5)'], $tableRows);
                }

                if (!empty($result['issues'])) {
                    $io->warning('Potential issues found:');
                    foreach ($result['issues'] as $issue) {
                        $io->text('  - ' . $issue);
                    }
                }
            } else {
                $io->success('Migration applied successfully');
                $io->text("Imported {$result['imported_records']} records");

                if (isset($result['imported_tables'])) {
                    $tableRows = [];
                    foreach ($result['imported_tables'] as $tableName => $count) {
                        $tableRows[] = [$tableName, $count];
                    }
                    $io->table(['Table', 'Imported Records'], $tableRows);
                }

                // Mark migration as applied in local repository
                $this->markMigrationAsApplied($migrationId, $migrationFile, $migrationData);

                // Create post-migration snapshot
                $this->createPostMigrationSnapshot($io, $migrationId);

                if (!empty($result['errors'])) {
                    $io->warning('Some errors occurred during import:');
                    foreach ($result['errors'] as $error) {
                        $io->text('  - ' . $error);
                    }
                }
            }

            return Command::SUCCESS;

        } catch (MigrationNotFoundException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        } catch (\Exception $e) {
            $io->error('Migration application failed: ' . $e->getMessage());
            if ($output->isVerbose()) {
                $io->text('Exception: ' . get_class($e));
                $io->text('Stack trace:');
                $io->text($e->getTraceAsString());
            }
            return Command::FAILURE;
        }
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
    private function createPostMigrationSnapshot(SymfonyStyle $io, string $migrationId): void
    {
        try {
            $enabledTables = $this->configuration->getEnabledTables();

            if (empty($enabledTables)) {
                $io->note('No tables configured for snapshot creation');
                return;
            }

            $io->text('Creating post-migration snapshot...');

            $snapshotData = $this->changeDetectionService->createSnapshot(
                'post_migration_' . $migrationId,
                $migrationId
            );

            if ($snapshotData['success']) {
                $io->text('✓ Post-migration snapshot created');
            } else {
                $io->warning('Failed to create post-migration snapshot: ' . $snapshotData['message']);
            }

        } catch (\Exception $e) {
            $io->warning('Failed to create post-migration snapshot: ' . $e->getMessage());
        }
    }
}
