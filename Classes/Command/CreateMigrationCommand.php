<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Command;

use Cpsit\T3hauler\Command\Argument\DescriptionArgument;
use Cpsit\T3hauler\Command\Option\AuthorOption;
use Cpsit\T3hauler\Command\Option\DryRunOption;
use Cpsit\T3hauler\Command\Option\SiteOption;
use Cpsit\T3hauler\Service\FilesystemInterface;
use Cpsit\T3hauler\Service\MigrationService;
use Cpsit\T3hauler\Traits\Command\CommandErrorHandlingTrait;
use Cpsit\T3hauler\Traits\Command\CommandInputOutputTrait;
use Cpsit\T3hauler\Traits\Command\CommandOptionsTrait;
use Cpsit\T3hauler\Traits\Command\CommandProgressTrait;
use Cpsit\T3hauler\Traits\Command\CommandUtilityTrait;
use Cpsit\T3hauler\Traits\Command\CommandValidationTrait;
use DWenzel\T3extensionTools\Command\ArgumentAwareInterface;
use DWenzel\T3extensionTools\Command\OptionAwareInterface;
use DWenzel\T3extensionTools\Traits\Command\ArgumentAwareTrait;
use DWenzel\T3extensionTools\Traits\Command\ConfigureTrait;
use DWenzel\T3extensionTools\Traits\Command\OptionAwareTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
    use CommandInputOutputTrait;
    use CommandErrorHandlingTrait;
    use CommandProgressTrait;
    use CommandUtilityTrait;
    use CommandOptionsTrait;
    use CommandValidationTrait;

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
        $this->initializeIO($input, $output);

        return $this->safeExecute(function () use ($input): int {
            $description = $this->getDescriptionArgument($input);
            $author = $this->getAuthor($input);
            $site = $this->getSite($input);
            $dryRun = $this->isDryRun($input);

            $this->displayCommandTitle('t3hauler - Create Migration');

            if ($dryRun) {
                $this->reportDryRunMode();
            }

            // Create migration using the service
            $result = $this->migrationService->createMigration(
                $description,
                $author,
                $site,
                $dryRun
            );

            if (!$result['success']) {
                return $this->reportValidationError($result['message']);
            }

            return $this->displayMigrationResult($result, $dryRun);
        }, 'Creating migration');
    }

    private function displayMigrationResult(array $result, bool $dryRun): int
    {
        if ($dryRun) {
            $this->reportSuccess('DRY RUN: Migration would be created successfully');
            $this->displaySection('Migration Details');
            $migration = $result['migration'];

            $this->getIO()->definitionList(
                ['Migration ID' => $migration['migration_id']],
                ['Description' => $migration['description']],
                ['Author' => $migration['author']],
                ['Site' => $migration['site'] ?? 'N/A'],
                ['Export File' => $migration['export_file']],
                ['Format' => $migration['format']],
            );

            $this->showChangesSummary($migration['changes']);
            return Command::SUCCESS;
        }

        // Show success message for actual creation
        $this->reportSuccess($result['message']);

        $migration = $result['migration'];
        $this->displaySection('Migration Created');
        $this->getIO()->definitionList(
            ['Migration ID' => $migration->getMigrationId()],
            ['Name' => $migration->getName()],
            ['Author' => $migration->getAuthor()],
            ['Status' => $migration->getStatus()],
            ['Created At' => $migration->getCreatedAt()->format('Y-m-d H:i:s')],
            ['Source Hash' => $this->truncateString($migration->getSourceHash(), 16)]
        );

        $this->displaySection('Files Created');
        $this->displaySummaryTable(
            ['Type', 'Path', 'Size'],
            [
                ['Data Export', $result['files']['export_file'], $this->getFileSizeForDisplay($result['files']['export_file'])],
            ],
            'Files'
        );

        if (!empty($result['snapshots'])) {
            $this->displaySection('Snapshots Created');
            $rows = [];
            foreach ($result['snapshots'] as $snapshot) {
                $rows[] = [
                    $snapshot->getTableName(),
                    $snapshot->getIdentifier(),
                    $this->truncateString($snapshot->getHash(), 12),
                    $snapshot->getCreatedAt()->format('Y-m-d H:i:s'),
                ];
            }
            $this->displaySummaryTable(['Table', 'Identifier', 'Hash', 'Created At'], $rows, 'Snapshots');
        }

        $this->reportNote('Migration is ready to be applied on target systems using \'t3hauler:apply ' . $migration->getMigrationId() . '\'');

        return Command::SUCCESS;
    }

    private function showChangesSummary(array $summary): void
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
            $this->displaySummaryTable(['Status', 'Count', 'Tables'], $rows, 'Changes Summary');
        }
    }

    private function getFileSizeForDisplay(string $filePath): string
    {
        if (!$this->filesystem->exists($filePath)) {
            return 'N/A';
        }

        $size = $this->filesystem->getFileSize($filePath);
        return $this->formatFileSize($size);
    }
}
