<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Tests\Functional\Command;

use Cpsit\T3hauler\Command\ApplyMigrationCommand;
use Cpsit\T3hauler\Configuration\T3HaulerConfiguration;
use Cpsit\T3hauler\Domain\Repository\MigrationFileRepository;
use Cpsit\T3hauler\Domain\Repository\MigrationRepository;
use Cpsit\T3hauler\Service\ChangeDetectionService;
use Cpsit\T3hauler\Service\ImportService;
use Cpsit\T3hauler\Tests\Functional\TestingUtilities;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Functional tests for ApplyMigrationCommand with filesystem-based workflow
 */
final class ApplyMigrationCommandTest extends FunctionalTestCase
{
    use TestingUtilities;

    private ApplyMigrationCommand $command;
    private CommandTester $commandTester;
    private MigrationRepository $migrationRepository;
    private string $testMigrationPath;

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

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpT3HaulerTests();

        $container = $this->getContainer();
        $this->migrationRepository = $container->get(MigrationRepository::class);
        $importService = $container->get(ImportService::class);
        $changeDetectionService = $container->get(ChangeDetectionService::class);

        // Create test migration path
        $this->testMigrationPath = Environment::getVarPath() . '/tests/t3hauler/migrations';
        if (!is_dir($this->testMigrationPath)) {
            mkdir($this->testMigrationPath, 0755, true);
        }

        // Create configuration with test migration paths
        $configuration = new T3HaulerConfiguration(
            [], // configuration paths
            [$this->testMigrationPath] // migration paths
        );

        // Create MigrationFileRepository with configuration
        $migrationFileRepository = new MigrationFileRepository($configuration);

