<?php

/**
 * Run unit and functional tests with merged coverage
 */

declare(strict_types=1);

// Change to project root directory
chdir(__DIR__ . '/../../');

// Create output directories
$directories = [
    '.build/log/coverage/merged',
    '.build/log/coverage/merged/html',
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

echo "Running merged test suite with coverage...\n";

// Run tests with the merged configuration with parallelization
$command = 'XDEBUG_MODE=coverage .build/bin/phpunit -c phpunit.coverage.xml --process-isolation';
$output = [];
$returnVar = 0;

exec($command, $output, $returnVar);

// Output the results
foreach ($output as $line) {
    echo $line . "\n";
}

echo "\nMerged coverage reports generated!\n";
echo "- HTML: .build/log/coverage/merged/html/index.html\n";
echo "- Clover XML: .build/log/coverage/merged/clover.xml\n";
echo "- Cobertura XML: .build/log/coverage/merged/cobertura.xml\n";
echo "- JUnit XML: .build/log/coverage/merged/junit.xml\n";

exit($returnVar);
