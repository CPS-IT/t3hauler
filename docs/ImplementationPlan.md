# T3Hauler Implementation Plan

## Overview

T3Hauler is a data migration tool for TYPO3 that enables developers to safely package and transfer data changes between environments while maintaining data integrity.

## Goals

- **Edit data in local development environment**: Provide an interface to identify and track data changes
- **Package changes to a set**: Create migration packages containing only the changed data
- **Apply changes in target environment**: Import and apply changes safely in production/staging
- **Ensure data integrity**: Verify that data hasn't been modified in the target environment before applying changes
- **Prevent conflicts**: Reject changes if the target database has been modified since the migration was created

## Project-Specific Requirements

### Configuration Structure
- **Configurable paths**: All migration and configuration paths must be configurable
- **Project structure**: Use `packages/zug-sitepackage` as the primary configuration location
- **Multi-site support**: Support the existing multi-site setup (zug, kei, ptx, knk, life)

### Development Standards
- **Modern TYPO3 practices**: Use Services.yaml and annotations instead of $GLOBALS
- **Code quality**: Use existing tools (composer lint, test, sca)
- **Testing requirements**: Unit tests and functional tests for all classes
- **PSR standards**: Follow PSR-4 autoloading and coding standards

### Instance Configuration
- **Base configuration path**: `packages/zug-sitepackage/Configuration/T3Hauler/`
- **Migrations storage**: `packages/zug-sitepackage/Migrations/`
- **Site-specific configs**: Support per-site migration configurations

## Architecture Analysis

### Available Libraries (Updated Analysis)

After installing `doctrine/migrations` and `cpsit/t3import_export`, we now have three powerful options:

1. **TYPO3 Core Import/Export (ext:impexp)** ⭐ **Best for Record-Level Changes**
   - Provides robust T3D format for data serialization
   - Handles record dependencies and relations automatically
   - Supports files and assets
   - Built-in integrity checks and validation
   - Native TYPO3 integration with proper permission handling
   - **Use Case**: Content records, pages, files, configuration records

2. **Doctrine Migrations** ⭐ **Best for Schema & Data Migrations**
   - Professional database migration framework
   - Version tracking with migration history
   - Up/down migration support with rollback capabilities
   - Schema and data migration support
   - Transactional execution with proper error handling
   - **Use Case**: Database schema changes, bulk data transformations, structural migrations

3. **CPSIT T3Import_Export** ⭐ **Best for Complex Data Workflows**
   - Comprehensive import/export framework for TYPO3
   - Modular component architecture (PreProcessors, PostProcessors, Converters)
   - Support for multiple formats (CSV, XML, Database, Repository objects)
   - YAML and TypoScript configuration
   - Queue system for asynchronous processing
   - Advanced data transformation pipeline
   - **Use Case**: Complex data migrations, format conversions, data integrations

### Recommended Hybrid Approach

**Strategy**: Combine all three libraries to create a comprehensive migration system:

- **Doctrine Migrations**: Migration versioning, schema changes, and migration orchestration
- **TYPO3 impexp**: TYPO3-native record serialization and dependency handling
- **CPSIT T3Import_Export**: Complex data transformations and multi-format support

### Current T3Hauler Package Structure

- Minimal structure with basic configuration classes
- Extends `DWenzel\T3extensionTools\Configuration\ExtensionConfiguration`
- Ready for extension with core functionality

## Proposed Hybrid Architecture

### Core Components

