<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Command;

use Cpsit\T3hauler\Command\Option\ListFormatOption;
use Cpsit\T3hauler\Command\Option\PathOption;
use Cpsit\T3hauler\Command\Option\StatusOption;
use Cpsit\T3hauler\Domain\Model\Migration;
use Cpsit\T3hauler\Domain\Repository\MigrationFileRepository;
use DWenzel\T3extensionTools\Command\OptionAwareInterface;
use DWenzel\T3extensionTools\Traits\Command\ConfigureTrait;
use DWenzel\T3extensionTools\Traits\Command\OptionAwareTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to list all available migrations
 */
#[AsCommand(
    name: 't3hauler:migration:list',
    description: 'List all available migrations',
    aliases: ['haul:list'],
    help: 'This command lists all migrations found in the configured migration paths.'
)]
class ListMigrationsCommand extends Command implements OptionAwareInterface
{
    use OptionAwareTrait;
    use ConfigureTrait;

    public const string MESSAGE_DESCRIPTION_COMMAND = 'List all available migrations';
    public const string MESSAGE_HELP_COMMAND = 'This command lists all migrations found in the configured migration paths.';

    protected const array OPTIONS = [
        ListFormatOption::class,
        StatusOption::class,
        PathOption::class,
    ];

    protected static array $optionsToConfigure = self::OPTIONS;

    public function __construct(
        private readonly MigrationFileRepository $migrationFileRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $format = $input->getOption(ListFormatOption::NAME);
        $statusFilter = $input->getOption(StatusOption::NAME);
        $pathFilter = $input->getOption(PathOption::NAME);

        try {
            $migrations = $this->findAllMigrations($pathFilter);

            if (empty($migrations)) {
                $io->warning('No migrations found in configured paths');
                $this->displayMigrationPaths($io);
                return Command::SUCCESS;
            }

            // Filter by status if specified
            if ($statusFilter) {
                $migrations = array_filter($migrations, function ($migration) use ($statusFilter) {
                    return $migration['status']->value === $statusFilter;
                });
            }

            $io->title('T3Hauler Migrations');

            if (!empty($migrations)) {
                $this->displayMigrations($io, $migrations, $format);
                $this->displaySummary($io, $migrations);
            } else {
                $io->info('No migrations found matching the specified criteria');
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Failed to list migrations: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Find all migrations in configured paths
     */
    private function findAllMigrations(?string $pathFilter = null): array
    {
        $allMigrations = $this->migrationFileRepository->findAll();
        $migrations = [];

        foreach ($allMigrations as $migration) {
            // Skip if path filter is specified and doesn't match
            if ($pathFilter && !str_contains($migration['path'], $pathFilter)) {
                continue;
            }

            $metadata = $migration['data']['metadata'] ?? [];
            $status = $this->migrationFileRepository->getMigrationStatus($migration['id']);

            $migrations[] = [
                'id' => $migration['id'],
                'file' => $migration['file'],
                'path' => $migration['path'],
                'description' => $metadata['description'] ?? 'No description',
                'created' => $this->formatTimestamp($metadata['created_at'] ?? time()),
                'status' => $status,
                'tables' => isset($metadata['exported_tables']) ? count($metadata['exported_tables']) : 0,
                'records' => $metadata['total_records'] ?? 0,
                'format_version' => $metadata['format_version'] ?? 'unknown',
            ];
        }

        return $migrations;
    }

    /**
     * Display migrations in the specified format
     */
    private function displayMigrations(SymfonyStyle $io, array $migrations, string $format): void
    {
        switch ($format) {
            case 'json':
                $io->text(json_encode($migrations, JSON_PRETTY_PRINT));
                break;

            case 'table':
            default:
                $this->displayMigrationsAsTable($io, $migrations);
                break;
        }
    }

    /**
     * Display migrations as table
     */
    private function displayMigrationsAsTable(SymfonyStyle $io, array $migrations): void
    {
        $rows = [];

        foreach ($migrations as $migration) {
            $status = $migration['status']->getColoredStatus();

            $rows[] = [
                $migration['id'],
                $migration['description'],
                $migration['created'],
                $status,
                $migration['tables'],
                $migration['records'],
                $migration['path'],
            ];
        }

        $io->table([
            'Migration ID',
            'Description',
            'Created',
            'Status',
            'Tables',
            'Records',
            'Path',
        ], $rows);
    }

    /**
     * Display summary information
     */
    private function displaySummary(SymfonyStyle $io, array $migrations): void
    {
        $statusCounts = [];
        foreach ($migrations as $migration) {
            $statusValue = $migration['status']->value;
            $statusCounts[$statusValue] = ($statusCounts[$statusValue] ?? 0) + 1;
        }
        $totalRecords = array_sum(array_column($migrations, 'records'));
        $totalTables = array_sum(array_column($migrations, 'tables'));

        $io->section('Summary');

        $summaryData = [
            ['Total migrations', count($migrations)],
            ['Total records', number_format($totalRecords)],
            ['Total tables affected', $totalTables],
        ];

        foreach ($statusCounts as $status => $count) {
            $summaryData[] = [ucfirst($status) . ' migrations', $count];
        }

        $io->table(['Metric', 'Value'], $summaryData);
    }

    /**
     * Display configured migration paths
     */
    private function displayMigrationPaths(SymfonyStyle $io): void
    {
        $pathInfo = $this->migrationFileRepository->validatePaths();

        $io->section('Configured Migration Paths');

        if (empty($pathInfo)) {
            $io->text('No migration paths configured');
            return;
        }

        $pathRows = [];
        foreach ($pathInfo as $info) {
            $status = $info['exists'] ? '✓ Exists' : '✗ Missing';
            $pathRows[] = [
                $info['path'],
                $info['absolute_path'],
                $status,
                $info['migration_count'],
            ];
        }

        $io->table(['Relative Path', 'Absolute Path', 'Status', 'Migrations'], $pathRows);
    }

    /**
     * Format timestamp from migration ID
     */
    private function formatTimestamp(int $timestamp): string
    {
        return date('Y-m-d H:i:s', $timestamp);
    }

}