        $this->command = new ApplyMigrationCommand(
            $configuration,
            $this->migrationRepository,
            $migrationFileRepository,
            $importService,
            $changeDetectionService
        );
        $this->commandTester = new CommandTester($this->command);
    }

    protected function tearDown(): void
    {
        // Clean up test migration files
        if (is_dir($this->testMigrationPath)) {
            $files = glob($this->testMigrationPath . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->testMigrationPath);
        }

        $this->tearDownT3HaulerTests();
        parent::tearDown();
    }

    /**
     * Helper method to create properly formatted migration data
     */
    private function createMigrationData(string $migrationId, string $description, string $author, array $records = []): array
    {
        return [
            'metadata' => [
                'format_version' => '1.0',
                'migration_id' => $migrationId,
                'description' => $description,
                'author' => $author,
                'created_at' => time(),
                'source_hash' => 'test_hash_' . substr(md5($migrationId), 0, 8),
                'name' => ucfirst(str_replace('_', ' ', $description)),
            ],
            'records' => $records,
        ];
    }

    #[Test]
    public function commandFailsWhenMigrationFileNotFound(): void
    {
        $exitCode = $this->commandTester->execute([
            'migration' => 'nonexistent-migration',
        ]);

        self::assertSame(Command::FAILURE, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Migration file \'nonexistent-migration\' not found', $output);
    }

    #[Test]
    public function commandAppliesNewMigrationSuccessfully(): void
    {
        // Create a test migration file
        $migrationId = '2024-01-01_12:00:00_12345678';
        $migrationData = $this->createMigrationData(
            $migrationId,
            'Test migration',
            'Test Author',
            [
                'pages' => [
                    99 => [
                        'uid' => 99,
                        'pid' => 0,
                        'title' => 'Test Page from Migration',
                        'doktype' => 1,
                        'hidden' => 0,
                    ],
                ],
            ]
        );

        $migrationFile = $this->testMigrationPath . '/' . $migrationId . '.json';
        file_put_contents($migrationFile, json_encode($migrationData, JSON_PRETTY_PRINT));

        // Execute command
        $exitCode = $this->commandTester->execute([
            'migration' => $migrationId,
        ]);

        $output = $this->commandTester->getDisplay();

        self::assertSame(Command::SUCCESS, $exitCode);

        self::assertStringContainsString('Migration applied successfully', $output);
        self::assertStringContainsString('Test migration', $output);
        self::assertStringContainsString('Test Author', $output);
        self::assertStringContainsString('Local status: not applied', $output);

        // Verify migration was marked as applied in local repository
        $migration = $this->migrationRepository->findByMigrationId($migrationId);
        self::assertNotNull($migration);
        self::assertSame('applied', $migration->getStatus());
    }

    #[Test]
    public function commandSkipsAlreadyAppliedMigration(): void
    {
        $migrationId = '2024-01-01_12:00:01_abcdef12';
        $migrationData = $this->createMigrationData(
            $migrationId,
            'Already applied migration',
            'Test Author',
            ['pages' => []]
        );

        $migrationFile = $this->testMigrationPath . '/' . $migrationId . '.json';
        file_put_contents($migrationFile, json_encode($migrationData, JSON_PRETTY_PRINT));

        // Pre-apply the migration (simulate it was already applied)
        $this->insertTestData('tx_t3hauler_migrations', [
            'migration_id' => $migrationId,
            'name' => 'Already Applied Migration',
            'description' => 'Already applied migration',
            'author' => 'Test Author',
            'created_at' => time(),
            'status' => 'applied',
            'source_hash' => 'test_hash_456',
            'data_file' => $migrationId . '.json',
            'metadata' => '{}',
        ]);

        // Execute command
        $exitCode = $this->commandTester->execute([
            'migration' => $migrationId,
        ]);

        self::assertSame(Command::FAILURE, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Migration has already been applied in this instance', $output);
        self::assertStringContainsString('Local status: applied', $output);
    }

    #[Test]
    public function commandRetriesFailedMigrationWithForce(): void
    {
        $migrationId = '2024-01-01_12:00:02_fedcba98';
        $migrationData = $this->createMigrationData(
            $migrationId,
            'Failed migration retry',
            'Test Author',
            [
                'pages' => [
                    100 => [
                        'uid' => 100,
                        'pid' => 0,
                        'title' => 'Test Page from Retry',
                        'doktype' => 1,
                        'hidden' => 0,
                    ],
                ],
            ]
        );

        $migrationFile = $this->testMigrationPath . '/' . $migrationId . '.json';
        file_put_contents($migrationFile, json_encode($migrationData, JSON_PRETTY_PRINT));

        // Pre-mark the migration as failed
        $this->insertTestData('tx_t3hauler_migrations', [
            'migration_id' => $migrationId,
            'name' => 'Failed Migration',
            'description' => 'Failed migration retry',
            'author' => 'Test Author',
            'created_at' => time(),
            'status' => 'failed',
            'source_hash' => 'test_hash_789',
            'data_file' => $migrationId . '.json',
            'metadata' => '{}',
        ]);

        // Execute command without force - should fail
        $exitCode = $this->commandTester->execute([
            'migration' => $migrationId,
        ]);

        self::assertSame(Command::FAILURE, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Migration failed previously in this instance', $output);
        self::assertStringContainsString('Use --force to retry', $output);

        // Execute command with force - should succeed
        $exitCode = $this->commandTester->execute([
            'migration' => $migrationId,
            '--force' => true,
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Force mode enabled - retrying migration', $output);
        self::assertStringContainsString('Migration applied successfully', $output);
    }

    #[Test]
    public function commandShowsDryRunPreview(): void
    {
        $migrationId = '2024-01-01_12:00:03_13579bdf';
        $migrationData = $this->createMigrationData(
            $migrationId,
            'Dry run test migration',
            'Test Author',
            [
                'pages' => [
                    101 => [
                        'uid' => 101,
                        'pid' => 0,
                        'title' => 'Dry Run Test Page',
                        'doktype' => 1,
                        'hidden' => 0,
                    ],
                ],
                'tt_content' => [
                    201 => [
                        'uid' => 201,
                        'pid' => 101,
                        'CType' => 'text',
                        'header' => 'Dry Run Test Content',
                    ],
                ],
            ]
        );

        $migrationFile = $this->testMigrationPath . '/' . $migrationId . '.json';
        file_put_contents($migrationFile, json_encode($migrationData, JSON_PRETTY_PRINT));

        // Execute command with dry run
        $exitCode = $this->commandTester->execute([
            'migration' => $migrationId,
            '--dry-run' => true,
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('DRY RUN MODE - No changes will be applied', $output);
        self::assertStringContainsString('Migration preview completed', $output);
        self::assertStringContainsString('Would import', $output);

        // Verify no migration record was created in repository
        $migration = $this->migrationRepository->findByMigrationId($migrationId);
        self::assertNull($migration);
    }

    #[Test]
    public function commandDisplaysMigrationMetadata(): void
    {
        $migrationId = '2024-01-01_12:00:04_2468ace0';
        $testTime = time();
        $migrationData = $this->createMigrationData(
            $migrationId,
            'Metadata display test',
            'Metadata Author',
            ['pages' => []]
        );
        // Override the created_at time for this specific test
        $migrationData['metadata']['created_at'] = $testTime;

        $migrationFile = $this->testMigrationPath . '/' . $migrationId . '.json';
        file_put_contents($migrationFile, json_encode($migrationData, JSON_PRETTY_PRINT));

        // Execute command
        $exitCode = $this->commandTester->execute([
            'migration' => $migrationId,
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Migration: ' . $migrationId, $output);
        self::assertStringContainsString('Description: Metadata display test', $output);
        self::assertStringContainsString('Author: Metadata Author', $output);
        self::assertStringContainsString('Created: ' . date('Y-m-d H:i:s', $testTime), $output);
        self::assertStringContainsString('Local status: not applied', $output);
    }

    #[Test]
    public function commandHandlesMissingMetadata(): void
    {
        $migrationId = '2024-01-01_12:00:05_fdb97531';
        $migrationData = [
            'records' => [
                'pages' => [],
            ],
        ];

        $migrationFile = $this->testMigrationPath . '/' . $migrationId . '.json';
        file_put_contents($migrationFile, json_encode($migrationData, JSON_PRETTY_PRINT));

        // Execute command
        $exitCode = $this->commandTester->execute([
            'migration' => $migrationId,
        ]);

        self::assertSame(Command::FAILURE, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Migration: ' . $migrationId, $output);
        self::assertStringContainsString('Migration application failed', $output);
        self::assertStringContainsString('Invalid export structure', $output);
    }

    #[Test]
    public function commandCreatesPostMigrationSnapshot(): void
    {
        $migrationId = '2024-01-01_12:00:06_86420eca';
        $migrationData = $this->createMigrationData(
            $migrationId,
            'Snapshot test migration',
            'Test Author',
            ['pages' => []]
        );

        $migrationFile = $this->testMigrationPath . '/' . $migrationId . '.json';
        file_put_contents($migrationFile, json_encode($migrationData, JSON_PRETTY_PRINT));

        // Execute command
        $exitCode = $this->commandTester->execute([
            'migration' => $migrationId,
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Creating post-migration snapshot', $output);
        self::assertStringContainsString('Failed to create post-migration snapshot', $output);
    }

}
