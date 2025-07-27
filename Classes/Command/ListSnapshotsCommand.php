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
use Cpsit\T3hauler\Traits\Command\CommandInputOutputTrait;
use Cpsit\T3hauler\Traits\Command\CommandOptionsTrait;
use Cpsit\T3hauler\Traits\Command\CommandProgressTrait;
use Cpsit\T3hauler\Traits\Command\CommandUtilityTrait;
use DWenzel\T3extensionTools\Command\OptionAwareInterface;
use DWenzel\T3extensionTools\Traits\Command\ConfigureTrait;
use DWenzel\T3extensionTools\Traits\Command\OptionAwareTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
    use CommandInputOutputTrait;
    use CommandUtilityTrait;
    use CommandProgressTrait;
    use CommandOptionsTrait;

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
        $this->initializeIO($input, $output);

        try {
            // Get command options with validation
            $orderBy = $input->getOption('order') ?? 'created_at';
            $direction = $input->getOption('direction') ?? 'desc';
            $limit = $this->getLimit($input);
            $filter = $this->getFilter($input);
            $showDetails = (bool)$input->getOption('details');

            // Get snapshots from repository
            $snapshots = $this->getSnapshots($orderBy, $direction, $limit, $filter);

            if (empty($snapshots)) {
                $this->reportWarning('No snapshots found');
                $this->displaySnapshotInfo();
                return Command::SUCCESS;
            }

            // Display snapshots title
            $this->displayCommandTitle('t3hauler Snapshots');

            if ($showDetails) {
                $this->displayDetailedSnapshots($snapshots);
            } else {
                $this->displaySnapshotsAsTable($snapshots);
            }

            // Display summary
            $this->displaySnapshotSummary($snapshots);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io = $this->getIO();
            $io->error('Command failed: ' . $e->getMessage());
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
     * @param DataSnapshot[] $snapshots
     */
    private function displaySnapshotsAsTable(array $snapshots): void
    {
        $rows = [];

        foreach ($snapshots as $snapshot) {
            $createdAt = $snapshot->getCreatedAt()->format('Y-m-d H:i:s');
            $tableData = $snapshot->getMetadataValue('table_data', []);
            $totalRecords = $this->calculateTotalRecords($tableData);
            $size = $this->formatFileSize(strlen(json_encode($tableData)));
            $hash = $this->truncateString($snapshot->getHash(), 10);

            $rows[] = [
                $snapshot->getIdentifier(),
                $createdAt,
                number_format($totalRecords),
                $size,
                $hash,
            ];
        }

        $this->displaySummaryTable([
            'Snapshot ID',
            'Created',
            'Records',
            'Size',
            'Hash',
        ], $rows, 'Snapshots');
    }

    /**
     * Display detailed snapshots information
     */
    private function displayDetailedSnapshots(array $snapshots): void
    {
        foreach ($snapshots as $snapshot) {
            $this->displaySection('Snapshot: ' . $snapshot->getIdentifier());

            $tableData = $snapshot->getMetadataValue('table_data', []);
            $tables = is_array($tableData) ? count($tableData) : 0;
            $totalRecords = $this->calculateTotalRecords($tableData);
            $size = $this->formatFileSize(strlen(json_encode($tableData)));

            $info = [
                ['Created', $snapshot->getCreatedAt()->format('Y-m-d H:i:s')],
                ['Hash', $snapshot->getHash()],
                ['Tables', $tables],
                ['Total Records', number_format($totalRecords)],
                ['Size', $size],
            ];

            $this->displaySummaryTable(['Property', 'Value'], $info, 'Details');

            if (!empty($tableData)) {
                $tableRows = [];
                foreach ($tableData as $tableName => $data) {
                    $recordCount = is_array($data) ? count($data) : 0;
                    $tableRows[] = [$tableName, number_format($recordCount)];
                }

                $this->displaySummaryTable(['Table', 'Records'], $tableRows, 'Table Data');
            }

            $this->getIO()->newLine();
        }
    }

    /**
     * Display summary information
     */
    private function displaySnapshotSummary(array $snapshots): void
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

        $summaryData = [
            ['Total snapshots', $totalSnapshots],
            ['Total tables', number_format($totalTables)],
            ['Total records', number_format($totalRecords)],
            ['Total size', $this->formatFileSize($totalSize)],
            ['Average records per snapshot', number_format($totalRecords / $totalSnapshots)],
        ];

        if ($oldestSnapshot) {
            $summaryData[] = ['Oldest snapshot', $oldestSnapshot->getIdentifier() . ' (' . $oldestSnapshot->getCreatedAt()->format('Y-m-d H:i:s') . ')'];
        }

        if ($newestSnapshot) {
            $summaryData[] = ['Newest snapshot', $newestSnapshot->getIdentifier() . ' (' . $newestSnapshot->getCreatedAt()->format('Y-m-d H:i:s') . ')'];
        }

        $this->displaySummaryTable(['Metric', 'Value'], $summaryData, 'Snapshot Summary');
    }

    /**
     * Display snapshot configuration info
     */
    private function displaySnapshotInfo(): void
    {
        $this->displaySection('Snapshot Configuration');

        $enabledTables = $this->configuration->get('t3hauler.detection.enabledTables', []);
        $excludeFields = $this->configuration->get('t3hauler.detection.excludeFields', []);

        if (empty($enabledTables)) {
            $this->reportInfo('No tables configured for snapshot creation');
            $this->reportNote('Configure detection.enabledTables to enable snapshot creation');
        } else {
            $this->reportInfo('Enabled tables: ' . implode(', ', $enabledTables));
        }

        if (!empty($excludeFields)) {
            $this->reportInfo('Excluded fields: ' . implode(', ', $excludeFields));
        }

        $this->reportInfo('To create a snapshot, use: t3hauler:snapshot:create');
    }

}