```
Classes/
├── Domain/
│   ├── Model/
│   │   ├── DataMigration.php       # Doctrine migration extension for data
│   │   ├── MigrationSet.php        # Collection of migrations
│   │   ├── DataSnapshot.php        # Database state snapshot
│   │   └── ChangeSet.php           # Detected changes container
│   ├── Repository/
│   │   ├── MigrationRepository.php
│   │   ├── DataSnapshotRepository.php
│   │   └── ChangeSetRepository.php
│   └── Service/
│       ├── MigrationOrchestrator.php   # Main orchestration service
│       ├── ChangeDetectionService.php  # Detect data changes
│       ├── IntegrityService.php        # Data integrity checks
│       ├── T3ExportService.php         # TYPO3 native export service
│       ├── T3ImportService.php         # TYPO3 native import service
│       ├── TransformationService.php   # T3ImportExport wrapper
│       └── DoctrineService.php         # Doctrine migrations wrapper
├── Migration/
│   ├── AbstractDataMigration.php   # Base class extending Doctrine
│   ├── T3HaulerMigrationFactory.php
│   └── DataMigrationGenerator.php
├── Command/
│   ├── CreateMigrationCommand.php  # Generate migration from changes
│   ├── ApplyMigrationCommand.php   # Apply specific migration
│   ├── StatusCommand.php           # Show migration status
│   ├── RollbackCommand.php         # Rollback migration
│   └── DiffCommand.php             # Show pending changes
├── Adapter/
│   ├── DoctrineAdapter.php         # Doctrine migrations integration
│   ├── ImpexpAdapter.php           # TYPO3 impexp integration
│   └── T3ImportExportAdapter.php   # T3ImportExport integration
├── Controller/
│   └── MigrationController.php     # Backend module
├── Event/
│   ├── BeforeMigrationEvent.php
│   ├── AfterMigrationEvent.php
│   └── ChangeDetectedEvent.php
├── Exception/
│   ├── IntegrityException.php
│   ├── MigrationNotFoundException.php
│   ├── ConflictException.php
│   └── UnsupportedChangeException.php
└── Utility/
    ├── HashUtility.php             # Data fingerprinting
    ├── FileUtility.php             # File operations
    └── ChangeDetector.php          # Change detection logic
```

### Integration Strategy

#### 1. Migration Types by Use Case

**Content/Record Migrations** → **TYPO3 impexp**
```php
// Handles: pages, content elements, files, sys_template, etc.
class ContentMigration extends AbstractDataMigration
{
    public function up(Schema $schema): void
    {
        $this->exportRecords(['pages' => [1, 2, 3], 'tt_content' => [15, 16]]);
    }

    public function down(Schema $schema): void
    {
        $this->removeRecords(['pages' => [1, 2, 3], 'tt_content' => [15, 16]]);
    }
}
```

**Schema Migrations** → **Doctrine Migrations**
```php
// Handles: table structure, indexes, constraints
class AddCustomFieldMigration extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $table = $schema->getTable('pages');
        $table->addColumn('custom_field', 'string', ['length' => 255]);
    }
}
```

**Complex Data Transformations** → **T3ImportExport**
```php
// Handles: CSV imports, data format conversions, complex mappings
class DataTransformationMigration extends AbstractDataMigration
{
    public function up(Schema $schema): void
    {
        $this->executeT3ImportExportTask('importLegacyData', [
            'source' => 'fileadmin/migration/legacy_data.csv',
            'transformations' => ['mapFields', 'validateData', 'generateSlugs']
        ]);
    }
}
```

#### 2. Layered Architecture Benefits

- **Doctrine Migrations**: Provides versioning, execution order, rollback infrastructure
- **TYPO3 impexp**: Handles TYPO3-specific record serialization and relations
- **T3ImportExport**: Provides complex data transformation pipeline
- **T3Hauler**: Orchestrates all components and adds change detection + integrity checks

### Data Flow

1. **Development Phase**
   - Developer makes changes to content/configuration
   - `CreateMigrationCommand` captures changes since last snapshot
   - Creates migration package with:
     - Changed records (using T3D format)
     - Integrity hash of source data
     - Metadata (timestamp, author, description)

2. **Transfer Phase**
   - Migration package is versioned and transferred
   - Contains T3D files + metadata JSON

3. **Application Phase**
   - `ApplyMigrationCommand` validates target environment
   - Checks integrity hash against target database
   - Applies changes if validation passes
   - Creates new snapshot for next migration

