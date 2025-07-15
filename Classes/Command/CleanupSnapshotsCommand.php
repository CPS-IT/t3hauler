<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Command;

use Cpsit\T3hauler\Command\Argument\KeepDaysArgument;
use Cpsit\T3hauler\Service\ChangeDetectionService;
use DWenzel\T3extensionTools\Command\ArgumentAwareInterface;
use DWenzel\T3extensionTools\Traits\Command\ArgumentAwareTrait;
use DWenzel\T3extensionTools\Traits\Command\ConfigureTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to create or manage database snapshots
 */
#[AsCommand(
    name: 't3hauler:snapshots:cleanup',
    description: 'Manage database snapshots',
    aliases: ['haul:snap:cleanup']
)]
class CleanupSnapshotsCommand extends Command implements ArgumentAwareInterface
{
    use ArgumentAwareTrait;
    use ConfigureTrait;

    public const string MESSAGE_DESCRIPTION_COMMAND = 'Cleanup database snapshots';
    public const string MESSAGE_HELP_COMMAND = 'This command allows you manage baseline snapshots for change detection.';

    protected const array ARGUMENTS = [
        KeepDaysArgument::class,
    ];

    protected static array $argumentsToConfigure = self::ARGUMENTS;

    public function __construct(
        private readonly ChangeDetectionService $changeDetectionService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $keepDays = $input->getArgument(KeepDaysArgument::NAME);

        $io->title('t3hauler - Create Snapshot');

        try {
            return $this->cleanupSnapshots($io, (int)$keepDays);
        } catch (\Exception $e) {
            $io->error('Error managing snapshots: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function cleanupSnapshots(SymfonyStyle $io, int $keepDays): int
    {
        $io->section("Cleaning up snapshots older than {$keepDays} days");

        if ($keepDays < 0) {
            $io->error('Keep days must not be less than 0.');
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
