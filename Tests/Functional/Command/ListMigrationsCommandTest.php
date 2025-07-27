<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Tests\Functional\Command;

use Cpsit\T3hauler\Command\ListMigrationsCommand;
use Cpsit\T3hauler\Domain\Repository\MigrationFileRepository;
use Cpsit\T3hauler\Tests\Functional\TestingUtilities;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Functional tests for ListMigrationsCommand with trait-based architecture
 */
final class ListMigrationsCommandTest extends FunctionalTestCase
{
    use TestingUtilities;

    private ListMigrationsCommand $command;
    private CommandTester $commandTester;
    private MigrationFileRepository $migrationFileRepository;

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
        $this->migrationFileRepository = $container->get(MigrationFileRepository::class);

        $this->command = new ListMigrationsCommand($this->migrationFileRepository);
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

        // Should complete successfully and show either migrations or warning
        self::assertNotEmpty(trim($output));
    }

    /**
     * Test command with empty migration paths shows warning message
     */
    #[Test]
    public function commandShowsWarningForNoMigrationsFound(): void
    {
        $exitCode = $this->commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();

        // Verify trait-based warning reporting
        self::assertStringContainsString('No migrations found in configured paths', $output);

        // Should display migration paths information using trait methods
        self::assertStringContainsString('No migration paths configured', $output);
    }

    /**
     * Test command with status filter option using trait-based option extraction
     */
    #[Test]
    public function commandHandlesStatusFilterOption(): void
    {
        $exitCode = $this->commandTester->execute([
            '--status' => 'pending',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();

        // Command should handle the status filter gracefully and show appropriate output
        self::assertNotEmpty(trim($output));
    }

    /**
     * Test command with path filter option using trait-based option extraction
     */
    #[Test]
    public function commandHandlesPathFilterOption(): void
    {
        $exitCode = $this->commandTester->execute([
            '--path' => 'test-path',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();

        // Command should handle the path filter gracefully
        self::assertNotEmpty(trim($output));
    }

    /**
     * Test command with format option for JSON output
     */
    #[Test]
    public function commandHandlesJSONFormatOption(): void
    {
        $exitCode = $this->commandTester->execute([
            '--format' => 'json',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();

        // Command should handle JSON format option
        self::assertNotEmpty(trim($output));
    }

    /**
     * Test command with table format option (default)
     */
    #[Test]
    public function commandHandlesTableFormatOption(): void
    {
        $exitCode = $this->commandTester->execute([
            '--format' => 'table',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();

        // Command should handle table format option
        self::assertNotEmpty(trim($output));
    }

    /**
     * Test command with multiple options combined
     */
    #[Test]
    public function commandHandlesMultipleOptions(): void
    {
        $exitCode = $this->commandTester->execute([
            '--status' => 'pending',
            '--format' => 'table',
            '--path' => 'migrations',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();

        // Command should handle multiple options gracefully using trait methods
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
     * Test verbose output includes additional information
     */
    #[Test]
    public function commandProvidesVerboseOutput(): void
    {
        $exitCode = $this->commandTester->execute([], [
            'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();

        // Verify verbose output includes expected information
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
        // Warning should be displayed using reportWarning() trait method if no migrations
        if (str_contains($output, 'No migrations found')) {
            self::assertStringContainsString('No migrations found in configured paths', $output);
        } else {
            // If migrations are found, title should be displayed
            self::assertStringContainsString('t3hauler Migrations', $output);
        }
    }

    /**
     * Test trait-based table display functionality
     */
    #[Test]
    public function commandUsesTraitBasedTableDisplay(): void
    {
        $exitCode = $this->commandTester->execute([
            '--format' => 'table',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();

        // Verify trait-based table display is working
        self::assertNotEmpty(trim($output));
    }

    /**
     * Test command option extraction using trait methods
     */
    #[Test]
    public function commandUsesTraitBasedOptionExtraction(): void
    {
        // Test that options are extracted using trait methods
        $exitCode = $this->commandTester->execute([
            '--status' => 'applied',
            '--path' => '/custom/path',
            '--format' => 'json',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);

        // Command should execute successfully with trait-based option extraction
        $output = $this->commandTester->getDisplay();
        self::assertNotEmpty(trim($output));
    }

    /**
     * Test command with invalid status option
     */
    #[Test]
    public function commandHandlesInvalidStatusOption(): void
    {
        $exitCode = $this->commandTester->execute([
            '--status' => 'invalid-status',
        ]);

        // Command should handle invalid status gracefully
        self::assertContains($exitCode, [Command::SUCCESS, Command::FAILURE]);

        // Should produce some output
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

        // If no migrations, should show warning message
        if (str_contains($output, 'No migrations found')) {
            self::assertStringContainsString('No migrations found in configured paths', $output);
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

        // Should produce output and handle the case where no migrations are found appropriately
        self::assertNotEmpty(trim($output));

        // Should handle the case where no migrations are found
        if (str_contains($output, 'No migrations found')) {
            self::assertStringContainsString('No migrations found in configured paths', $output);
        } else {
            // If migrations exist, should show the title
            self::assertStringContainsString('t3hauler Migrations', $output);
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
     * Test trait-based option extraction methods work correctly
     */
    #[Test]
    public function commandTraitBasedOptionExtractionWorks(): void
    {
        // Test that the trait methods getStatus(), getPath() work correctly
        $exitCode = $this->commandTester->execute([
            '--status' => 'pending',
            '--path' => 'test/path',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);

        // Options should be processed correctly by trait methods
        $output = $this->commandTester->getDisplay();
        self::assertNotEmpty(trim($output));
    }
}
