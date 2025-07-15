<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Command;

use Cpsit\T3hauler\Command\Argument\DescriptionArgument;
use Cpsit\T3hauler\Command\Option\AuthorOption;
use Cpsit\T3hauler\Command\Option\DryRunOption;
use Cpsit\T3hauler\Command\Option\SiteOption;
use Cpsit\T3hauler\Service\FilesystemInterface;
use Cpsit\T3hauler\Service\MigrationService;
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
 * Command to create migration from detected changes
 *
 * Note: Full implementation will be completed in Phase 2
 */
#[AsCommand(
    name: 't3hauler:create',
    description: 'Create migration from detected changes',
    aliases: ['haul:create']
)]
class CreateMigrationCommand extends Command implements ArgumentAwareInterface, OptionAwareInterface
{
    use ArgumentAwareTrait;
    use OptionAwareTrait;
    use ConfigureTrait;

    public const string MESSAGE_DESCRIPTION_COMMAND = 'Create migration from detected changes';
    public const string MESSAGE_HELP_COMMAND = 'This command generates a migration file from the detected database changes.';

    protected const array ARGUMENTS = [
        DescriptionArgument::class,
    ];

    protected const array OPTIONS = [
        AuthorOption::class,
        SiteOption::class,
        DryRunOption::class,
    ];

    protected static array $argumentsToConfigure = self::ARGUMENTS;
    protected static array $optionsToConfigure = self::OPTIONS;

    public function __construct(
        private readonly MigrationService $migrationService,
        private readonly FilesystemInterface $filesystem
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $description = $input->getArgument(DescriptionArgument::NAME);
        $author = $input->getOption(AuthorOption::NAME);
        $site = $input->getOption(SiteOption::NAME);
        $dryRun = $input->getOption(DryRunOption::NAME);

        $io->title('t3hauler - Create Migration');

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
        if (!$this->filesystem->exists($filePath)) {
            return 'N/A';
        }

        $size = $this->filesystem->getFileSize($filePath);
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $size >= 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }

        return round($size, 2) . ' ' . $units[$i];
    }
}
