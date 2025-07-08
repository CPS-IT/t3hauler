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
 * Command to show pending changes since last snapshot
 */
#[AsCommand(
    name: 't3hauler:diff',
    description: 'Show pending changes since last snapshot',
    aliases: ['haul:diff']
)]
class DiffCommand extends Command
{
    public function __construct(
        private readonly ChangeDetectionService $changeDetectionService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Show pending changes since last snapshot')
            ->setHelp('This command compares the current database state with the last snapshot to show what has changed.')
            ->addOption(
                'baseline',
                'b',
                InputOption::VALUE_OPTIONAL,
                'Specific baseline snapshot identifier to compare against'
            )
            ->addOption(
                'summary',
                's',
                InputOption::VALUE_NONE,
                'Show only a summary of changes'
            )
            ->addOption(
                'table',
                't',
                InputOption::VALUE_OPTIONAL,
                'Check changes for a specific table only'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $baseline = $input->getOption('baseline');
        $summaryOnly = $input->getOption('summary');
        $specificTable = $input->getOption('table');

        $io->title('T3Hauler - Change Detection');

        try {
            if ($specificTable) {
                return $this->showTableChanges($io, $specificTable, $baseline);
            }

            if ($summaryOnly) {
                return $this->showChangesSummary($io, $baseline);
            }

            return $this->showDetailedChanges($io, $baseline);

        } catch (\Exception $e) {
            $io->error('Error detecting changes: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function showChangesSummary(SymfonyStyle $io, ?string $baseline): int
    {
        $summary = $this->changeDetectionService->getChangesSummary($baseline);

        $io->section('Changes Summary');

        if (!$summary['has_changes']) {
            $io->success('No changes detected since last snapshot.');
            return Command::SUCCESS;
        }

        $io->table(
            ['Category', 'Count', 'Tables'],
            [
                [
                    'Changed',
                    count($summary['changed_tables']),
                    implode(', ', $summary['changed_tables']),
                ],
                [
                    'Unchanged',
                    count($summary['unchanged_tables']),
                    implode(', ', $summary['unchanged_tables']),
                ],
                [
                    'No Baseline',
                    count($summary['no_baseline_tables']),
                    implode(', ', $summary['no_baseline_tables']),
                ],
            ]
        );

        $totalChanged = count($summary['changed_tables']) + count($summary['no_baseline_tables']);
        if ($totalChanged > 0) {
            $io->warning("Changes detected in {$totalChanged} table(s). Use 't3hauler:create' to generate a migration.");
        }

        return Command::SUCCESS;
    }

    private function showDetailedChanges(SymfonyStyle $io, ?string $baseline): int
    {
        $changes = $this->changeDetectionService->detectChanges($baseline);

        if (empty($changes)) {
            $io->success('No tables configured for change detection.');
            return Command::SUCCESS;
        }

        $hasAnyChanges = false;
        $rows = [];

        foreach ($changes as $tableName => $tableChanges) {
            $status = $tableChanges['status'];
            $changed = $tableChanges['changed'];

            if ($changed) {
                $hasAnyChanges = true;
            }

            $statusIcon = match ($status) {
                'changed' => '🔴',
                'unchanged' => '🟢',
                'no_baseline' => '🟡',
                default => '❓'
            };

            $rows[] = [
                $statusIcon . ' ' . $tableName,
                $status,
                $changed ? 'Yes' : 'No',
                substr($tableChanges['current_hash'], 0, 12) . '...',
                $tableChanges['baseline_hash'] ? substr($tableChanges['baseline_hash'], 0, 12) . '...' : 'N/A',
            ];
        }

        $io->section('Detailed Change Detection Results');

        $io->table(
            ['Table', 'Status', 'Changed', 'Current Hash', 'Baseline Hash'],
            $rows
        );

        if (!$hasAnyChanges) {
            $io->success('No changes detected since last snapshot.');
        } else {
            $io->warning('Changes detected! Use \'t3hauler:create\' to generate a migration.');
        }

        return Command::SUCCESS;
    }

    private function showTableChanges(SymfonyStyle $io, string $tableName, ?string $baseline): int
    {
        $io->section("Changes for table: {$tableName}");

        $tableChanges = $this->changeDetectionService->detectTableChanges($tableName, [], $baseline);

        $status = $tableChanges['status'];
        $changed = $tableChanges['changed'];

        $io->definitionList(
            ['Table' => $tableName],
            ['Status' => $status],
            ['Changed' => $changed ? 'Yes' : 'No'],
            ['Current Hash' => $tableChanges['current_hash']],
            ['Baseline Hash' => $tableChanges['baseline_hash'] ?? 'N/A'],
            ['Message' => $tableChanges['message']]
        );

        if (isset($tableChanges['baseline_created_at'])) {
            $io->note('Baseline created: ' . $tableChanges['baseline_created_at']->format('Y-m-d H:i:s'));
        }

        if ($changed) {
            $io->warning('Changes detected in this table.');
        } else {
            $io->success('No changes detected in this table.');
        }

        return Command::SUCCESS;
    }
}
