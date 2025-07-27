<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Command;

use Cpsit\T3hauler\Command\Option\IdentifierOption;
use Cpsit\T3hauler\Command\Option\MigrationVersionOption;
use Cpsit\T3hauler\Service\ChangeDetectionService;
use Cpsit\T3hauler\Traits\Command\CommandErrorHandlingTrait;
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
    use CommandInputOutputTrait;
    use CommandErrorHandlingTrait;
    use CommandProgressTrait;
    use CommandUtilityTrait;
    use CommandOptionsTrait;

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
        $this->initializeIO($input, $output);

        return $this->safeExecute(function () use ($input): int {
            $identifier = $this->getIdentifier($input);
            $migrationVersion = $this->getMigrationVersion($input);

            $this->displayCommandTitle('t3hauler - Create Snapshot');

            return $this->createSnapshot($identifier, $migrationVersion);
        }, 'Creating snapshot');
    }

    private function createSnapshot(?string $identifier, ?string $migrationVersion): int
    {
        $this->displaySection('Creating Snapshot');

        $this->reportNote("Creating snapshot with identifier: {$identifier}");

        if ($migrationVersion !== null) {
            $this->reportNote("Associated with migration version: {$migrationVersion}");
        }

        $snapshots = $this->changeDetectionService->createSnapshot($identifier, $migrationVersion);

        $this->reportSuccess('Snapshot created successfully!');

        $rows = [];
        foreach ($snapshots as $snapshot) {
            $rows[] = [
                $snapshot->getTableName(),
                $snapshot->getIdentifier(),
                $this->truncateString($snapshot->getHash(), 12),
                $snapshot->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }

        $this->displaySummaryTable(
            ['Table', 'Identifier', 'Hash', 'Created At'],
            $rows,
            'Snapshots'
        );

        $this->reportNote('This snapshot can now be used as a baseline for change detection.');

        return Command::SUCCESS;
    }
}