### Data Integrity Strategy

#### Snapshot-Based Integrity Checks

1. **Database State Snapshots**
   - Create SHA256 hashes of relevant table records
   - Store in `tx_t3hauler_snapshots` table
   - Include timestamp and migration context

2. **Conflict Detection**
   - Compare current target state with baseline hash
   - Reject migration if target has been modified
   - Provide detailed conflict report

3. **Rollback Capability**
   - Store previous state before applying migration
   - Enable rollback to previous snapshot

#### Hash Strategy

```php
// Example integrity check
$sourceHash = $this->calculateTableHash('pages', $lastMigrationTimestamp);
$targetHash = $this->calculateTableHash('pages', $lastMigrationTimestamp);

if ($sourceHash !== $targetHash) {
    throw new ConflictException('Target database has been modified since last migration');
}
```

## Implementation Strategy (Updated for Hybrid Approach)

### Phase 1: Foundation & Doctrine Integration (Week 1-2)

1. **Core Architecture Setup**
   - Extend Doctrine AbstractMigration for T3Hauler
   - Create adapter pattern for all three libraries
   - Implement MigrationOrchestrator service
   - Set up basic CLI commands

2. **Doctrine Migrations Configuration**
   - Configure Doctrine migrations for TYPO3
   - Create T3Hauler migration namespace
   - Implement AbstractDataMigration base class

### Phase 2: Library Adapters (Week 3-4)

1. **TYPO3 ImpExp Adapter**
   - Wrap TYPO3 Export/Import classes
   - Add change detection for content records
   - Implement T3D file handling within migrations

2. **T3ImportExport Adapter**
   - Integrate transfer task execution
   - Support YAML configuration within migrations
   - Handle complex data transformations

3. **Change Detection Service**
   - Database snapshot comparison
   - Content change identification
   - Automatic migration generation

### Phase 3: Enhanced CLI & Migration Generation (Week 5)

1. **Advanced CLI Commands**
   - `t3hauler:diff` - Show pending changes
   - `t3hauler:generate` - Auto-generate migrations from changes
   - `t3hauler:migrate` - Apply migrations with validation
   - Integration with Doctrine migration commands

2. **Migration Templates**
   - Content migration templates
   - Schema migration templates
   - Data transformation templates

### Phase 4: Backend Module & Integrity System (Week 6)

1. **Backend Interface**
   - Visual migration history (Doctrine migrations table)
   - Change preview interface
   - Migration execution with progress

2. **Enhanced Integrity Checks**
   - Pre-migration validation
   - Post-migration verification
   - Conflict resolution interface

### Phase 5: Advanced Features & Testing (Week 7-8)

1. **Advanced Migration Types**
   - Multi-step migrations
   - Conditional migrations
   - Environment-specific migrations

2. **Comprehensive Testing**
   - Integration tests with all three libraries
   - Performance optimization
   - Documentation completion

## Enhanced CLI Usage Examples

```bash
# Show changes since last migration
ddev typo3 t3hauler:diff

# Generate migration from detected changes
ddev typo3 t3hauler:generate "Add news content" --detect-changes

# Execute specific migration
ddev typo3 doctrine:migrations:migrate YYYYMMDDHHMMSS

# Use T3Hauler orchestrated migration
ddev typo3 t3hauler:migrate --with-validation

# Rollback using Doctrine
ddev typo3 doctrine:migrations:migrate prev

# Show migration status (combines all sources)
ddev typo3 t3hauler:status
```

## Migration Examples

### Auto-Generated Content Migration

```php
<?php
namespace App\Migration;

use Cpsit\T3hauler\Migration\AbstractDataMigration;
use Doctrine\DBAL\Schema\Schema;

class Version20241203120000 extends AbstractDataMigration
{
    public function getDescription(): string
    {
        return 'Add news content and update page structure';
    }

    public function up(Schema $schema): void
    {
        // Auto-detected changes: new pages and content elements
        $this->exportRecords([
            'pages' => [12, 13, 14],
            'tt_content' => [145, 146, 147, 148],
            'sys_file_reference' => [89, 90]
        ]);
    }

    public function down(Schema $schema): void
    {
        $this->removeRecords([
            'pages' => [12, 13, 14],
            'tt_content' => [145, 146, 147, 148],
            'sys_file_reference' => [89, 90]
        ]);
    }
}
```

