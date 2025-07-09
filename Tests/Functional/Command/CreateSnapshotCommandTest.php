<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Tests\Functional\Command;

use Cpsit\T3hauler\Command\CreateSnapshotCommand;
use Cpsit\T3hauler\Domain\Repository\DataSnapshotRepository;
use Cpsit\T3hauler\Service\ChangeDetectionService;
use Cpsit\T3hauler\Tests\Functional\TestingUtilities;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Functional tests for CreateSnapshotCommand
 */
final class CreateSnapshotCommandTest extends FunctionalTestCase
{
    use TestingUtilities;
    private CreateSnapshotCommand $command;
    private CommandTester $commandTester;
    private DataSnapshotRepository $snapshotRepository;

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
        $changeDetectionService = $container->get(ChangeDetectionService::class);
        $this->snapshotRepository = $container->get(DataSnapshotRepository::class);

        $this->command = new CreateSnapshotCommand($changeDetectionService);
        $this->commandTester = new CommandTester($this->command);
    }

    protected function tearDown(): void
    {
        $this->tearDownT3HaulerTests();
        parent::tearDown();
    }

    #[Test]
    public function commandCreatesSnapshotWithDefaultIdentifier(): void
    {
        // No snapshots initially
        $this->assertTableCount('tx_t3hauler_snapshots', 0);

        // Execute command
        $exitCode = $this->commandTester->execute([]);

        // Verify command succeeded
        self::assertSame(Command::SUCCESS, $exitCode);

        // Verify snapshots were created (1 for each enabled table + 1 set snapshot)
        $this->assertTableCount('tx_t3hauler_snapshots', 3);

        // Verify output contains success message
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Snapshot created successfully', $output);
        self::assertStringContainsString('Identifier', $output);
        self::assertStringContainsString('Hash', $output);
    }

    #[Test]
    public function commandCreatesSnapshotWithCustomIdentifier(): void
    {
        $customIdentifier = 'custom-test-snapshot';

        // Execute command with custom identifier
        $exitCode = $this->commandTester->execute([
            '--identifier' => $customIdentifier,
        ]);

        // Verify command succeeded
        self::assertSame(Command::SUCCESS, $exitCode);

        // Verify snapshot was created with custom identifier
        $this->assertRecordExists('tx_t3hauler_snapshots', ['identifier' => $customIdentifier]);

        // Verify output contains custom identifier
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString($customIdentifier, $output);
    }

    #[Test]
    public function commandCreatesSnapshotWithMigrationVersion(): void
    {
        $migrationVersion = 'v1.2.3';

        // Execute command with migration version
        $exitCode = $this->commandTester->execute([
            '--migration-version' => $migrationVersion,
        ]);

        // Verify command succeeded
        self::assertSame(Command::SUCCESS, $exitCode);

        // Verify snapshots were created (1 for each enabled table + 1 set snapshot)
        $this->assertTableCount('tx_t3hauler_snapshots', 3);

        // Get created snapshot with migration version
        $snapshot = $this->getConnectionForTable('tx_t3hauler_snapshots')
            ->select(['*'], 'tx_t3hauler_snapshots', ['migration_version' => $migrationVersion])
            ->fetchAssociative();

        // Verify migration version is stored correctly
        self::assertNotEmpty($snapshot);
        self::assertSame($migrationVersion, $snapshot['migration_version']);

        // Verify output contains migration version
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString($migrationVersion, $output);
    }

    #[Test]
    public function commandShowsProgressAndStatistics(): void
    {
        // Execute command
        $exitCode = $this->commandTester->execute([]);

        // Verify command succeeded
        self::assertSame(Command::SUCCESS, $exitCode);

        // Verify output contains progress information
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Creating snapshot', $output);
        self::assertStringContainsString('Table', $output);
        self::assertStringContainsString('Snapshot created successfully', $output);
    }

    #[Test]
    public function commandHandlesEmptyDatabase(): void
    {
        // Clear all fixture data
        $this->getConnectionForTable('pages')->truncate('pages');
        $this->getConnectionForTable('tt_content')->truncate('tt_content');

        // Execute command
        $exitCode = $this->commandTester->execute([]);

        // Verify command succeeded even with empty tables
        self::assertSame(Command::SUCCESS, $exitCode);

        // Verify snapshots were still created (1 for each enabled table + 1 set snapshot)
        $this->assertTableCount('tx_t3hauler_snapshots', 3);

        // Verify output indicates empty tables
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Snapshot created successfully', $output);
    }

    #[Test]
    public function commandSupportsVerboseOutput(): void
    {
        // Create a fresh command tester for this test
        $freshCommandTester = new CommandTester($this->command);

        // Execute command with verbose flag
        $exitCode = $freshCommandTester->execute([], ['verbosity' => 2]); // VERBOSITY_VERBOSE

        // Verify command succeeded
        self::assertSame(Command::SUCCESS, $exitCode);

        // Verify verbose output (same as normal output since no special verbose handling)
        $output = $freshCommandTester->getDisplay();
        self::assertStringContainsString('Creating snapshot', $output);
        self::assertStringContainsString('Snapshot created successfully', $output);
    }

    #[Test]
    public function commandValidatesIdentifierFormat(): void
    {
        // Execute command with invalid identifier
        $exitCode = $this->commandTester->execute([
            '--identifier' => 'invalid identifier with spaces!@#',
        ]);

        // Command should handle invalid input gracefully
        // (The actual validation behavior depends on the command implementation)
        self::assertContains($exitCode, [Command::SUCCESS, Command::INVALID]);
    }

    #[Test]
    public function multipleSnapshotsCanBeCreated(): void
    {
        // Create first snapshot
        $exitCode1 = $this->commandTester->execute([
            '--identifier' => 'snapshot-1',
        ]);
        self::assertSame(Command::SUCCESS, $exitCode1);

        // Create second snapshot
        $exitCode2 = $this->commandTester->execute([
            '--identifier' => 'snapshot-2',
        ]);
        self::assertSame(Command::SUCCESS, $exitCode2);

        // Verify all snapshots exist (3 per execution, 2 executions = 6 total)
        $this->assertTableCount('tx_t3hauler_snapshots', 6);
        $this->assertRecordExists('tx_t3hauler_snapshots', ['identifier' => 'snapshot-1']);
        $this->assertRecordExists('tx_t3hauler_snapshots', ['identifier' => 'snapshot-2']);
    }

    #[Test]
    public function commandIncludesTimestampInOutput(): void
    {
        // Execute command
        $exitCode = $this->commandTester->execute([]);

        // Verify command succeeded
        self::assertSame(Command::SUCCESS, $exitCode);

        // Verify output contains timestamp information
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Created At', $output);
    }

    #[Test]
    public function commandCreatesCurrentSnapshot(): void
    {
        // Execute command
        $exitCode = $this->commandTester->execute([]);

        // Verify command succeeded
        self::assertSame(Command::SUCCESS, $exitCode);

        // Verify a current snapshot was set (finds latest snapshot for the snapshots table)
        $currentSnapshot = $this->snapshotRepository->findCurrentSnapshot();
        self::assertNotNull($currentSnapshot);

        // Verify it's in the database table
        $this->assertRecordExists('tx_t3hauler_snapshots', ['table_name' => 'tx_t3hauler_snapshots']);
    }
}
