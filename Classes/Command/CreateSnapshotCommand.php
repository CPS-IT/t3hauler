<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Command;

use Cpsit\T3hauler\Command\Option\IdentifierOption;
use Cpsit\T3hauler\Command\Option\MigrationVersionOption;
use Cpsit\T3hauler\Service\ChangeDetectionService;
use DWenzel\T3extensionTools\Command\OptionAwareInterface;
use DWenzel\T3extensionTools\Traits\Command\ConfigureTrait;
use DWenzel\T3extensionTools\Traits\Command\OptionAwareTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
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
class CreateSnapshotCommand extends Command implements OptionAwareInterface
{
    use OptionAwareTrait;
    use ConfigureTrait;

    public const string MESSAGE_DESCRIPTION_COMMAND = 'Create database snapshots';
    public const string MESSAGE_HELP_COMMAND = 'This command allows you to create baseline snapshots for change detection.';

    protected const array OPTIONS = [
        IdentifierOption::class,
        MigrationVersionOption::class,
    ];

    /**
     * @var array|string[]
     */
    protected static array $optionsToConfigure = self::OPTIONS;

    public function __construct(
        private readonly ChangeDetectionService $changeDetectionService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $identifier = $input->getOption(IdentifierOption::NAME);
        $migrationVersion = $input->getOption(MigrationVersionOption::NAME);

        $io->title('t3hauler - Create Snapshot');

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
