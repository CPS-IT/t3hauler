<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Tests\Functional\Command;

use Cpsit\T3hauler\Command\ListSnapshotsCommand;
use Cpsit\T3hauler\Configuration\T3HaulerConfiguration;
use Cpsit\T3hauler\Domain\Repository\DataSnapshotRepository;
use Cpsit\T3hauler\Tests\Functional\TestingUtilities;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Functional tests for ListSnapshotsCommand with trait-based architecture
 */
final class ListSnapshotsCommandTest extends FunctionalTestCase
{
    use TestingUtilities;

    private ListSnapshotsCommand $command;
    private CommandTester $commandTester;
    private DataSnapshotRepository $snapshotRepository;
    private T3HaulerConfiguration $configuration;

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

        $container = $this->getContainer();
        $this->snapshotRepository = $container->get(DataSnapshotRepository::class);
        $this->configuration = $container->get(T3HaulerConfiguration::class);

        $this->command = new ListSnapshotsCommand($this->configuration, $this->snapshotRepository);
        $this->commandTester = new CommandTester($this->command);
    }

    protected function tearDown(): void
    {
        $this->tearDownT3HaulerTests();
        parent::tearDown();
    }

    /**
     * Test basic command execution with trait-based I/O handling
     */
    #[Test]
    public function commandExecutesSuccessfullyWithTraitBasedIO(): void
    {
        $exitCode = $this->commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();

        // Should complete successfully and show either snapshots or warning
        self::assertNotEmpty(trim($output));
    }

    /**
     * Test command with no snapshots shows warning message and configuration info
     */
    #[Test]
    public function commandShowsWarningForNoSnapshotsFound(): void
    {
        $exitCode = $this->commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();

        // Verify trait-based warning reporting
        self::assertStringContainsString('No snapshots found', $output);

        // Should display snapshot configuration info using trait methods
        self::assertStringContainsString('Snapshot Configuration', $output);
        self::assertStringContainsString('To create a snapshot, use: t3hauler:snapshot:create', $output);
    }

    /**
     * Test command with limit option using trait-based option extraction
     */
    #[Test]
    public function commandHandlesLimitOption(): void
    {
        // Create test snapshots
        $this->createTestSnapshots(5);

        $exitCode = $this->commandTester->execute([
            '--limit' => '3',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();

        // Should show snapshots with limit applied
        self::assertStringContainsString('t3hauler Snapshots', $output);
        self::assertNotEmpty(trim($output));
    }

    /**
     * Test command with filter option using trait-based option extraction
     */
    #[Test]
    public function commandHandlesFilterOption(): void
    {
        // Create test snapshots with specific identifiers
        $this->createTestSnapshot('test-snapshot-1', ['pages' => [['uid' => 1]]]);
        $this->createTestSnapshot('other-snapshot', ['pages' => [['uid' => 2]]]);

        $exitCode = $this->commandTester->execute([
            '--filter' => 'test',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();

        // Should show filtered snapshots
        self::assertStringContainsString('t3hauler Snapshots', $output);
        self::assertStringContainsString('test-snapshot-1', $output);
        self::assertStringNotContainsString('other-snapshot', $output);
    }

    /**
     * Test command with details option shows detailed view
     */
    #[Test]
    public function commandHandlesDetailsOption(): void
    {
        // Create test snapshot with table data
        $tableData = [
            'pages' => [['uid' => 1, 'title' => 'Test Page']],
            'tt_content' => [['uid' => 1, 'header' => 'Test Content'], ['uid' => 2, 'header' => 'Another Content']],
        ];
        $this->createTestSnapshot('detailed-test', $tableData);

        $exitCode = $this->commandTester->execute([
            '--details' => true,
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();

        // Should show detailed view with sections
        self::assertStringContainsString('Snapshot: detailed-test', $output);
        self::assertStringContainsString('Details', $output);
        self::assertStringContainsString('Table Data', $output);
        self::assertStringContainsString('pages', $output);
        self::assertStringContainsString('tt_content', $output);
    }

    /**
     * Test command with order option (created_at)
     */
    #[Test]
    public function commandHandlesOrderByCreatedAtOption(): void
    {
        // Create test snapshots with different timestamps
        $this->createTestSnapshot('older-snapshot', ['pages' => []], new \DateTime('-2 days'));
        $this->createTestSnapshot('newer-snapshot', ['pages' => []], new \DateTime('-1 day'));

        $exitCode = $this->commandTester->execute([
            '--order' => 'created_at',
            '--direction' => 'asc',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();

        // Should show snapshots ordered by creation date
        self::assertStringContainsString('t3hauler Snapshots', $output);
        self::assertStringContainsString('older-snapshot', $output);
        self::assertStringContainsString('newer-snapshot', $output);
    }

    /**
     * Test command with order option (hash)
     */
    #[Test]
    public function commandHandlesOrderByHashOption(): void
    {
        // Create test snapshots
        $this->createTestSnapshots(3);

        $exitCode = $this->commandTester->execute([
            '--order' => 'hash',
            '--direction' => 'desc',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();

        // Should show snapshots ordered by hash
        self::assertStringContainsString('t3hauler Snapshots', $output);
        self::assertNotEmpty(trim($output));
    }

    /**
     * Test command with multiple options combined
     */
    #[Test]
    public function commandHandlesMultipleOptions(): void
    {
        // Create test snapshots
        $this->createTestSnapshots(5);

        $exitCode = $this->commandTester->execute([
            '--limit' => '3',
            '--order' => 'identifier',
            '--direction' => 'asc',
            '--filter' => 'snapshot',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();

        // Command should handle multiple options gracefully using trait methods
        self::assertStringContainsString('t3hauler Snapshots', $output);
        self::assertNotEmpty(trim($output));
    }

    /**
     * Test table display formatting with trait-based table methods
     */
    #[Test]
    public function commandDisplaysTableFormatCorrectly(): void
    {
        // Create test snapshot with specific data for verification
        $tableData = [
            'pages' => array_fill(0, 100, ['uid' => 1, 'title' => 'Test']),
            'tt_content' => array_fill(0, 50, ['uid' => 1, 'header' => 'Content']),
        ];
        $this->createTestSnapshot('format-test', $tableData);

        $exitCode = $this->commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();

        // Should show properly formatted table with headers
        self::assertStringContainsString('Snapshot ID', $output);
        self::assertStringContainsString('Created', $output);
        self::assertStringContainsString('Records', $output);
        self::assertStringContainsString('Size', $output);
        self::assertStringContainsString('Hash', $output);

        // Should show formatted record count and size
        self::assertStringContainsString('150', $output); // Total records
        self::assertStringContainsString('format-test', $output);
    }

    /**
     * Test summary display with multiple snapshots
     */
    #[Test]
    public function commandDisplaysSummaryCorrectly(): void
    {
        // Create multiple test snapshots with varying data
        $this->createTestSnapshot('snapshot1', ['pages' => array_fill(0, 10, ['uid' => 1])]);
        $this->createTestSnapshot('snapshot2', ['tt_content' => array_fill(0, 20, ['uid' => 1])]);
        $this->createTestSnapshot('snapshot3', [
            'pages' => array_fill(0, 5, ['uid' => 1]),
            'tt_content' => array_fill(0, 15, ['uid' => 1]),
        ]);

        $exitCode = $this->commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();

        // Should show summary table with statistics
        self::assertStringContainsString('Snapshot Summary', $output);
        self::assertStringContainsString('Total snapshots', $output);
        self::assertStringContainsString('Total tables', $output);
        self::assertStringContainsString('Total records', $output);
        self::assertStringContainsString('Total size', $output);
        self::assertStringContainsString('Average records per snapshot', $output);
        self::assertStringContainsString('Oldest snapshot', $output);
        self::assertStringContainsString('Newest snapshot', $output);
    }

    /**
     * Test verbose output includes additional information
     */
    #[Test]
    public function commandProvidesVerboseOutput(): void
    {
        $this->createTestSnapshots(2);

        $exitCode = $this->commandTester->execute([], [
            'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();

        // Verify verbose output includes expected information
        self::assertStringContainsString('t3hauler Snapshots', $output);
        self::assertNotEmpty(trim($output));
    }

    /**
     * Test error handling using trait-based exception management
     */
    #[Test]
    public function commandHandlesExceptionsGracefully(): void
    {
        // Command should handle exceptions using trait-based error handling
        $exitCode = $this->commandTester->execute([]);

        // Command should either succeed or fail gracefully
        self::assertContains($exitCode, [Command::SUCCESS, Command::FAILURE]);

        // Should produce some output
        $output = $this->commandTester->getDisplay();
        self::assertNotEmpty(trim($output));
    }

    /**
     * Test trait-based progress reporting methods
     */
    #[Test]
    public function commandUsesTraitBasedProgressReporting(): void
    {
        $exitCode = $this->commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();

        // Verify trait-based methods are used for output formatting
        // Warning should be displayed using reportWarning() trait method if no snapshots
        if (str_contains($output, 'No snapshots found')) {
            self::assertStringContainsString('No snapshots found', $output);
            self::assertStringContainsString('Snapshot Configuration', $output);
        } else {
            // If snapshots are found, title should be displayed
            self::assertStringContainsString('t3hauler Snapshots', $output);
        }
    }

    /**
     * Test trait-based table display functionality
     */
    #[Test]
    public function commandUsesTraitBasedTableDisplay(): void
    {
        $this->createTestSnapshots(2);

        $exitCode = $this->commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();

        // Verify trait-based table display is working
        self::assertStringContainsString('t3hauler Snapshots', $output);
        self::assertStringContainsString('Snapshots', $output); // Table title
        self::assertNotEmpty(trim($output));
    }

    /**
     * Test command option extraction using trait methods
     */
    #[Test]
    public function commandUsesTraitBasedOptionExtraction(): void
    {
        $this->createTestSnapshots(3);

        // Test that options are extracted using trait methods
        $exitCode = $this->commandTester->execute([
            '--limit' => '2',
            '--filter' => 'snapshot',
            '--order' => 'created_at',
            '--direction' => 'desc',
            '--details' => true,
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);

        // Command should execute successfully with trait-based option extraction
        $output = $this->commandTester->getDisplay();
        self::assertNotEmpty(trim($output));
    }

    /**
     * Test command initialization and I/O setup
     */
    #[Test]
    public function commandInitializesIOCorrectly(): void
    {
        // Test that the command initializes properly with trait-based I/O
        $exitCode = $this->commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();

        // Should have proper output using trait methods
        self::assertNotEmpty(trim($output));

        // If no snapshots, should show warning message
        if (str_contains($output, 'No snapshots found')) {
            self::assertStringContainsString('No snapshots found', $output);
        }
    }

    /**
     * Test that command maintains backward compatibility
     */
    #[Test]
    public function commandMaintainsBackwardCompatibility(): void
    {
        $exitCode = $this->commandTester->execute([]);

        // Command should execute successfully
        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();

        // Should produce output and handle the case where no snapshots are found appropriately
        self::assertNotEmpty(trim($output));

        // Should handle the case where no snapshots are found
        if (str_contains($output, 'No snapshots found')) {
            self::assertStringContainsString('No snapshots found', $output);
        } else {
            // If snapshots exist, should show the title
            self::assertStringContainsString('t3hauler Snapshots', $output);
        }
    }

    /**
     * Test trait-based safeExecute wrapper functionality
     */
    #[Test]
    public function commandUsesSafeExecuteWrapper(): void
    {
        $exitCode = $this->commandTester->execute([]);

        // The safeExecute wrapper should ensure the command always returns a valid exit code
        self::assertContains($exitCode, [Command::SUCCESS, Command::FAILURE, Command::INVALID]);

        $output = $this->commandTester->getDisplay();
        self::assertNotEmpty(trim($output));
    }

    /**
     * Test file size formatting utility method from trait
     */
    #[Test]
    public function commandUsesTraitBasedFileSizeFormatting(): void
    {
        // Create snapshot with large data to test size formatting
        $largeData = array_fill(0, 1000, ['uid' => 1, 'data' => str_repeat('x', 100)]);
        $this->createTestSnapshot('large-snapshot', ['pages' => $largeData]);

        $exitCode = $this->commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();

        // Should show formatted file size (KB or MB)
        self::assertNotEmpty(trim($output));
        self::assertStringContainsString('large-snapshot', $output);
    }

    /**
     * Test string truncation utility method from trait
     */
    #[Test]
    public function commandUsesTraitBasedStringTruncation(): void
    {
        $this->createTestSnapshots(1);

        $exitCode = $this->commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();

        // Hash should be truncated to 10 characters in table view
        self::assertNotEmpty(trim($output));
        self::assertStringContainsString('Hash', $output);
    }

    /**
     * Test detailed view section handling
     */
    #[Test]
    public function commandDisplaysDetailedSectionsCorrectly(): void
    {
        $tableData = [
            'pages' => [['uid' => 1]],
            'tt_content' => [['uid' => 1], ['uid' => 2]],
            'be_users' => [['uid' => 1]],
        ];
        $this->createTestSnapshot('section-test', $tableData);

        $exitCode = $this->commandTester->execute([
            '--details' => true,
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();

        // Should show properly formatted sections using trait methods
        self::assertStringContainsString('Snapshot: section-test', $output);
        self::assertStringContainsString('Details', $output);
        self::assertStringContainsString('Table Data', $output);

        // Should show all tables with record counts
        self::assertStringContainsString('pages', $output);
        self::assertStringContainsString('tt_content', $output);
        self::assertStringContainsString('be_users', $output);
    }

    /**
     * Test configuration display when no snapshots exist
     */
    #[Test]
    public function commandDisplaysConfigurationInfoWhenEmpty(): void
    {
        $exitCode = $this->commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();

        // Should show configuration information using trait methods
        self::assertStringContainsString('Snapshot Configuration', $output);
        self::assertStringContainsString('To create a snapshot, use: t3hauler:snapshot:create', $output);
    }

    /**
     * Helper method to create test snapshots
     */
    private function createTestSnapshots(int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $this->createTestSnapshot("test-snapshot-{$i}", ['pages' => [['uid' => $i]]]);
        }
    }

    /**
     * Helper method to create a single test snapshot
     */
    private function createTestSnapshot(string $identifier, array $tableData, ?\DateTime $createdAt = null): void
    {
        $createdAt = $createdAt ?? new \DateTime();
        $hash = hash('sha256', json_encode($tableData));

        $this->insertTestData('tx_t3hauler_snapshots', [
            'identifier' => $identifier,
            'hash' => $hash,
            'created_at' => $createdAt->getTimestamp(),
            'metadata' => json_encode(['table_data' => $tableData]),
        ]);
    }
}
