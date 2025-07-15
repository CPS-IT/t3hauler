# Test Coverage Solution for t3hauler

This document describes the comprehensive test coverage solution implemented for the t3hauler extension, which merges unit and functional test coverage reports into unified reports.

## Overview

The t3hauler extension now supports generating merged coverage reports that combine both unit and functional test coverage. This provides a complete picture of code coverage across all testing levels.

## Available Coverage Commands

### 1. Merged Coverage (Recommended)
```bash
composer test:coverage
```
Runs all tests together with merged coverage reports. This is the recommended approach for complete coverage.

### 2. Individual Coverage Reports
```bash
# Unit test coverage only
composer test:coverage-unit

# Functional test coverage only
composer test:coverage-functional
```

### 3. Separate Run + Merge
```bash
composer test:coverage-merge
```
Runs unit and functional tests separately, then merges the coverage reports. Useful for debugging coverage issues.

## Output Formats

All coverage commands generate multiple report formats:

### HTML Reports
- **Location**: `.build/log/coverage/merged/html/index.html`
- **Usage**: Interactive browsable coverage report
- **Features**: Line-by-line coverage visualization, method/class breakdowns

### XML Reports
- **Clover XML**: `.build/log/coverage/merged/clover.xml` (CI/CD compatible)
- **Cobertura XML**: `.build/log/coverage/merged/cobertura.xml` (GitLab CI compatible)
- **JUnit XML**: `.build/log/coverage/merged/junit.xml` (Test results)

### Additional Formats
- **Crap4j XML**: `.build/log/coverage/merged/crap4j.xml` (Code complexity analysis)
- **TestDox HTML**: `.build/log/coverage/merged/testdox.html` (Human-readable test documentation)

## Directory Structure

```
.build/log/coverage/
├── unit/                     # Unit test coverage
│   ├── html/                 # HTML report
│   ├── clover.xml           # Clover XML report
│   ├── coverage.php         # PHP coverage object
│   └── junit.xml            # JUnit XML report
├── functional/              # Functional test coverage
│   ├── html/                # HTML report
│   ├── clover.xml          # Clover XML report
│   ├── coverage.php        # PHP coverage object
│   └── junit.xml           # JUnit XML report
└── merged/                  # Merged coverage reports
    ├── html/                # Combined HTML report
    ├── clover.xml          # Combined Clover XML
    ├── cobertura.xml       # Combined Cobertura XML
    ├── crap4j.xml          # Combined Crap4j XML
    ├── junit.xml           # Combined JUnit XML
    └── testdox.html        # Combined TestDox HTML
```

## Local Development

### Prerequisites
- PHP 8.3+ with Xdebug extension
- Composer dependencies installed
- TYPO3 test environment set up

### Running Coverage Locally

1. **Quick Coverage Check**:
   ```bash
   composer test:coverage
   ```

2. **View HTML Report**:
   ```bash
   open .build/log/coverage/merged/html/index.html
   ```

3. **Check Coverage Summary**:
   The coverage summary is displayed in the terminal output and shows:
   - Classes coverage percentage
   - Methods coverage percentage
   - Lines coverage percentage
   - Detailed per-class breakdown

## GitHub Actions Integration

The coverage solution is fully integrated with GitHub Actions:

### Workflow Features
- Runs unit and functional tests with coverage on PHP 8.3
- Merges coverage reports automatically
- Uploads coverage to Codecov
- Stores coverage artifacts for download
- Supports multiple output formats

### Accessing Coverage in CI
1. **Codecov Integration**: Coverage is automatically uploaded to Codecov
2. **GitHub Artifacts**: Download coverage reports from workflow artifacts
3. **Retention**: Coverage artifacts are kept for 30 days

## PHPUnit Configuration

The solution uses three PHPUnit configuration files:

### 1. Unit Tests (`phpunit.unit.xml`)
- Bootstrap: `UnitTestsBootstrap.php`
- Test directory: `Tests/Unit/`
- Process isolation: Disabled
- Coverage: Unit test coverage only