### Complex Data Transformation Migration

```php
<?php
class Version20241203130000 extends AbstractDataMigration
{
    public function up(Schema $schema): void
    {
        // Schema change first
        $table = $schema->getTable('tx_news_domain_model_news');
        $table->addColumn('imported_id', 'integer', ['notnull' => false]);

        // Then complex data import using T3ImportExport
        $this->executeT3ImportExportTask('importLegacyNews', [
            'source' => [
                'class' => 'CPSIT\\T3importExport\\Persistence\\DataSourceCSV',
                'config' => ['file' => 'fileadmin/migration/legacy_news.csv']
            ],
            'transformations' => [
                'mapFields' => ['title' => 'headline', 'text' => 'bodytext'],
                'generateSlugs' => true,
                'validateData' => ['required' => ['title', 'text']]
            ]
        ]);
    }
}
```

## Database Schema

### Migration Tracking Tables

```sql
-- Migration metadata
CREATE TABLE tx_t3hauler_migrations (
    uid int(11) NOT NULL AUTO_INCREMENT,
    migration_id varchar(255) NOT NULL,
    name varchar(255) NOT NULL,
    description text,
    created_at int(11) NOT NULL,
    applied_at int(11) DEFAULT NULL,
    author varchar(255) NOT NULL,
    source_hash varchar(64) NOT NULL,
    target_hash varchar(64) DEFAULT NULL,
    status enum('pending','applied','failed','rolled_back') DEFAULT 'pending',
    data_file varchar(255) NOT NULL,
    PRIMARY KEY (uid),
    KEY migration_id (migration_id)
);

-- Database state snapshots
CREATE TABLE tx_t3hauler_snapshots (
    uid int(11) NOT NULL AUTO_INCREMENT,
    migration_uid int(11) NOT NULL,
    table_name varchar(255) NOT NULL,
    hash varchar(64) NOT NULL,
    created_at int(11) NOT NULL,
    PRIMARY KEY (uid),
    KEY migration_uid (migration_uid),
    KEY table_name (table_name)
);
```

## Configuration

### Modern TYPO3 Configuration (Services.yaml)

```yaml
# app/vendor/cpsit/t3hauler/Configuration/Services.yaml
services:
  _defaults:
    autowire: true
    autoconfigure: true
    public: false

  Cpsit\T3hauler\:
    resource: '../Classes/*'
    exclude: '../Classes/Domain/Model/*'

  # Commands with proper DI
  Cpsit\T3hauler\Command\CreateMigrationCommand:
    tags:
      - name: 'console.command'
        command: 't3hauler:create'
        description: 'Create migration from detected changes'
        schedulable: false

  Cpsit\T3hauler\Command\ApplyMigrationCommand:
    tags:
      - name: 'console.command'
        command: 't3hauler:apply'
        description: 'Apply migration with validation'
        schedulable: false

  Cpsit\T3hauler\Command\StatusCommand:
    tags:
      - name: 'console.command'
        command: 't3hauler:status'
        description: 'Show migration status'
        schedulable: false

  Cpsit\T3hauler\Command\DiffCommand:
    tags:
      - name: 'console.command'
        command: 't3hauler:diff'
        description: 'Show pending changes'
        schedulable: false

  # Configuration service with configurable paths
  Cpsit\T3hauler\Configuration\T3HaulerConfiguration:
    arguments:
      $configurationPaths:
        - '%kernel.project_dir%/packages/zug-sitepackage/Configuration/T3Hauler/'
        - '%kernel.project_dir%/config/t3hauler/'
      $migrationPaths:
        - '%kernel.project_dir%/packages/zug-sitepackage/Migrations/'
        - '%kernel.project_dir%/migrations/t3hauler/'
```

