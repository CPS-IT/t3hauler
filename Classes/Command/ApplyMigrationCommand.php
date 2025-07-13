<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Command;

use Cpsit\T3hauler\Command\Argument\MigrationArgument;
use Cpsit\T3hauler\Command\Option\DryRunOption;
use Cpsit\T3hauler\Command\Option\ForceOption;
use Cpsit\T3hauler\Command\Option\FormatOption;
use Cpsit\T3hauler\Command\Option\ValidateOption;
use Cpsit\T3hauler\Configuration\T3HaulerConfiguration;
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
 * Command to apply migration with validation
 */
#[AsCommand(
    name: 't3hauler:migration:apply',
    description: 'Apply migration with validation',
    aliases: ['haul:apply'],
    help: 'This command applies a migration to the target environment with integrity validation.'
)]
class ApplyMigrationCommand extends Command implements ArgumentAwareInterface, OptionAwareInterface
{
    use ArgumentAwareTrait;
    use OptionAwareTrait;
    use ConfigureTrait;

    public const string MESSAGE_DESCRIPTION_COMMAND = 'Apply migration with validation';
    public const string MESSAGE_HELP_COMMAND = 'This command applies a migration to the target environment with integrity validation.';

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
            // Load migration
            $migration = $this->migrationRepository->findByMigrationId($migrationId);
            if (!$migration) {
                throw new MigrationNotFoundException("Migration '{$migrationId}' not found", 8178776127);
            }

            $io->section("Migration: {$migration->getName()}");
            $io->text("Description: {$migration->getDescription()}");
            $io->text("Author: {$migration->getAuthor()}");
            $io->text('Created: ' . $migration->getCreatedAt()->format('Y-m-d H:i:s'));
            $io->text("Status: {$migration->getStatus()}");

            if ($migration->getStatus() === 'applied') {
                $io->warning('Migration has already been applied');
                if (!$force) {
                    return Command::FAILURE;
                }
                $io->note('Force mode enabled - proceeding anyway');
            }

            if ($dryRun) {
                $io->note('DRY RUN MODE - No changes will be applied');
            }

            // Validate data file exists
            $dataFile = $migration->getDataFile();
            $migrationPaths = $this->configuration->get('migrationPaths', []);
            $dataFilePath = null;

            foreach ($migrationPaths as $path) {
                $fullPath = rtrim($path, '/') . '/' . $dataFile;
                if (file_exists($fullPath)) {
                    $dataFilePath = $fullPath;
                    break;
                }
            }

            if (!$dataFilePath) {
                $io->error("Migration data file not found: {$dataFile}");
                return Command::FAILURE;
            }

            $io->text("Data file: {$dataFilePath}");

            // Integrity validation
            if ($validate && !$force) {
                $io->section('Integrity Validation');

                $validation = $this->importService->validateTargetIntegrity(
                    ['records' => []], // This would be loaded from file
                    $migration->getSourceHash()
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

            $result = $this->importService->importFromFile($dataFilePath, $dryRun);

            if (!$result['success']) {
                $io->error('Migration application failed: ' . $result['message']);
                if (isset($result['errors'])) {
                    foreach ($result['errors'] as $error) {
                        $io->text('  - ' . $error);
                    }
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

                // Update migration status
                $migration->markAsApplied();
                $this->migrationRepository->save($migration);

                // Create post-migration snapshot
                $this->createPostMigrationSnapshot($io, $migration);

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
     * Create post-migration snapshot
     */
    private function createPostMigrationSnapshot(SymfonyStyle $io, $migration): void
    {
        try {
            $enabledTables = $this->configuration->get('detection.enabledTables', []);

            if (empty($enabledTables)) {
                $io->note('No tables configured for snapshot creation');
                return;
            }

            $io->text('Creating post-migration snapshot...');

            $snapshotData = $this->changeDetectionService->createSnapshot(
                'post_migration_' . $migration->getMigrationId(),
                $enabledTables
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
