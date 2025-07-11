<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Configuration;

use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Configuration service for T3Hauler
 *
 * Loads and manages configuration from configurable paths
 */
class T3HaulerConfiguration
{
    private array $config = [];
    private bool $loaded = false;

    public function __construct(
        private readonly array $configurationPaths = [],
        private readonly array $migrationPaths = []
    ) {}

    /**
     * Get configuration value by path (dot notation)
     */
    public function get(string $path, mixed $default = null): mixed
    {
        $this->ensureConfigurationLoaded();

        $keys = explode('.', $path);
        $value = $this->config;

        foreach ($keys as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return $default;
            }
            $value = $value[$key];
        }

        return $value;
    }

    /**
     * Get all configuration
     */
    public function getAll(): array
    {
        $this->ensureConfigurationLoaded();
        return $this->config;
    }

    /**
     * Get configuration paths
     */
    public function getConfigurationPaths(): array
    {
        return $this->configurationPaths;
    }

    /**
     * Get migration paths
     */
    public function getMigrationPaths(): array
    {
        return $this->migrationPaths;
    }

    /**
     * Get storage configuration
     */
    public function getStorageConfig(): array
    {
        return $this->get('t3hauler.storage', []);
    }

    /**
     * Get integrity configuration
     */
    public function getIntegrityConfig(): array
    {
        return $this->get('t3hauler.integrity', []);
    }

    /**
     * Get detection configuration
     */
    public function getDetectionConfig(): array
    {
        return $this->get('t3hauler.detection', []);
    }

    /**
     * Get site configuration
     */
    public function getSiteConfig(): array
    {
        return $this->get('t3hauler.sites', []);
    }

    /**
     * Get enabled tables for change detection
     */
    public function getEnabledTables(): array
    {
        return $this->get('t3hauler.detection.enabledTables', ['pages', 'tt_content']);
    }

    /**
     * Get excluded fields for change detection
     */
    public function getExcludedFields(): array
    {
        return $this->get('t3hauler.detection.excludeFields', [
            // Standard TYPO3 system fields
            'tstamp',
            'crdate',
            'cruser_id',
            'SYS_LASTCHANGED',      // System last change timestamp

            // Workspace/Versioning fields
            't3ver_oid',            // Workspace original ID
            't3ver_wsid',           // Workspace ID
            't3ver_state',          // Workspace version state
            't3ver_stage',          // Workspace stage
            't3ver_count',          // Version count
            't3ver_tstamp',         // Version timestamp
            't3ver_move_id',        // Version move ID

            // Localization fields
            'l10n_state',           // Localization state
            'l10n_diffsource',      // Localization diff source

            // Permission fields
            'perms_userid',         // Permission user ID
            'perms_groupid',        // Permission group ID
            'perms_user',           // User permissions
            'perms_group',          // Group permissions
            'perms_everybody',      // Everyone permissions

            // Other potentially auto-updating fields
            'editlock',             // Edit lock status
            'fe_group',             // Frontend group (may be auto-calculated)
            'rowDescription',       // Row description (may be auto-generated)
        ]);
    }

    /**
     * Get hash algorithm
     */
    public function getHashAlgorithm(): string
    {
        return $this->get('t3hauler.integrity.hashAlgorithm', 'sha256');
    }

    /**
     * Load configuration from all configured paths
     */
    private function ensureConfigurationLoaded(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->config = [];

        foreach ($this->configurationPaths as $configPath) {
            $this->loadConfigurationFromPath($configPath);
        }

        $this->loaded = true;
    }

    /**
     * Load configuration from a specific path
     */
    private function loadConfigurationFromPath(string $configPath): void
    {
        $path = GeneralUtility::getFileAbsFileName($configPath);
        if (!GeneralUtility::isAllowedAbsPath($path)) {
            return;
        }

        // Load main settings.yaml
        $settingsFile = rtrim($path, '/') . '/settings.yaml';
        if (file_exists($settingsFile)) {
            $settings = Yaml::parseFile($settingsFile);
            if (is_array($settings)) {
                $this->config = array_merge_recursive($this->config, $settings);
            }
        }

        // Load site-specific configurations
        $sitesDir = rtrim($path, '/') . '/Sites/';
        if (is_dir($sitesDir)) {
            $siteFiles = GeneralUtility::getFilesInDir($sitesDir, 'yaml');
            foreach ($siteFiles as $siteFile) {
                $siteConfig = Yaml::parseFile($sitesDir . $siteFile);
                if (is_array($siteConfig)) {
                    $siteName = pathinfo($siteFile, PATHINFO_FILENAME);
                    $this->config['t3hauler']['sites']['configs'][$siteName] = $siteConfig;
                }
            }
        }
    }
}
