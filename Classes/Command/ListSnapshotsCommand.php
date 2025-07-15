<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Command;

use Cpsit\T3hauler\Command\Option\DetailsOption;
use Cpsit\T3hauler\Command\Option\DirectionOption;
use Cpsit\T3hauler\Command\Option\FilterOption;
use Cpsit\T3hauler\Command\Option\LimitOption;
use Cpsit\T3hauler\Command\Option\OrderOption;
use Cpsit\T3hauler\Configuration\T3HaulerConfiguration;
use Cpsit\T3hauler\Domain\Model\DataSnapshot;
use Cpsit\T3hauler\Domain\Repository\DataSnapshotRepository;
use DWenzel\T3extensionTools\Command\OptionAwareInterface;
use DWenzel\T3extensionTools\Traits\Command\ConfigureTrait;
use DWenzel\T3extensionTools\Traits\Command\OptionAwareTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to list all existing snapshots
 */
#[AsCommand(
    name: 't3hauler:snapshots:list',
    description: 'List all existing snapshots',
    aliases: ['haul:snap:list'],
    help: 'This command lists all snapshots stored in the database with their metadata and statistics.'
)]
class ListSnapshotsCommand extends Command implements OptionAwareInterface
{
    use OptionAwareTrait;
    use ConfigureTrait;

    public const string MESSAGE_DESCRIPTION_COMMAND = 'List all existing snapshots';
    public const string MESSAGE_HELP_COMMAND = 'This command lists all snapshots stored in the database with their metadata and statistics.';

    protected const array OPTIONS = [
        LimitOption::class,
        OrderOption::class,
        DirectionOption::class,
        FilterOption::class,
        DetailsOption::class,
    ];

    protected static array $optionsToConfigure = self::OPTIONS;

