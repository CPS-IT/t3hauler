# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with the T3Hauler TYPO3 extension.

## Project Information
- **Package**: cpsit/t3hauler
- **Description**: A tool for developers and integrators to haul data from one TYPO3 installation to another
- **Type**: TYPO3 CMS Extension (typo3-cms-extension)
- **License**: GPL-3.0-or-later
- **TYPO3 Version**: v13.4+
- **PHP Version**: 8.3-8.4
- **Extension Key**: t3hauler

## Project Structure
- **Namespace**: `Cpsit\T3hauler`
- **Classes Directory**: `Classes/`
- **Tests Directory**: `Tests/`
- **Build Directory**: `.build/` (contains vendor, bin, web directories)
- **Main Components**:
  - Domain Models and Repositories (change tracking, data snapshots)
  - Services (change detection, change tracking, data export/import)
  - Hooks (DataHandler integration for real-time change detection)
  - Configuration classes for extension settings
  - CLI Commands for data migration operations

## Build Commands
- **Test All**: `composer test` (runs both unit and functional tests)
- **Unit Tests**: `composer test:unit`
- **Functional Tests**: `composer test:functional`
- **Test with Coverage**:
  - Unit: `composer test:coverage` (HTML) or `composer test:coverage-clover` (Clover XML)
  - Functional: `composer test:coverage-functional` (HTML) or `composer test:coverage-functional-clover` (Clover XML)
- **Lint All**: `composer lint` (runs all linters)
  - `composer lint:composer` - Validates and checks composer.json normalization
  - `composer lint:php` - PHP coding standards (PHP-CS-Fixer)
  - `composer lint:editorconfig` - EditorConfig compliance
  - `composer lint:rector` - TYPO3 Rector code migration analysis
  - `composer lint:fractor` - TYPO3 Fractor TypoScript migration analysis
- **Fix All**: `composer fix` (fixes all auto-fixable issues)
  - `composer fix:composer` - Normalizes composer.json
  - `composer fix:php` - Fixes PHP coding standards
  - `composer fix:editorconfig` - Fixes EditorConfig violations
  - `composer fix:rector` - Applies TYPO3 Rector migrations
  - `composer fix:fractor` - Applies TYPO3 Fractor TypoScript migrations
- **Static Code Analysis**: `composer sca:php` (PHPStan level configuration in phpstan.neon)
- **Security Audit**: `composer audit`
- **Run Static Analysis**: `composer sca` (alias for `composer sca:php`)

## Development Dependencies & Tools
- **Testing**: PHPUnit 11+ with TYPO3 Testing Framework v9.2+
- **Code Quality**: PHP-CS-Fixer with TYPO3 Coding Standards v0.8+
- **Static Analysis**: PHPStan 2.1+ with 1GB memory limit (configuration in `phpstan.neon`)
- **Migration Tools**: TYPO3 Rector v3.5+ and TYPO3 Fractor v0.5+
- **Validation**: EditorConfig CLI, Composer Normalize
- **Security**: Roave Security Advisories (dev-latest)

## CI/CD Pipeline
The project uses GitHub Actions with comprehensive quality gates:
- **Quality Gate**: Runs on every push/PR
  - Composer validation and normalization
  - Code style checks (PHP-CS-Fixer, EditorConfig)
  - Static analysis (PHPStan)
  - Migration checks (Rector, Fractor)
  - Unit tests
  - Functional tests
  - Security audit
- **Build Matrix**: Tests across PHP 8.3 and 8.4
- **Integration Tests**: Validates tool executability and config files
- **Dependency Check**: Monitors for conflicts and outdated packages

## Key Features
- **Data Migration**: Tools for moving data between TYPO3 installations
- **Change Detection**: Real-time tracking of database changes via DataHandler hooks
- **Change Records**: Comprehensive logging of data modifications with:
  - Field-level change tracking
  - User context (backend user, workspace)
  - Correlation IDs for grouping related changes
  - Configurable field exclusion
- **Data Snapshots**: Point-in-time captures of database state
- **Export/Import**: Structured data exchange between TYPO3 instances
- **Hash-based Integrity**: SHA256 hashing for data validation