### 2. Functional Tests (`phpunit.functional.xml`)
- Bootstrap: `FunctionalTestsBootstrap.php`
- Test directory: `Tests/Functional/`
- Process isolation: Enabled
- Coverage: Functional test coverage only

### 3. Merged Coverage (`phpunit.coverage.xml`)
- Bootstrap: `FunctionalTestsBootstrap.php`
- Test directories: Both `Tests/Unit/` and `Tests/Functional/`
- Process isolation: Enabled
- Coverage: Combined coverage with all output formats

## Coverage Exclusions

The following are excluded from coverage analysis:
- `Classes/Domain/Model/` (Domain models are excluded by default)
- Command option classes (contain only constants)
- Exception classes (simple data classes)

## Troubleshooting

### Common Issues

1. **Xdebug not enabled**:
   ```bash
   # Enable Xdebug for coverage
   XDEBUG_MODE=coverage composer test:coverage
   ```

2. **Memory issues with large reports**:
   ```bash
   # Increase PHP memory limit
   php -d memory_limit=2G merge-coverage.php
   ```

3. **Permissions issues**:
   ```bash
   # Fix directory permissions
   chmod -R 755 .build/log/coverage/
   ```

4. **Missing coverage files**:
   ```bash
   # Clean and regenerate
   rm -rf .build/log/coverage/
   composer test:coverage
   ```

5. **Slow test execution**:
   ```bash
   # Run tests without coverage for faster execution
   composer test:unit
   composer test:functional

   # Or run specific test classes
   phpunit -c phpunit.unit.xml --filter TestClassName
   ```

### Debug Mode
To debug coverage generation:
```bash
# Enable verbose output
XDEBUG_MODE=coverage,debug composer test:coverage
```

## Performance Considerations

- **Unit tests**: Fast (~3-5 seconds with coverage)
- **Functional tests**: Slower (~60-80 seconds with coverage)
- **Merged coverage**: Combines both (~75-90 seconds total)
- **Optimized workflow**: Clean, simple execution without unnecessary overhead

### Optimization Tips
1. Run unit tests first for quick feedback
2. Use `test:coverage` for complete coverage analysis
3. Use `test:coverage-merge` for development workflow
4. Use individual test commands for focused development
5. Consider excluding complex integration tests from coverage if they're too slow

## Coverage Goals

The t3hauler extension aims for:
- **Overall coverage**: 80%+ lines
- **Critical components**: 95%+ lines
- **Domain logic**: 100% lines
- **Services**: 90%+ lines
- **Repositories**: 100% lines

Current coverage levels are displayed in each report and tracked over time through Codecov integration.

## Contributing

When adding new features:
1. Write unit tests first
2. Add functional tests for integration scenarios
3. Run coverage to ensure adequate coverage
4. Update coverage exclusions if needed
5. Document any new coverage-related changes

## Script Architecture

The coverage solution uses helper scripts located in `Tests/Scripts/`:

### Coverage Scripts
- **`Tests/Scripts/run-merged-tests.php`**: Runs unit and functional tests with merged coverage
- **`Tests/Scripts/merge-coverage.php`**: Merges separate coverage reports into unified reports

### Test Execution
The project uses standard PHPUnit for all test execution:

- **Unit Tests**: Fast execution with comprehensive mocking
- **Functional Tests**: Integration tests with real TYPO3 environment

### Benefits
- **Reliable Execution**: Uses standard PHPUnit for consistent results
- **Multiple Formats**: Generates HTML, Clover, Cobertura, and JUnit reports
- **CI/CD Integration**: Works seamlessly with GitHub Actions

## Tools and Dependencies

- **PHPUnit 11+**: Test framework with coverage support
- **Xdebug**: Coverage data collection
- **TYPO3 Testing Framework**: TYPO3-specific testing utilities
- **Codecov**: Coverage tracking service
- **GitHub Actions**: CI/CD integration
