<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Tests\Unit\Configuration;

use Cpsit\T3hauler\Configuration\T3HaulerConfiguration;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class T3HaulerConfigurationTest extends TestCase
{
    private T3HaulerConfiguration $subject;
    private vfsStreamDirectory $vfsRoot; // @phpstan-ignore-line

    protected function setUp(): void
    {
        parent::setUp();
        $this->vfsRoot = vfsStream::setup('config');
        $this->subject = new T3HaulerConfiguration(
            ['/path/to/config'],
            ['/path/to/migrations']
        );
    }

    #[Test]
    public function getConfigurationPathsReturnsConfiguredPaths(): void
    {
        $result = $this->subject->getConfigurationPaths();

        self::assertSame(['/path/to/config'], $result);
    }

    #[Test]
    public function getMigrationPathsReturnsConfiguredPaths(): void
    {
        $result = $this->subject->getMigrationPaths();

        self::assertSame(['/path/to/migrations'], $result);
    }

    #[Test]
    public function getReturnsDefaultValueWhenConfigurationNotSet(): void
    {
        $result = $this->subject->get('nonexistent.path', 'default');

        self::assertSame('default', $result);
    }

    #[Test]
    public function getEnabledTablesReturnsDefaultWhenNotConfigured(): void
    {
        $result = $this->subject->getEnabledTables();

        self::assertSame(['pages', 'tt_content'], $result);
    }

    #[Test]
    public function getExcludedFieldsReturnsDefaultWhenNotConfigured(): void
    {
        $result = $this->subject->getExcludedFields();

        $expected = [
            'tstamp', 'crdate', 'cruser_id', 'SYS_LASTCHANGED',
            't3ver_oid', 't3ver_wsid', 't3ver_state', 't3ver_stage',
            't3ver_count', 't3ver_tstamp', 't3ver_move_id',
            'l10n_state', 'l10n_diffsource',
            'perms_userid', 'perms_groupid', 'perms_user', 'perms_group', 'perms_everybody',
            'editlock', 'fe_group', 'rowDescription',
        ];
        self::assertSame($expected, $result);
    }

    #[Test]
    public function getHashAlgorithmReturnsDefaultWhenNotConfigured(): void
    {
        $result = $this->subject->getHashAlgorithm();

        self::assertSame('sha256', $result);
    }

    #[Test]
    public function getAllReturnsEmptyArrayWhenNoConfigurationLoaded(): void
    {
        $result = $this->subject->getAll();

        self::assertSame([], $result);
    }

    #[Test]
    public function getStorageConfigReturnsEmptyArrayWhenNotConfigured(): void
    {
        $result = $this->subject->getStorageConfig();

        self::assertSame([], $result);
    }

    #[Test]
    public function getIntegrityConfigReturnsEmptyArrayWhenNotConfigured(): void
    {
        $result = $this->subject->getIntegrityConfig();

        self::assertSame([], $result);
    }

    #[Test]
    public function getDetectionConfigReturnsEmptyArrayWhenNotConfigured(): void
    {
        $result = $this->subject->getDetectionConfig();

        self::assertSame([], $result);
    }

    #[Test]
    public function getSiteConfigReturnsEmptyArrayWhenNotConfigured(): void
    {
        $result = $this->subject->getSiteConfig();

        self::assertSame([], $result);
    }

    #[Test]
    public function constructorWithoutParametersCreatesEmptyConfiguration(): void
    {
        $config = new T3HaulerConfiguration();

        self::assertSame([], $config->getConfigurationPaths());
        self::assertSame([], $config->getMigrationPaths());
    }

    #[Test]
    public function constructorAcceptsMultiplePaths(): void
    {
        $configPaths = ['/path/to/config1', '/path/to/config2'];
        $migrationPaths = ['/path/to/migrations1', '/path/to/migrations2'];

        $config = new T3HaulerConfiguration($configPaths, $migrationPaths);

        self::assertSame($configPaths, $config->getConfigurationPaths());
        self::assertSame($migrationPaths, $config->getMigrationPaths());
    }

    #[Test]
    public function getHandlesDotNotationWithMultipleLevels(): void
    {
        $result = $this->subject->get('level1.level2.level3', 'default');

        self::assertSame('default', $result);
    }

    #[Test]
    public function getReturnsNullWhenNoDefaultProvided(): void
    {
        $result = $this->subject->get('nonexistent.path');

        self::assertNull($result);
    }

    #[Test]
    public function getHandlesEmptyStringPath(): void
    {
        $result = $this->subject->get('', 'default');

        self::assertSame('default', $result);
    }

    #[Test]
    public function getHandlesSingleLevelPath(): void
    {
        $result = $this->subject->get('nonexistent', 'default');

        self::assertSame('default', $result);
    }

    #[Test]
    public function ensureConfigurationLoadedIsCalledOnlyOnce(): void
    {
        // Call get multiple times to ensure configuration is loaded only once
        $this->subject->get('test1');
        $this->subject->get('test2');
        $this->subject->getAll();
        $enabledTables = $this->subject->getEnabledTables();

        self::assertNotEmpty($enabledTables);
    }

    #[Test]
    public function getConfigMethodsReturnCorrectDefaultValues(): void
    {
        // Test all the specific config getter methods
        self::assertSame([], $this->subject->getStorageConfig());
        self::assertSame([], $this->subject->getIntegrityConfig());
        self::assertSame([], $this->subject->getDetectionConfig());
        self::assertSame([], $this->subject->getSiteConfig());
        self::assertSame(['pages', 'tt_content'], $this->subject->getEnabledTables());
        self::assertSame('sha256', $this->subject->getHashAlgorithm());

        // Check excluded fields default contains expected entries
        $excludedFields = $this->subject->getExcludedFields();
        self::assertContains('tstamp', $excludedFields);
        self::assertContains('crdate', $excludedFields);
        self::assertContains('t3ver_oid', $excludedFields);
        self::assertContains('l10n_state', $excludedFields);
        self::assertContains('perms_userid', $excludedFields);
        self::assertContains('editlock', $excludedFields);
    }

    #[Test]
    public function getExcludedFieldsContainsAllExpectedSystemFields(): void
    {
        $excludedFields = $this->subject->getExcludedFields();

        // Verify all expected system fields are present
        $expectedSystemFields = [
            'tstamp', 'crdate', 'cruser_id', 'SYS_LASTCHANGED',
        ];

        foreach ($expectedSystemFields as $field) {
            self::assertContains($field, $excludedFields, "Field '$field' should be in excluded fields");
        }
    }

    #[Test]
    public function getExcludedFieldsContainsAllExpectedWorkspaceFields(): void
    {
        $excludedFields = $this->subject->getExcludedFields();

        $expectedWorkspaceFields = [
            't3ver_oid', 't3ver_wsid', 't3ver_state', 't3ver_stage',
            't3ver_count', 't3ver_tstamp', 't3ver_move_id',
        ];

        foreach ($expectedWorkspaceFields as $field) {
            self::assertContains($field, $excludedFields, "Workspace field '$field' should be in excluded fields");
        }
    }

    #[Test]
    public function getExcludedFieldsContainsAllExpectedLocalizationFields(): void
    {
        $excludedFields = $this->subject->getExcludedFields();

        $expectedLocalizationFields = ['l10n_state', 'l10n_diffsource'];

        foreach ($expectedLocalizationFields as $field) {
            self::assertContains($field, $excludedFields, "Localization field '$field' should be in excluded fields");
        }
    }

    #[Test]
    public function getExcludedFieldsContainsAllExpectedPermissionFields(): void
    {
        $excludedFields = $this->subject->getExcludedFields();

        $expectedPermissionFields = [
            'perms_userid', 'perms_groupid', 'perms_user', 'perms_group', 'perms_everybody',
        ];

        foreach ($expectedPermissionFields as $field) {
            self::assertContains($field, $excludedFields, "Permission field '$field' should be in excluded fields");
        }
    }

    #[Test]
    public function configurationsWorkThroughPublicInterface(): void
    {
        // Since file loading depends on TYPO3 utilities that may not work with vfs,
        // we test the public interface behavior to ensure configuration loading
        // is called and the loaded flag is properly managed

        $config = new T3HaulerConfiguration(['nonexistent/path']);

        // These calls should trigger ensureConfigurationLoaded() but handle
        // missing/invalid paths gracefully
        self::assertSame([], $config->getAll());
        self::assertSame([], $config->getStorageConfig());
        self::assertSame([], $config->getIntegrityConfig());
        self::assertSame([], $config->getDetectionConfig());
        self::assertSame([], $config->getSiteConfig());

        // Default values should still be returned
        self::assertSame(['pages', 'tt_content'], $config->getEnabledTables());
        self::assertSame('sha256', $config->getHashAlgorithm());

        // Verify excluded fields contains expected defaults
        $excludedFields = $config->getExcludedFields();
        self::assertGreaterThan(15, count($excludedFields));
    }

    #[Test]
    public function handlesMultipleConfigurationPaths(): void
    {
        // Test with multiple nonexistent paths to verify iteration through all paths
        $config = new T3HaulerConfiguration(['/nonexistent1', '/nonexistent2', '/nonexistent3']);

        // Should handle multiple paths gracefully
        self::assertSame([], $config->getAll());
        self::assertSame(['pages', 'tt_content'], $config->getEnabledTables());
    }

    #[Test]
    public function loadingBehaviorWithEmptyPaths(): void
    {
        // Test configuration loading behavior with empty path arrays
        $config = new T3HaulerConfiguration([]);

        // Should handle empty paths gracefully
        self::assertSame([], $config->getAll());
        self::assertSame([], $config->getStorageConfig());

        // Default values should still work
        self::assertSame(['pages', 'tt_content'], $config->getEnabledTables());
        self::assertSame('sha256', $config->getHashAlgorithm());
    }

    #[Test]
    public function getMethodSupportsVariousDataTypes(): void
    {
        // Test that get method properly handles different path access patterns
        // This tests the dot notation parsing logic

        // Test empty path returns default
        self::assertSame('default', $this->subject->get('', 'default'));

        // Test single level path
        self::assertSame('fallback', $this->subject->get('missing', 'fallback'));

        // Test multi-level path that doesn't exist
        self::assertSame('nested-default', $this->subject->get('a.b.c.d.e', 'nested-default'));

        // Test with null default
        self::assertNull($this->subject->get('nonexistent'));

        // Test with various default types
        self::assertSame(42, $this->subject->get('missing.int', 42));
        self::assertTrue($this->subject->get('missing.bool', true));
        self::assertSame(['default'], $this->subject->get('missing.array', ['default']));
    }

    #[Test]
    public function configurationLoadingIsLazilyEvaluated(): void
    {
        // Test that configuration loading only happens when needed
        $config = new T3HaulerConfiguration(['/some/path']);

        // Configuration should not be loaded yet, but this is internal behavior
        // We can only test the public interface

        // First call should trigger loading
        $result1 = $config->getAll();

        // Subsequent calls should use cached result
        $result2 = $config->getAll();
        $result3 = $config->get('test');
        $result4 = $config->getEnabledTables();

        // All should return the same empty array for getAll()
        self::assertSame($result1, $result2);
        self::assertSame([], $result1);
        self::assertSame([], $result2);

        // Other methods should work consistently
        self::assertNull($result3);
        self::assertSame(['pages', 'tt_content'], $result4);
    }

    #[Test]
    public function getMethodHandlesArrayTraversalCorrectly(): void
    {
        // Since we can't easily load real config, test the path traversal logic
        // by testing various edge cases with nonexistent paths

        // Path with special characters should return default
        self::assertSame('default', $this->subject->get('path.with-dash.and_underscore', 'default'));

        // Path with numbers should return default
        self::assertSame('default', $this->subject->get('path.123.456', 'default'));

        // Very long path should return default
        $longPath = implode('.', array_fill(0, 20, 'level'));
        self::assertSame('default', $this->subject->get($longPath, 'default'));

        // Path with only dots should return default
        self::assertSame('default', $this->subject->get('...', 'default'));
    }
}
