<?php

declare(strict_types=1);

/**
 * Build script to set up TYPO3 test environment for functional tests
 */

require_once __DIR__ . '/.build/vendor/autoload.php';

use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;

// Set up TYPO3 environment
$_ENV['TYPO3_PATH_ROOT'] = __DIR__ . '/.build/Web';
$_ENV['TYPO3_PATH_APP'] = __DIR__ . '/.build/Web';

// Create minimal LocalConfiguration.php if it doesn't exist
$localConfigFile = __DIR__ . '/.build/Web/typo3conf/LocalConfiguration.php';
if (!file_exists($localConfigFile)) {
    $localConfigDir = dirname($localConfigFile);
    if (!is_dir($localConfigDir)) {
        mkdir($localConfigDir, 0755, true);
    }

    $config = [
        'BE' => [
            'installToolPassword' => '$2y$12$test',
        ],
        'DB' => [
            'Connections' => [
                'Default' => [
                    'driver' => 'pdo_sqlite',
                    'path' => ':memory:',
                ],
            ],
        ],
        'EXT' => [
            'extConf' => [],
        ],
        'EXTENSIONS' => [
            't3hauler' => [
                'detection' => [
                    'enabledTables' => ['pages', 'tt_content'],
                ],
            ],
        ],
    ];

    file_put_contents($localConfigFile, "<?php\nreturn " . var_export($config, true) . ";\n");
}

// Create PackageStates.php if it doesn't exist
$packageStatesFile = __DIR__ . '/.build/Web/typo3conf/PackageStates.php';
if (!file_exists($packageStatesFile)) {
    $packageStates = [
        'packages' => [
            'core' => [
                'packagePath' => 'typo3/sysext/core/',
            ],
            'backend' => [
                'packagePath' => 'typo3/sysext/backend/',
            ],
            'frontend' => [
                'packagePath' => 'typo3/sysext/frontend/',
            ],
            't3hauler' => [
                'packagePath' => 'typo3conf/ext/t3hauler/',
            ],
        ],
        'version' => 5,
    ];

    file_put_contents($packageStatesFile, "<?php\nreturn " . var_export($packageStates, true) . ";\n");
}

echo "TYPO3 test environment prepared successfully.\n";