    public function __construct(
        private readonly T3HaulerConfiguration $configuration,
        private readonly DataSnapshotRepository $snapshotRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = $input->getOption(LimitOption::NAME) ? (int)$input->getOption(LimitOption::NAME) : null;
        $orderBy = $input->getOption(OrderOption::NAME);
        $direction = $input->getOption(DirectionOption::NAME);
        $filter = $input->getOption(FilterOption::NAME);
        $showDetails = $input->getOption(DetailsOption::NAME);

        try {
            $snapshots = $this->getSnapshots($orderBy, $direction, $limit, $filter);

            if (empty($snapshots)) {
                $io->warning('No snapshots found');
                $this->displaySnapshotInfo($io);
                return Command::SUCCESS;
            }

            $io->title('t3hauler Snapshots');

            $displayMethod = $showDetails ? 'displayDetailedSnapshots' : 'displaySnapshotsAsTable';
            $this->{$displayMethod}($io, $snapshots);

            $this->displaySummary($io, $snapshots);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Failed to list snapshots: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Get snapshots from repository
     */
    private function getSnapshots(string $orderBy, string $direction, ?int $limit, ?string $filter): array
    {
        $snapshots = $this->snapshotRepository->findAll();

        // Apply filter if specified
        if ($filter) {
            $snapshots = array_filter($snapshots, function ($snapshot) use ($filter) {
                return str_contains(strtolower($snapshot->getIdentifier()), strtolower($filter));
            });
        }

        // Sort snapshots
        usort($snapshots, function ($a, $b) use ($orderBy, $direction) {
            $valueA = match ($orderBy) {
                'created_at' => $a->getCreatedAt()->getTimestamp(),
                'hash' => $a->getHash(),
                default => $a->getIdentifier()
            };
            $valueB = match ($orderBy) {
                'created_at' => $b->getCreatedAt()->getTimestamp(),
                'hash' => $b->getHash(),
                default => $b->getIdentifier()
            };

            $result = $valueA <=> $valueB;

            return $direction === 'desc' ? -$result : $result;
        });

        // Apply limit if specified
        if ($limit) {
            $snapshots = array_slice($snapshots, 0, $limit);
        }

        return $snapshots;
    }

    /**
     * Display snapshots as table
     * @param SymfonyStyle $io
     * @param DataSnapshot[] $snapshots
     * @noinspection PhpUnusedPrivateMethodInspection
     */
    private function displaySnapshotsAsTable(SymfonyStyle $io, array $snapshots): void
    {
        $rows = [];

        foreach ($snapshots as $snapshot) {
            $createdAt = $snapshot->getCreatedAt()->format('Y-m-d H:i:s');
            $tableData = $snapshot->getMetadataValue('table_data', []);
            $totalRecords = $this->calculateTotalRecords($tableData);
            $size = $this->formatSize(strlen(json_encode($tableData)));
            $hash = substr($snapshot->getHash(), 0, 8) . '...';

            $rows[] = [
                $snapshot->getIdentifier(),
                $createdAt,
                number_format($totalRecords),
                $size,
                $hash,
            ];
        }

        $io->table([
            'Snapshot ID',
            'Created',
            'Records',
            'Size',
            'Hash',
        ], $rows);
    }

    /**
     * Display detailed snapshots information
     * @noinspection PhpUnusedPrivateMethodInspection
     */
    private function displayDetailedSnapshots(SymfonyStyle $io, array $snapshots): void
    {
        foreach ($snapshots as $snapshot) {
            $io->section('Snapshot: ' . $snapshot->getIdentifier());

            $tableData = $snapshot->getMetadataValue('table_data', []);
            $tables = is_array($tableData) ? count($tableData) : 0;
            $totalRecords = $this->calculateTotalRecords($tableData);
            $size = $this->formatSize(strlen(json_encode($tableData)));

            $info = [
                ['Created', $snapshot->getCreatedAt()->format('Y-m-d H:i:s')],
                ['Hash', $snapshot->getHash()],
                ['Tables', $tables],
                ['Total Records', number_format($totalRecords)],
                ['Size', $size],
            ];

            $io->table(['Property', 'Value'], $info);

            if (!empty($tableData)) {
                $tableRows = [];
                foreach ($tableData as $tableName => $data) {
                    $recordCount = is_array($data) ? count($data) : 0;
                    $tableRows[] = [$tableName, number_format($recordCount)];
                }

                $io->table(['Table', 'Records'], $tableRows);
            }

            $io->newLine();
        }
    }

    /**
     * Display summary information
     */
    private function displaySummary(SymfonyStyle $io, array $snapshots): void
    {
        if (empty($snapshots)) {
            return;
        }

        $totalSnapshots = count($snapshots);
        $totalTables = 0;
        $totalRecords = 0;
        $totalSize = 0;
        $oldestSnapshot = null;
        $newestSnapshot = null;

        foreach ($snapshots as $snapshot) {
            $tableData = $snapshot->getMetadataValue('table_data', []);
            $totalTables += is_array($tableData) ? count($tableData) : 0;
            $totalRecords += $this->calculateTotalRecords($tableData);
            $totalSize += strlen(json_encode($tableData));

            if (!$oldestSnapshot || $snapshot->getCreatedAt()->getTimestamp() < $oldestSnapshot->getCreatedAt()->getTimestamp()) {
                $oldestSnapshot = $snapshot;
            }

            if (!$newestSnapshot || $snapshot->getCreatedAt()->getTimestamp() > $newestSnapshot->getCreatedAt()->getTimestamp()) {
                $newestSnapshot = $snapshot;
            }
        }

        $io->section('Summary');

        $summaryData = [
            ['Total snapshots', $totalSnapshots],
            ['Total tables', number_format($totalTables)],
            ['Total records', number_format($totalRecords)],
            ['Total size', $this->formatSize($totalSize)],
            ['Average records per snapshot', number_format($totalRecords / $totalSnapshots)],
        ];

        if ($oldestSnapshot) {
            $summaryData[] = ['Oldest snapshot', $oldestSnapshot->getIdentifier() . ' (' . $oldestSnapshot->getCreatedAt()->format('Y-m-d H:i:s') . ')'];
        }

        if ($newestSnapshot) {
            $summaryData[] = ['Newest snapshot', $newestSnapshot->getIdentifier() . ' (' . $newestSnapshot->getCreatedAt()->format('Y-m-d H:i:s') . ')'];
        }

        $io->table(['Metric', 'Value'], $summaryData);
    }

    /**
     * Display snapshot configuration info
     */
    private function displaySnapshotInfo(SymfonyStyle $io): void
    {
        $io->section('Snapshot Configuration');

        $enabledTables = $this->configuration->get('t3hauler.detection.enabledTables', []);
        $excludeFields = $this->configuration->get('t3hauler.detection.excludeFields', []);

        if (empty($enabledTables)) {
            $io->text('No tables configured for snapshot creation');
            $io->note('Configure detection.enabledTables to enable snapshot creation');
        } else {
            $io->text('Enabled tables: ' . implode(', ', $enabledTables));
        }

        if (!empty($excludeFields)) {
            $io->text('Excluded fields: ' . implode(', ', $excludeFields));
        }

        $io->text('To create a snapshot, use: t3hauler:snapshot:create');
    }

    /**
     * Calculate total records in snapshot
     */
    private function calculateTotalRecords(mixed $tableData): int
    {
        if (!is_array($tableData)) {
            return 0;
        }

        $total = 0;
        foreach ($tableData as $data) {
            if (is_array($data)) {
                $total += count($data);
            }
        }
        return $total;
    }

    /**
     * Format byte size in human readable format
     */
    private function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor((strlen((string)$bytes) - 1) / 3);

        return sprintf('%.1f %s', $bytes / (1024 ** $factor), $units[$factor] ?? 'TB');
    }
}
