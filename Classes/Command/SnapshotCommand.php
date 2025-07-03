<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Command;

use Cpsit\T3hauler\Service\ChangeDetectionService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to create or manage database snapshots
 */
#[AsCommand(name: 't3hauler:snapshot')]
class SnapshotCommand extends Command
{
    public function __construct(
        private readonly ChangeDetectionService $changeDetectionService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Create or manage database snapshots')
            ->setHelp('This command allows you to create baseline snapshots for change detection.')
            ->addOption(
                'create',
                'c',
                InputOption::VALUE_NONE,
                'Create a new snapshot of the current database state'
            )
            ->addOption(
                'identifier',
                'i',
                InputOption::VALUE_OPTIONAL,
                'Custom identifier for the snapshot'
            )
            ->addOption(
                'migration-version',
                'm',
                InputOption::VALUE_OPTIONAL,
                'Migration version to associate with this snapshot'
            )
            ->addOption(
                'cleanup',
                null,
                InputOption::VALUE_OPTIONAL,
                'Clean up snapshots older than specified days',
                null
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $create = $input->getOption('create');
        $identifier = $input->getOption('identifier');
        $migrationVersion = $input->getOption('migration-version');
        $cleanup = $input->getOption('cleanup');

        $io->title('T3Hauler - Snapshot Management');

        try {
            if ($create) {
                return $this->createSnapshot($io, $identifier, $migrationVersion);
            }

            if ($cleanup !== null) {
                return $this->cleanupSnapshots($io, (int)$cleanup);
            }

            $io->error('No action specified. Use --create to create a snapshot or --cleanup to clean old snapshots.');
            return Command::FAILURE;

        } catch (\Exception $e) {
            $io->error('Error managing snapshots: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function createSnapshot(SymfonyStyle $io, ?string $identifier, ?string $migrationVersion): int
    {
        $io->section('Creating Snapshot');

        if ($identifier === null) {
            $identifier = 'baseline_' . date('YmdHis');
        }

        $io->note("Creating snapshot with identifier: {$identifier}");

        if ($migrationVersion !== null) {
            $io->note("Associated with migration version: {$migrationVersion}");
        }

        $snapshots = $this->changeDetectionService->createSnapshot($identifier, $migrationVersion);

        $io->success('Snapshot created successfully!');

        $rows = [];
        foreach ($snapshots as $snapshot) {
            $rows[] = [
                $snapshot->getTableName(),
                $snapshot->getIdentifier(),
                substr($snapshot->getHash(), 0, 12) . '...',
                $snapshot->getCreatedAt()->format('Y-m-d H:i:s')
            ];
        }

        $io->table(
            ['Table', 'Identifier', 'Hash', 'Created At'],
            $rows
        );

        $io->note('This snapshot can now be used as a baseline for change detection.');

        return Command::SUCCESS;
    }

    private function cleanupSnapshots(SymfonyStyle $io, int $keepDays): int
    {
        $io->section("Cleaning up snapshots older than {$keepDays} days");

        if ($keepDays < 1) {
            $io->error('Keep days must be at least 1.');
            return Command::FAILURE;
        }

        $io->note("This will delete all snapshots older than {$keepDays} days.");
        
        if (!$io->confirm('Are you sure you want to proceed?', false)) {
            $io->note('Cleanup cancelled.');
            return Command::SUCCESS;
        }

        $deletedCount = $this->changeDetectionService->cleanupOldSnapshots($keepDays);

        if ($deletedCount > 0) {
            $io->success("Successfully deleted {$deletedCount} old snapshots.");
        } else {
            $io->info('No old snapshots found to delete.');
        }

        return Command::SUCCESS;
    }
}