### Project-Specific Configuration Structure

```
packages/zug-sitepackage/
├── Configuration/
│   └── T3Hauler/
│       ├── settings.yaml           # Main configuration
│       ├── Sites/                  # Site-specific configs
│       │   ├── zug.yaml
│       │   ├── kei.yaml
│       │   ├── ptx.yaml
│       │   ├── knk.yaml
│       │   └── life.yaml
│       └── Templates/              # Migration templates
│           ├── ContentMigration.php
│           ├── SchemaMigration.php
│           └── DataTransformation.php
└── Migrations/                     # Generated migrations
    ├── Version20241203120000.php
    ├── Version20241203130000.php
    └── data/                       # Migration data files
        ├── content_export_001.t3d
        └── content_export_002.t3d
```

### Settings Configuration

```yaml
# packages/zug-sitepackage/Configuration/T3Hauler/settings.yaml
t3hauler:
  storage:
    migrationPath: '%kernel.project_dir%/packages/zug-sitepackage/Migrations/'
    dataPath: '%kernel.project_dir%/packages/zug-sitepackage/Migrations/data/'
    configPath: '%kernel.project_dir%/packages/zug-sitepackage/Configuration/T3Hauler/'

  integrity:
    hashAlgorithm: 'sha256'
    excludeTables: ['sys_log', 'sys_history', 'be_sessions', 'fe_sessions']
    includeTables: ['pages', 'tt_content', 'sys_file', 'sys_file_reference']

  migration:
    autoSnapshot: true
    requireConfirmation: true
    namespace: 'ZugSitepackage\\Migration'

  detection:
    enabledTables: ['pages', 'tt_content', 'sys_template', 'tx_news_domain_model_news']
    excludeFields: ['tstamp', 'crdate', 'cruser_id']

  sites:
    default: 'zug'
    available: ['zug', 'kei', 'ptx', 'knk', 'life']
```

### Site-Specific Configuration Example

```yaml
# packages/zug-sitepackage/Configuration/T3Hauler/Sites/zug.yaml
site:
  identifier: 'zug'
  rootPageId: 1

migration:
  enabledTables:
    - pages
    - tt_content
    - tx_news_domain_model_news
    - tx_zugproject_domain_model_project
    - tx_zugsitepackage_domain_model_publication

  tableConfigs:
    pages:
      excludeFields: ['tstamp', 'crdate', 'deleted']
      includeRelations: ['media']
    tt_content:
      excludeFields: ['tstamp', 'crdate']
      includeRelations: ['image', 'media', 'assets']
```

## CLI Usage Examples

```bash
# Create migration from current changes
ddev typo3 t3hauler:create "Add new content elements" --author="developer"

# Show migration status
ddev typo3 t3hauler:status

# Apply migration (dry-run first)
ddev typo3 t3hauler:apply migration_20241201_001 --dry-run
ddev typo3 t3hauler:apply migration_20241201_001

# Rollback migration
ddev typo3 t3hauler:rollback migration_20241201_001
```

## Risk Mitigation

1. **Data Loss Prevention**
   - Always create backups before applying migrations
   - Implement comprehensive rollback mechanisms
   - Use transactions where possible

2. **Conflict Resolution**
   - Provide clear conflict reporting
   - Allow manual conflict resolution
   - Support merge strategies

3. **Testing Strategy**
   - Unit tests for all core services
   - Integration tests with sample data
   - Performance tests for large datasets

## Success Criteria

1. **Functional Requirements**
   - ✅ Create migration packages from local changes
   - ✅ Apply migrations safely in target environment
   - ✅ Detect and prevent conflicting changes
   - ✅ Rollback capability for failed migrations

2. **Non-Functional Requirements**
   - Performance: Handle datasets up to 10,000 records
   - Reliability: 99.9% success rate for conflict-free migrations
   - Usability: CLI interface with clear feedback
   - Maintainability: Clean architecture with proper separation