## Architecture
- **Domain-Driven Design**: Models, repositories, and services
- **TYPO3 v13 Standards**: Modern dependency injection, event system
- **Database Layer**: Doctrine DBAL 4.0+ integration
- **CLI Integration**: Symfony Console commands
- **Hook System**: DataHandler integration for real-time change detection
- **Configuration**: YAML-based service configuration

## Code Style Guidelines
- **PSR Standards**: PSR-4 autoloading, modern PHP practices
- **TYPO3 Coding Standards**: Official v0.8+ ruleset
- **Strict Types**: All PHP files use `declare(strict_types=1)`
- **Type Declarations**: Full type hints including return types
- **PHP 8.3+ Features**: Union types, readonly properties, attributes
- **EditorConfig**: Enforced formatting rules
- **Documentation**: PHPDoc for all public methods

## Testing Strategy
- **Unit Tests**: PHPUnit 11+ with comprehensive coverage
  - Located in `Tests/Unit/`
  - Extensive mocking of dependencies
  - Configuration: `phpunit.unit.xml`
- **Functional Tests**: Integration tests with real database
  - Located in `Tests/Functional/`
  - Uses TYPO3 Testing Framework
  - SQLite database for CI/CD
  - Test fixtures in `Tests/Functional/Fixtures/Database/`
  - Configuration: `phpunit.functional.xml`
- **Test Coverage**: Both unit and functional tests support coverage reporting
- **Attributes**: Modern PHPUnit test attributes instead of annotations
- **Test Structure**: Follows TYPO3 testing conventions
- **Database Testing**: Real database operations in functional tests, mocking in unit tests
- **Coverage Reports**: HTML and Clover XML formats available for both test types

## Database Schema
- **Change Records Table**: `tx_t3hauler_change_records`
  - Tracks individual record changes with metadata
  - JSON field for field-level change details
  - Foreign key relationships to snapshots
- **Data Snapshots**: Integration with existing snapshot system
- **Migrations**: Proper TYPO3 database migration handling

## Configuration
- **Services**: Dependency injection via `Configuration/Services.yaml`
- **Extension Configuration**: TYPO3 extension configuration
- **Change Detection**: Configurable table and field exclusions
- **Hook Registration**: DataHandler hooks in `ext_localconf.php`

## Important Reminders
- **Read Before Edit**: Always use Read tool before making changes to files
- **Follow TYPO3 Conventions**: Extension follows TYPO3 v13 best practices
- **Test Coverage**: Maintain comprehensive unit test coverage
- **Code Quality**: Run linters and static analysis before committing
- **PHPStan Analysis**: ALWAYS run `composer sca` after any code changes to catch type issues
- **Security**: Never expose sensitive data in logs or exports
- **Performance**: Consider memory usage for large data operations
- **Documentation**: Update relevant documentation when adding features

## Static Code Analysis Workflow
- **Before any commit**: Run `composer sca` to check for type errors
- **PHPStan Configuration**: Located in `phpstan.neon` with level 8 analysis
- **Common Issues**: Property type covariance, undefined methods, unused properties
- **Fixing Type Issues**: Use proper type annotations, avoid redundant assertions
- **CI Integration**: PHPStan runs automatically in GitHub Actions quality gate

## Common Operations
- **Add New Domain Model**: Create in `Classes/Domain/Model/`, add repository, write tests
- **Add CLI Command**: Create in appropriate namespace, register in Services.yaml
- **Extend Change Detection**: Modify ChangeTrackingService, update configuration options
- **Database Changes**: Update `ext_tables.sql`, create migrations if needed
- **New Service**: Add to Services.yaml with proper dependency injection
- **After Code Changes**: Always run `composer sca` to validate type safety

## Dependencies
- **Core Dependencies**: TYPO3 CMS Core v13.4+, Doctrine DBAL v4.0+
- **Symfony Components**: Console v6.0+|v7.0+, YAML v6.0+|v7.0+
- **Development Tools**: Comprehensive toolchain for code quality and testing

## Workflow
- **Unit Tests**: add unit tests for each component
- **Functional Tests**: use functional tests where unit tests become too complex
- **Test-driven development**: Implement tests before implementing the functionality where ever possible.
- **Focus on component**: Make small changes to a single component, then test it. Proceed when all issues are fixed.
- **TYPO3 Core API**: Prefer using the TYPO3 core API. Make sure to use the current version v13.
- **Strong typing**: Use typehints for methods and properties. Prefer interfaces. Prefer objects to arrays or strings.
