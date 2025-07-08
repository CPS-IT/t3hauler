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
#[AsCommand(
    name: 't3hauler:snapshot',
    description: 'Create database snapshots',
    aliases: ['haul:snap:create']
)]
class CreateSnapshotCommand extends Command
{
    public function __construct(
        private readonly ChangeDetectionService $changeDetectionService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Create database snapshots')
            ->setHelp('This command allows you to create baseline snapshots for change detection.')
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
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $identifier = $input->getOption('identifier');
        $migrationVersion = $input->getOption('migration-version');

        $io->title('T3Hauler - Create Snapshot');

        try {
            return $this->createSnapshot($io, $identifier, $migrationVersion);
        } catch (\Exception $e) {
            $io->error('Error managing snapshots: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function createSnapshot(SymfonyStyle $io, ?string $identifier, ?string $migrationVersion): int
    {
        $io->section('Creating Snapshot');

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
                $snapshot->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }

        $io->table(
            ['Table', 'Identifier', 'Hash', 'Created At'],
            $rows
        );

        $io->note('This snapshot can now be used as a baseline for change detection.');

        return Command::SUCCESS;
    }
}