## Testing Strategy and Code Quality

### Testing Requirements

**Unit Tests** (Required for all classes)
```php
<?php
namespace Cpsit\T3hauler\Tests\Unit\Service;

use Cpsit\T3hauler\Service\ChangeDetectionService;
use PHPUnit\Framework\TestCase;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class ChangeDetectionServiceTest extends UnitTestCase
{
    private ChangeDetectionService $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new ChangeDetectionService();
    }

    /**
     * @test
     */
    public function detectChangesReturnsEmptyArrayWhenNoChanges(): void
    {
        $result = $this->subject->detectChanges(['pages'], []);
        self::assertEmpty($result);
    }
}
```

**Functional Tests** (Required for integration scenarios)
```php
<?php
namespace Cpsit\T3hauler\Tests\Functional\Command;

use Cpsit\T3hauler\Command\CreateMigrationCommand;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

class CreateMigrationCommandTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['cpsit/t3hauler'];

    /**
     * @test
     */
    public function createMigrationCommandGeneratesValidMigration(): void
    {
        // Test implementation with real database
    }
}
```

### Code Quality Tools Integration

**Use existing project tools:**
```bash
# Use from project root or vendor/cpsit/t3hauler
composer lint      # Runs all linting (composer, editorconfig, PHP, TypoScript)
composer fix       # Fixes all fixable issues
composer test      # Runs unit and functional tests
composer sca       # Static code analysis (PHPStan level 6)
```

**PHPStan Configuration** (`phpstan.neon`)
```yaml
includes:
    - %currentWorkingDirectory%/../../rector.php

parameters:
    level: 6
    paths:
        - Classes
        - Tests
    scanDirectories:
        - %currentWorkingDirectory%/../../packages/zug-sitepackage/Classes
```

**Required Coverage**: Minimum 80% code coverage for all service classes

## Minimal Viable Product (MVP)

### MVP Definition: Basic Change Detection and Migration

**Core MVP Features** (Deliverable in 3-4 weeks):

1. **Change Detection** ⭐ **Primary Value**
   - Detect changes in be_groups, pages and tt_content tables
   - Generate hash-based snapshots
   - Compare current state vs. last snapshot

2. **Basic Migration Generation** ⭐ **Core Functionality**
   - Generate Doctrine migrations with T3D exports
   - Use TYPO3 impexp for content serialization
   - Store migrations in configurable paths

3. **CLI Interface** ⭐ **User Interface**
   - `t3hauler:diff` - Show detected changes
   - `t3hauler:create` - Generate migration from changes
   - `t3hauler:apply` - Apply migration with basic validation

4. **Configuration System** ⭐ **Project Integration**
   - Load settings from `packages/zug-sitepackage/Configuration/T3Hauler/`
   - Support configurable table lists and exclude patterns
   - Site-specific configuration for multi-site setup

### MVP Success Criteria

- **Manual content changes** are detected automatically
- **Generated migrations** can be applied to target environment
- **Data integrity** is maintained (basic hash validation)
- **Zero data loss** during migration process
- **Configurable for project** structure and multi-site setup

### Post-MVP Enhancements

**Phase 2** (Weeks 5-6):
- Backend module for visual migration management
- Advanced integrity checks and conflict resolution
- T3ImportExport integration for complex transformations

**Phase 3** (Weeks 7-8):
- Full rollback capabilities
- Migration templates and automation
- Performance optimization for large datasets

## Updated Implementation Strategy (MVP-Focused)

### Phase 1: MVP Foundation (Week 1-2)

**Sprint 1.1: Core Infrastructure**
- ✅ Configuration service with YAML support
- ✅ Basic change detection service
- ✅ Doctrine migration integration
- ✅ Services.yaml setup with DI

**Sprint 1.2: Change Detection**
- ✅ Hash-based snapshot system
- ✅ Database state comparison
- ✅ Configurable table monitoring
- ✅ Unit tests for all services

