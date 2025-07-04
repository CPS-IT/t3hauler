<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Command;

use Cpsit\T3hauler\Service\MigrationService;
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
#[AsCommand(
    name: 't3hauler:create',
    description: 'Create migration from detected changes',
    aliases: ['haul:create']
)]
class CreateMigrationCommand extends Command
{
    public function __construct(
        private readonly MigrationService $migrationService
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
            // Create migration using the service
            $result = $this->migrationService->createMigration(
                $description,
                $author,
                $site,
                $dryRun
            );

            if (!$result['success']) {
                $io->error($result['message']);
                return Command::FAILURE;
            }

            if ($dryRun) {
                $io->success('DRY RUN: Migration would be created successfully');
                $io->section('Migration Details');
                $migration = $result['migration'];
                $io->definitionList(
                    ['Migration ID' => $migration['migration_id']],
                    ['Description' => $migration['description']],
                    ['Author' => $migration['author']],
                    ['Site' => $migration['site'] ?? 'N/A'],
                    ['Export File' => $migration['export_file']],
                    ['Format' => $migration['format']],
                    ['Metadata File' => $migration['metadata_file']]
                );

                $this->showChangesSummary($io, $migration['changes']);

                return Command::SUCCESS;
            }

            // Show success message for actual creation
            $io->success($result['message']);

            $migration = $result['migration'];
            $io->section('Migration Created');
            $io->definitionList(
                ['Migration ID' => $migration->getMigrationId()],
                ['Name' => $migration->getName()],
                ['Author' => $migration->getAuthor()],
                ['Status' => $migration->getStatus()],
                ['Created At' => $migration->getCreatedAt()->format('Y-m-d H:i:s')],
                ['Source Hash' => substr($migration->getSourceHash(), 0, 16) . '...']
            );

            $io->section('Files Created');
            $io->table(
                ['Type', 'Path', 'Size'],
                [
                    ['Data Export', $result['files']['export_file'], $this->formatFileSize($result['files']['export_file'])],
                    ['Metadata', $result['files']['metadata_file'], $this->formatFileSize($result['files']['metadata_file'])],
                ]
            );

            if (!empty($result['snapshots'])) {
                $io->section('Snapshots Created');
                $rows = [];
                foreach ($result['snapshots'] as $snapshot) {
                    $rows[] = [
                        $snapshot->getTableName(),
                        $snapshot->getIdentifier(),
                        substr($snapshot->getHash(), 0, 12) . '...',
                        $snapshot->getCreatedAt()->format('Y-m-d H:i:s'),
                    ];
                }
                $io->table(['Table', 'Identifier', 'Hash', 'Created At'], $rows);
            }

            $io->note('Migration is ready to be applied on target systems using \'t3hauler:apply ' . $migration->getMigrationId() . '\'');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Error creating migration: ' . $e->getMessage());
            if ($output->isVerbose()) {
                $io->text('<error>' . $e->getTraceAsString() . '</error>');
            }
            return Command::FAILURE;
        }
    }

    private function showChangesSummary(SymfonyStyle $io, array $summary): void
    {
        $rows = [];

        if (!empty($summary['changed_tables'])) {
            $rows[] = ['Changed', count($summary['changed_tables']), implode(', ', $summary['changed_tables'])];
        }

        if (!empty($summary['unchanged_tables'])) {
            $rows[] = ['Unchanged', count($summary['unchanged_tables']), implode(', ', $summary['unchanged_tables'])];
        }

        if (!empty($summary['no_baseline_tables'])) {
            $rows[] = ['No Baseline', count($summary['no_baseline_tables']), implode(', ', $summary['no_baseline_tables'])];
        }

        if (!empty($rows)) {
            $io->table(['Status', 'Count', 'Tables'], $rows);
        }
    }

    private function formatFileSize(string $filePath): string
    {
        if (!file_exists($filePath)) {
            return 'N/A';
        }

        $size = filesize($filePath);
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $size >= 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }

        return round($size, 2) . ' ' . $units[$i];
    }
}
