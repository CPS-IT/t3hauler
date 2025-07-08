<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Command;

use Cpsit\T3hauler\Configuration\T3HaulerConfiguration;
use Cpsit\T3hauler\Domain\Model\Migration;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Command to list all available migrations
 */
#[AsCommand(
    name: 't3hauler:migration:list',
    description: 'List all available migrations',
    aliases: ['haul:list'],
    help: 'This command lists all migrations found in the configured migration paths.'
)]
class ListMigrationsCommand extends Command
{
    public function __construct(
        private readonly T3HaulerConfiguration $configuration
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'format',
            'f',
            InputOption::VALUE_OPTIONAL,
            'Output format (table, json)',
            'table'
        );

        $this->addOption(
            'status',
            's',
            InputOption::VALUE_OPTIONAL,
            'Filter by status (pending, applied, failed)',
            null
        );

        $this->addOption(
            'path',
            'p',
            InputOption::VALUE_OPTIONAL,
            'Show migrations from specific path only',
            null
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $format = $input->getOption('format');
        $statusFilter = $input->getOption('status');
        $pathFilter = $input->getOption('path');

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
                    return $migration['status'] === $statusFilter;
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
        $migrationPaths = $this->configuration->getMigrationPaths();
        $migrations = [];

        if (empty($migrationPaths)) {
            throw new \RuntimeException('No migration paths configured', 7950745142);
        }

        foreach ($migrationPaths as $migrationPath) {
            // Skip if path filter is specified and doesn't match
            if ($pathFilter && !str_contains($migrationPath, $pathFilter)) {
                continue;
            }

            $absolutePath = GeneralUtility::getFileAbsFileName($migrationPath);

            if (!is_dir($absolutePath)) {
                continue;
            }

            $pathMigrations = $this->scanMigrationPath($absolutePath, $migrationPath);
            $migrations = array_merge($migrations, $pathMigrations);
        }

        // Sort by migration ID (timestamp)
        usort($migrations, function ($a, $b) {
            return strcmp($a['id'], $b['id']);
        });

        return $migrations;
    }

    /**
     * Scan a single migration path for migrations
     */
    private function scanMigrationPath(string $absolutePath, string $relativePath): array
    {
        $migrations = [];
        $files = glob($absolutePath . '/*.json');

        foreach ($files as $file) {
            $filename = basename($file);

            // Parse migration ID from filename (format: T3H_YYYYMMDDHHMMSS_hash.json)
            if (!preg_match('/T3H_(\d{14})_(.{8})\.json/', $filename, $matches)) {
                continue;
            }

            // @todo use file name minus 'json' as migration ID
            $migrationId = $matches[1] . '_' . $matches[2];

            try {
                $migrationData = json_decode(file_get_contents($file), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    continue;
                }

                $metadata = $migrationData['metadata'] ?? [];

                $migrations[] = [
                    'id' => $migrationId,
                    'file' => $filename,
                    'path' => $relativePath,
                    'description' => $metadata['description'] ?? 'No description',
                    'created' => $this->formatTimestamp($metadata['created_at']),
                    'status' => $this->determineMigrationStatus($file, $migrationData),
                    'tables' => isset($metadata['exported_tables']) ? count($metadata['exported_tables']) : 0,
                    'records' => $metadata['total_records'] ?? 0,
                    'format_version' => $metadata['format_version'] ?? 'unknown',
                ];

            } catch (\Exception $e) {
                // Skip invalid migration files
                continue;
            }
        }

        return $migrations;
    }

    /**
     * Determine migration status
     */
    private function determineMigrationStatus(string $file, array $migrationData): string
    {
        // Check if migration has been applied (this is a simplified check)
        $appliedMarker = str_replace('.json', '.applied', $file);

        if (file_exists($appliedMarker)) {
            return 'applied';
        }

        return 'pending';
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
            $status = $this->formatStatus($migration['status']);

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
        $statusCounts = array_count_values(array_column($migrations, 'status'));
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
        $migrationPaths = $this->configuration->getMigrationPaths();

        $io->section('Configured Migration Paths');

        if (empty($migrationPaths)) {
            $io->text('No migration paths configured');
            return;
        }

        $pathRows = [];
        foreach ($migrationPaths as $path) {
            $absolutePath = GeneralUtility::getFileAbsFileName($path);
            $exists = is_dir($absolutePath);
            $status = $exists ? '✓ Exists' : '✗ Missing';

            $pathRows[] = [$path, $absolutePath, $status];
        }

        $io->table(['Relative Path', 'Absolute Path', 'Status'], $pathRows);
    }

    /**
     * Format timestamp from migration ID
     */
    private function formatTimestamp(int $timestamp): string
    {
        return date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * Format status with colors
     */
    private function formatStatus(string $status): string
    {
        return match ($status) {
            'applied' => '<fg=green>applied</fg=green>',
            'pending' => '<fg=yellow>pending</fg=yellow>',
            'failed' => '<fg=red>failed</fg=red>',
            'invalid' => '<fg=red>invalid</fg=red>',
            'incomplete' => '<fg=red>incomplete</fg=red>',
            default => $status,
        };
    }
}
