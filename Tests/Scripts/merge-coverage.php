<?php

/**
 * Merge coverage reports from unit and functional tests
 *
 * This script merges PHP coverage objects from unit and functional test runs
 * and generates combined HTML, Clover XML, and JUnit XML reports.
 */

declare(strict_types=1);

use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Report\Clover as CloverReport;
use SebastianBergmann\CodeCoverage\Report\Cobertura as CoberturaReport;
use SebastianBergmann\CodeCoverage\Report\Crap4j as Crap4jReport;
use SebastianBergmann\CodeCoverage\Report\Html\Facade as HtmlReport;

// Change to project root directory
chdir(__DIR__ . '/../../');

// Autoload Composer dependencies
require_once __DIR__ . '/../../.build/vendor/autoload.php';

function createOutputDirectories(): void
{
    $directories = [
        '.build/log/coverage/merged',
        '.build/log/coverage/merged/html',
    ];

    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

function loadCoverageData(string $file): ?CodeCoverage
{
    if (!file_exists($file)) {
        echo "Coverage file not found: $file\n";
        return null;
    }

    $coverage = include $file;
    if (!$coverage instanceof CodeCoverage) {
        echo "Invalid coverage data in: $file\n";
        return null;
    }

    return $coverage;
}

function mergeCoverageReports(): void
{
    echo "Merging coverage reports...\n";

    createOutputDirectories();

    // Load unit test coverage
    $unitCoverage = loadCoverageData('.build/log/coverage/unit/coverage.php');

    // Load functional test coverage
    $functionalCoverage = loadCoverageData('.build/log/coverage/functional/coverage.php');

    if (!$unitCoverage && !$functionalCoverage) {
        echo "No coverage data found to merge.\n";
        exit(1);
    }

    // Create merged coverage object
    $mergedCoverage = $unitCoverage ?: $functionalCoverage;

    // Merge functional coverage into unit coverage if both exist
    if ($unitCoverage && $functionalCoverage) {
        echo "Merging unit and functional coverage...\n";
        $mergedCoverage->merge($functionalCoverage);
    } elseif ($unitCoverage) {
        echo "Using unit coverage only...\n";
    } elseif ($functionalCoverage) {
        echo "Using functional coverage only...\n";
    }

    // Generate HTML report
    echo "Generating HTML report...\n";
    $htmlReport = new HtmlReport();
    $htmlReport->process($mergedCoverage, '.build/log/coverage/merged/html');

    // Generate Clover XML report
    echo "Generating Clover XML report...\n";
    $cloverReport = new CloverReport();
    file_put_contents('.build/log/coverage/merged/clover.xml', $cloverReport->process($mergedCoverage));

    // Generate Cobertura XML report
    echo "Generating Cobertura XML report...\n";
    $coberturaReport = new CoberturaReport();
    file_put_contents('.build/log/coverage/merged/cobertura.xml', $coberturaReport->process($mergedCoverage));

    // Generate Crap4j XML report
    echo "Generating Crap4j XML report...\n";
    $crap4jReport = new Crap4jReport();
    file_put_contents('.build/log/coverage/merged/crap4j.xml', $crap4jReport->process($mergedCoverage));

    // Save merged coverage as PHP file
    echo "Saving merged coverage data...\n";
    file_put_contents('.build/log/coverage/merged/coverage.php', serialize($mergedCoverage));

    echo "Coverage reports merged successfully!\n";
    echo "Reports available in: .build/log/coverage/merged/\n";
    echo "- HTML: .build/log/coverage/merged/html/index.html\n";
    echo "- Clover XML: .build/log/coverage/merged/clover.xml\n";
    echo "- Cobertura XML: .build/log/coverage/merged/cobertura.xml\n";
    echo "- Crap4j XML: .build/log/coverage/merged/crap4j.xml\n";
}

// Run the merge
mergeCoverageReports();
