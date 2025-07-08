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
        return $this->get('t3hauler.detection.excludeFields', ['tstamp', 'crdate', 'cruser_id']);
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
