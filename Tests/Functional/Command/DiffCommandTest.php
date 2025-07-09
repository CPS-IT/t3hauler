<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Tests\Functional\Command;

use Cpsit\T3hauler\Command\DiffCommand;
use Cpsit\T3hauler\Service\ChangeDetectionService;
use Cpsit\T3hauler\Tests\Functional\TestingUtilities;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Functional tests for DiffCommand
 */
final class DiffCommandTest extends FunctionalTestCase
{
    use TestingUtilities;
    private DiffCommand $command;
    private CommandTester $commandTester;
    private ChangeDetectionService $changeDetectionService;

    /**
     * @var array<non-empty-string>
     */
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/t3hauler',
    ];

    /**
     * @var array<non-empty-string>
     */
    protected array $coreExtensionsToLoad = [
        'core',
        'backend',
        'frontend',
    ];

    /**
     * @var array<string, non-empty-string>
     */
    protected array $pathsToLinkInTestInstance = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpT3HaulerTests();

        $this->changeDetectionService = GeneralUtility::makeInstance(ChangeDetectionService::class);
        $this->command = new DiffCommand($this->changeDetectionService);
        $this->commandTester = new CommandTester($this->command);
    }

    protected function tearDown(): void
    {
        $this->tearDownT3HaulerTests();
        parent::tearDown();
    }

    #[Test]
    public function commandShowsNoChangesWhenNoBaseline(): void
    {
        // Execute command without any snapshots
        $exitCode = $this->commandTester->execute([]);

        // Command should handle gracefully
        self::assertSame(Command::SUCCESS, $exitCode);

        // Verify output indicates no baseline
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('No baseline snapshot found', $output);
    }

    #[Test]
    public function commandDetectsChangesAfterSnapshot(): void
    {
        // Create initial snapshot
        $this->changeDetectionService->createTableSnapshot('pages');
        $this->changeDetectionService->createTableSnapshot('tt_content');

        // Execute diff command - should show no changes
        $exitCode = $this->commandTester->execute([]);
        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('No changes detected', $output);

        // Modify data
        $this->updateTestData('pages', ['title' => 'Modified Page Title'], ['uid' => 2]);

        // Execute diff command again - should show changes
        $exitCode = $this->commandTester->execute([]);
        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Changes detected', $output);
        self::assertStringContainsString('pages', $output);
        self::assertStringContainsString('changed', $output);
    }

    #[Test]
    public function commandShowsSummaryWithSummaryOption(): void
    {
        // Create initial snapshot
        $this->changeDetectionService->createTableSnapshot('pages');

        // Modify data
        $this->updateTestData('pages', ['title' => 'Summary Test'], ['uid' => 2]);

        // Execute diff command with summary option
        $exitCode = $this->commandTester->execute(['--summary' => true]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Summary', $output);
        self::assertStringContainsString('Total tables:', $output);
        self::assertStringContainsString('Changed tables:', $output);
        self::assertStringContainsString('Unchanged tables:', $output);
    }

    #[Test]
    public function commandFiltersSpecificTable(): void
    {
        // Create snapshots for multiple tables
        $this->changeDetectionService->createTableSnapshot('pages');
        $this->changeDetectionService->createTableSnapshot('tt_content');

        // Modify both tables
        $this->updateTestData('pages', ['title' => 'Modified Page'], ['uid' => 2]);
        $this->updateTestData('tt_content', ['header' => 'Modified Content'], ['uid' => 1]);

        // Execute diff command for specific table only
        $exitCode = $this->commandTester->execute(['--table' => 'pages']);

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('pages', $output);
        self::assertStringNotContainsString('tt_content', $output);
    }

    #[Test]
    public function commandUsesSpecificBaseline(): void
    {
        // Create multiple snapshots
        $snapshot1 = $this->changeDetectionService->createTableSnapshot('pages');

        // Modify data
        $this->updateTestData('pages', ['title' => 'Intermediate Change'], ['uid' => 2]);

        $snapshot2 = $this->changeDetectionService->createTableSnapshot('pages');

        // Modify data again
        $this->updateTestData('pages', ['title' => 'Final Change'], ['uid' => 2]);

        // Execute diff command with specific baseline
        $exitCode = $this->commandTester->execute([
            '--baseline' => $snapshot1->getIdentifier(),
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Changes detected', $output);
        self::assertStringContainsString($snapshot1->getIdentifier(), $output);
    }

    #[Test]
    public function commandShowsDetailedChanges(): void
    {
        // Create initial snapshot
        $this->changeDetectionService->createTableSnapshot('pages');

        // Modify multiple fields
        $this->updateTestData('pages', [
            'title' => 'New Title',
            'hidden' => 1,
        ], ['uid' => 2]);

        // Execute diff command without summary
        $exitCode = $this->commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Changes detected', $output);
        self::assertStringContainsString('Table: pages', $output);
        self::assertStringContainsString('Status: changed', $output);
        self::assertStringContainsString('Current hash:', $output);
        self::assertStringContainsString('Baseline hash:', $output);
    }

    #[Test]
    public function commandHandlesMultipleTableChanges(): void
    {
        // Create snapshots for multiple tables
        $this->changeDetectionService->createTableSnapshot('pages');
        $this->changeDetectionService->createTableSnapshot('tt_content');

        // Modify both tables
        $this->updateTestData('pages', ['title' => 'Changed Page'], ['uid' => 2]);
        $this->updateTestData('tt_content', ['header' => 'Changed Content'], ['uid' => 1]);

        // Execute diff command
        $exitCode = $this->commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('pages', $output);
        self::assertStringContainsString('tt_content', $output);
        self::assertStringContainsString('changed', $output);
    }

    #[Test]
    public function commandShowsNoChangesForUnchangedTables(): void
    {
        // Create snapshots
        $this->changeDetectionService->createTableSnapshot('pages');
        $this->changeDetectionService->createTableSnapshot('tt_content');

        // Modify only one table
        $this->updateTestData('pages', ['title' => 'Only Pages Changed'], ['uid' => 2]);

        // Execute diff command
        $exitCode = $this->commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('pages', $output);
        self::assertStringContainsString('changed', $output);
        self::assertStringContainsString('tt_content', $output);
        self::assertStringContainsString('unchanged', $output);
    }

    #[Test]
    public function commandSupportsVerboseOutput(): void
    {
        // Create snapshot and modify data
        $this->changeDetectionService->createTableSnapshot('pages');
        $this->updateTestData('pages', ['title' => 'Verbose Test'], ['uid' => 2]);

        // Execute command with verbose flag
        $exitCode = $this->commandTester->execute([], ['verbosity' => 2]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Checking changes', $output);
        self::assertStringContainsString('Processing table:', $output);
    }

    #[Test]
    public function commandHandlesInvalidBaselineGracefully(): void
    {
        // Execute command with non-existent baseline
        $exitCode = $this->commandTester->execute([
            '--baseline' => 'non-existent-snapshot',
        ]);

        // Command should handle invalid baseline gracefully
        self::assertContains($exitCode, [Command::SUCCESS, Command::INVALID]);

        $output = $this->commandTester->getDisplay();
        // Should contain error message about invalid baseline
        self::assertTrue(
            str_contains($output, 'not found') ||
            str_contains($output, 'invalid') ||
            str_contains($output, 'No baseline')
        );
    }

    #[Test]
    public function commandShowsHashComparison(): void
    {
        // Create snapshot
        $snapshot = $this->changeDetectionService->createTableSnapshot('pages');
        $originalHash = $snapshot->getHash();

        // Modify data
        $this->updateTestData('pages', ['title' => 'Hash Comparison Test'], ['uid' => 2]);

        // Execute diff command
        $exitCode = $this->commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Baseline hash:', $output);
        self::assertStringContainsString('Current hash:', $output);
        self::assertStringContainsString($originalHash, $output);
    }

    #[Test]
    public function commandIndicatesNoChangesOnUnmodifiedData(): void
    {
        // Create snapshot
        $this->changeDetectionService->createTableSnapshot('pages');

        // Execute diff command without any modifications
        $exitCode = $this->commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('No changes detected', $output);
    }
}