### Phase 2: MVP Migration Generation (Week 3)

**Sprint 2.1: TYPO3 ImpExp Integration**
- ✅ T3D export generation for detected changes
- ✅ Migration file generation with embedded T3D data
- ✅ Basic migration metadata handling

**Sprint 2.2: CLI Commands**
- ✅ `t3hauler:diff` command implementation
- ✅ `t3hauler:create` command implementation
- ✅ Configuration loading and validation

### Phase 3: MVP Migration Application (Week 4)

**Sprint 3.1: Migration Application**
- ✅ `t3hauler:apply` command implementation
- ✅ Basic integrity validation before apply
- ✅ T3D import execution

**Sprint 3.2: MVP Testing and Validation**
- ✅ Functional tests for complete workflow
- ✅ Integration with existing code quality tools
- ✅ Documentation and examples

### MVP Development Timeline

```
Week 1: Core Infrastructure + Change Detection
├── Day 1-2: Configuration service and DI setup
├── Day 3-4: Change detection and snapshot system
└── Day 5: Unit tests and initial CLI structure

Week 2: Integration with TYPO3 ImpExp
├── Day 1-2: T3D export wrapper and migration generation
├── Day 3-4: Migration file handling and storage
└── Day 5: Testing and refinement

Week 3: CLI Commands and User Interface
├── Day 1-2: diff and create commands
├── Day 3-4: apply command and validation
└── Day 5: Integration testing

Week 4: MVP Completion and Project Integration
├── Day 1-2: Configuration for zug-sitepackage structure
├── Day 3-4: Multi-site support and testing
└── Day 5: Documentation and deployment preparation
```

**Total MVP Duration**: 4 weeks
**Total Enhanced Version**: 8 weeks

## Updated CLI Usage Examples (MVP)

```bash
# Initialize T3Hauler configuration (one-time setup)
ddev typo3 t3hauler:init --project-path="packages/zug-sitepackage"

# Show detected changes since last snapshot
ddev typo3 t3hauler:diff

# Create migration from detected changes
ddev typo3 t3hauler:create "Add news content and pages" --site=zug

# Apply migration in target environment
ddev typo3 t3hauler:apply Version20241203120000 --validate

# Create initial snapshot (baseline)
ddev typo3 t3hauler:snapshot --create
```

## Success Criteria

### MVP Success Criteria

1. **Functional Requirements** (MVP)
   - ✅ Detect changes in pages and tt_content automatically
   - ✅ Generate migrations with T3D exports
   - ✅ Apply migrations safely with basic validation
   - ✅ Configurable for zug-sitepackage project structure

2. **Quality Requirements** (MVP)
   - 80%+ unit test coverage for services
   - Integration with existing code quality tools
   - Zero data corruption during migrations
   - Clear CLI feedback and error handling

### Full Version Success Criteria

1. **Functional Requirements** (Full)
   - ✅ Advanced conflict detection and resolution
   - ✅ Backend module for visual management
   - ✅ Full rollback capabilities
   - ✅ Multi-library integration (Doctrine + ImpExp + T3ImportExport)

2. **Non-Functional Requirements** (Full)
   - Performance: Handle datasets up to 10,000 records
   - Reliability: 99.9% success rate for conflict-free migrations
   - Usability: Backend module with real-time progress
   - Maintainability: Clean architecture with comprehensive tests

## Next Steps

### MVP Development

1. **Week 1**: Set up core infrastructure and change detection
2. **Week 2**: Integrate TYPO3 ImpExp and migration generation
3. **Week 3**: Implement CLI commands and user interface
4. **Week 4**: Project integration and multi-site configuration

### Post-MVP Evaluation

1. **Validate MVP** with real project data migration scenarios
2. **Gather feedback** from development team usage
3. **Prioritize enhancements** based on actual needs
4. **Plan Phase 2** implementation if MVP proves valuable
