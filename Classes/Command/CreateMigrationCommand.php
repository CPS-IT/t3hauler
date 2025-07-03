<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Command;

use Cpsit\T3hauler\Service\ChangeDetectionService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to create migration from detected changes
 * 
 * Note: Full implementation will be completed in Phase 2
 */
#[AsCommand(name: 't3hauler:create')]
class CreateMigrationCommand extends Command
{
    public function __construct(
        private readonly ChangeDetectionService $changeDetectionService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Create migration from detected changes')
            ->setHelp('This command generates a migration file from the detected database changes.')
            ->addArgument(
                'description',
                InputArgument::REQUIRED,
                'Description of the migration'
            )
            ->addOption(
                'author',
                'a',
                InputOption::VALUE_OPTIONAL,
                'Author of the migration',
                'Developer'
            )
            ->addOption(
                'site',
                's',
                InputOption::VALUE_OPTIONAL,
                'Site identifier for multi-site setup'
            )
            ->addOption(
                'dry-run',
                'd',
                InputOption::VALUE_NONE,
                'Show what would be generated without creating files'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $description = $input->getArgument('description');
        $author = $input->getOption('author');
        $site = $input->getOption('site');
        $dryRun = $input->getOption('dry-run');

        $io->title('T3Hauler - Create Migration');

        try {
            // Check if there are any changes first
            $summary = $this->changeDetectionService->getChangesSummary();
            
            if (!$summary['has_changes']) {
                $io->info('No changes detected since last snapshot. Nothing to migrate.');
                return Command::SUCCESS;
            }

            $io->section('Detected Changes');
            $this->showChangesSummary($io, $summary);

            if ($dryRun) {
                $io->note('DRY RUN MODE - No files will be created');
            }

            // TODO: Implement migration generation in Phase 2
            $io->warning('Migration generation is not yet implemented.');
            $io->note('This feature will be available in Phase 2 of the implementation.');
            $io->note('Planned features:');
            $io->listing([
                'Generate Doctrine migration files',
                'Export changed records to T3D format',
                'Create migration metadata',
                'Store migration in configured path'
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Error creating migration: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function showChangesSummary(SymfonyStyle $io, array $summary): void
    {
        $rows = [];
        
        if (!empty($summary['changed_tables'])) {
            $rows[] = ['Changed', count($summary['changed_tables']), implode(', ', $summary['changed_tables'])];
        }
        
        if (!empty($summary['no_baseline_tables'])) {
            $rows[] = ['No Baseline', count($summary['no_baseline_tables']), implode(', ', $summary['no_baseline_tables'])];
        }

        if (!empty($rows)) {
            $io->table(['Status', 'Count', 'Tables'], $rows);
        }
    }
}