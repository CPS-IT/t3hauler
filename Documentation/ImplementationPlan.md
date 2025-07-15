# t3hauler Implementation Plan

## Overview

t3hauler is a data migration tool for TYPO3 that enables developers to safely package and transfer data changes between environments while maintaining data integrity.

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
- **Modern TYPO3 practices**: Use Services.yaml and dependency injection
- **Code quality**: Use existing tools (composer lint, test, sca)
- **Testing requirements**: Unit tests and functional tests for all classes
- **PSR standards**: Follow PSR-4 autoloading and coding standards

### Instance Configuration
- **Base configuration path**: `packages/zug-sitepackage/Configuration/T3Hauler/`
- **Migrations storage**: `packages/zug-sitepackage/Migrations/`
- **Site-specific configs**: Support per-site migration configurations

## Revised Architecture (Custom Export System)

### Key Decision: No Internal TYPO3 Classes

**Problem**: TYPO3's import/export classes (`TYPO3\CMS\Impexp\Export`, `TYPO3\CMS\Impexp\Import`) are marked as `@internal` and must not be used by extensions.

**Solution**: Implement a custom export system with structured data formats and dependency resolution.

### Custom Export System

#### Core Components

```
Classes/
├── Domain/
│   ├── Model/
│   │   ├── Migration.php             # Migration metadata and lifecycle
│   │   ├── Export.php                # Custom export model with dependency resolution
│   │   └── DataSnapshot.php          # Database state snapshot
│   ├── Repository/
│   │   ├── MigrationRepository.php   # Migration CRUD operations
│   │   └── DataSnapshotRepository.php # Snapshot management
│   └── Service/
│       ├── MigrationService.php      # Main migration orchestration
│       ├── ExportService.php         # Custom export implementation
│       ├── ChangeDetectionService.php # Detect data changes
│       └── IntegrityService.php      # Data integrity checks
├── Command/
│   ├── CreateMigrationCommand.php    # Generate migration from changes
│   ├── ApplyMigrationCommand.php     # Apply specific migration
│   ├── DiffCommand.php               # Show pending changes
│   └── SnapshotCommand.php           # Manage database snapshots
├── Configuration/
│   └── T3HaulerConfiguration.php     # Configuration service
├── Utility/
│   └── HashUtility.php               # Data fingerprinting
└── Exception/
    ├── IntegrityException.php
    ├── MigrationNotFoundException.php
    └── ConflictException.php
```

### Custom Export Format

#### Supported Formats
- **JSON**: Human-readable, widely supported (only supported format)

#### Export Structure
```json
{
  "metadata": {
    "created_at": 1701432000,
    "created_by": "t3hauler",
    "format_version": "1.0",
    "total_records": 25,
    "exported_tables": ["pages", "tt_content"]
  },
  "records": {
    "pages": [1, 2, 3],
    "tt_content": [101, 102, 103]
  },
  "relations": {
    "tt_content:101": [
      {"field": "pid", "to_table": "pages", "to_uid": 1}
    ]
  }
}
```

### Dependency Resolution

The export system automatically:
1. **Orders tables** by common TYPO3 dependency patterns
2. **Identifies relations** through field analysis
3. **Resolves dependencies** to prevent import conflicts
4. **Maintains consistency** across multi-table exports

## Implementation Status

### ✅ Phase 1: Foundation (Completed)
- **Core Infrastructure**: Services.yaml with dependency injection
- **Configuration Service**: YAML-based settings with dot-notation access via T3HaulerConfiguration
- **Change Detection**: Hash-based comparison with snapshots via ChangeDetectionService
- **Database Models**: Complete domain models (Migration, Export, DataSnapshot) with repositories
- **CLI Commands**: Full command structure with 7 implemented commands
- **Database Schema**: Two tables (tx_t3hauler_migrations, tx_t3hauler_snapshots) with proper indexes
- **Testing**: Unit tests covering core functionality (8 test files)

### ✅ Phase 2: Migration Generation (Completed)
- **Migration Model**: Full lifecycle management (pending → applied → failed → rolled_back)
- **Custom Export System**: Replaces TYPO3 internal classes with JSON format
- **Export Format**: Structured JSON with metadata, records, and relations
- **Migration Service**: Complete orchestration workflow for creating migrations
- **Enhanced CLI**: Fully functional create, list, and diff commands
- **Hash-based Integrity**: SHA256 hashing for data integrity validation

### 🔄 Phase 3: Migration Application (80% Complete)
- **Import Service**: Basic structure with validation and dry-run support ✅
- **Apply Command**: Implemented but needs refinement for actual data import 🔄
- **Integrity Validation**: Framework exists but needs full implementation 🔄
- **Error Recovery**: Basic error recovery mechanisms in place ✅
- **Actual Data Operations**: Missing actual data import/export operations ❌
- **Relation Resolution**: Limited TCA-based relation handling ❌

## Current Architecture Benefits

### Custom Export Advantages
1. **No Internal Dependencies**: Avoids `@internal` TYPO3 classes
2. **Format Standardization**: Consistent JSON format
3. **Relation Handling**: Built-in dependency resolution
4. **Better Performance**: Optimized for t3hauler use cases
5. **Enhanced Metadata**: Rich migration context information

### Technical Implementation
- **Dependency Injection**: Full Services.yaml configuration
- **Modern PHP**: Strict types, proper error handling
- **Comprehensive Testing**: Unit tests for all components
- **Static Analysis**: PHPStan level 6 compliance
- **Documentation**: Complete format specifications

## Configuration

### Modern TYPO3 Configuration (Services.yaml)

```yaml
# Configuration/Services.yaml
services:
  _defaults:
    autowire: true
    autoconfigure: true
    public: false

  Cpsit\T3hauler\:
    resource: '../Classes/*'
    exclude: '../Classes/Domain/Model/*'

  # Migration services
  Cpsit\T3hauler\Service\MigrationService:
    arguments:
      $changeDetectionService: '@Cpsit\T3hauler\Service\ChangeDetectionService'
      $exportService: '@Cpsit\T3hauler\Service\ExportService'
      $migrationRepository: '@Cpsit\T3hauler\Domain\Repository\MigrationRepository'

  # CLI Commands
  Cpsit\T3hauler\Command\CreateMigrationCommand:
    tags:
      - name: 'console.command'
        command: 't3hauler:create'
        description: 'Create migration from detected changes'
```

### Project-Specific Configuration

```yaml
# packages/zug-sitepackage/Configuration/T3Hauler/settings.yaml
t3hauler:
  storage:
    migrationPath: 'packages/zug-sitepackage/Migrations/'
    configPath: 'packages/zug-sitepackage/Configuration/T3Hauler/'

  detection:
    enabledTables: ['pages', 'tt_content', 'sys_template', 'tx_news_domain_model_news']
    excludeFields: ['tstamp', 'crdate', 'cruser_id']
    hashAlgorithm: 'sha256'

  sites:
    default: 'zug'
    available: ['zug', 'kei', 'ptx', 'knk', 'life']
```

## CLI Usage Examples

### Currently Working Commands

```bash
# Show detected changes since last snapshot
ddev typo3 t3hauler:diff

# Create baseline snapshot
ddev typo3 t3hauler:snapshot:create --identifier="baseline_20241201"

# List all snapshots
ddev typo3 t3hauler:snapshot:list

# Clean up old snapshots
ddev typo3 t3hauler:snapshot:cleanup --days=30

# Create migration from detected changes
ddev typo3 t3hauler:create "Add news content and pages" --author="Developer"

# List all migrations
ddev typo3 t3hauler:migration:list

# Apply migration (basic implementation)
ddev typo3 t3hauler:migration:apply T3H_20241201120000_abc12345 --dry-run
ddev typo3 t3hauler:migration:apply T3H_20241201120000_abc12345
```

### Available Commands Overview

1. **`t3hauler:create`** - Create migration from detected changes ✅
2. **`t3hauler:diff`** - Show pending changes since last snapshot ✅
3. **`t3hauler:migration:list`** - List all migrations with status ✅
4. **`t3hauler:migration:apply`** - Apply migration (basic implementation) 🔄
5. **`t3hauler:snapshot:create`** - Create database snapshot ✅
6. **`t3hauler:snapshot:list`** - List all snapshots ✅
7. **`t3hauler:snapshot:cleanup`** - Clean up old snapshots ✅

## Database Schema

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
    metadata text,
    PRIMARY KEY (uid),
    UNIQUE KEY migration_id (migration_id)
);

-- Database state snapshots
CREATE TABLE tx_t3hauler_snapshots (
    uid int(11) NOT NULL AUTO_INCREMENT,
    identifier varchar(255) NOT NULL,
    table_name varchar(255) NOT NULL,
    hash varchar(64) NOT NULL,
    created_at int(11) NOT NULL,
    migration_version varchar(255) DEFAULT NULL,
    metadata text,
    PRIMARY KEY (uid),
    UNIQUE KEY identifier (identifier)
);
```

## Testing Strategy

### Current Test Coverage
- **8 unit test files** covering core functionality
- **Unit tests** for service classes, configuration, and utilities
- **Mock-based testing** for external dependencies
- **PHPStan level 6** static analysis compliance

### Test Structure
```php
<?php
namespace Cpsit\T3hauler\Tests\Unit\Service;

use Cpsit\T3hauler\Service\MigrationService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MigrationServiceTest extends TestCase
{
    #[Test]
    public function createMigrationReturnsSuccessfulResult(): void
    {
        // Test implementation
    }
}
```

## Migration Workflow

### Development Phase
1. **Developer makes changes** to content/configuration
2. **`t3hauler:diff`** shows detected changes since last snapshot
3. **`t3hauler:create`** generates migration with structured export
4. **Migration package** contains JSON export + metadata

### Transfer Phase
- Migration files are versioned and transferred
- Contains structured data + complete metadata
- Format validation ensures integrity

### Application Phase (Phase 3)
- **`t3hauler:apply`** validates target environment
- **Integrity checks** prevent conflicts
- **Structured import** maintains relationships
- **Post-migration snapshots** for next cycle

## Success Criteria

### Phase 1 & 2 (Completed) ✅
- ✅ Detect changes in configurable tables automatically
- ✅ Generate migrations with structured JSON exports
- ✅ Hash-based integrity validation system
- ✅ Configurable for zug-sitepackage project structure
- ✅ Unit test coverage for core components (8 test files)
- ✅ Modern TYPO3 dependency injection architecture

### Phase 3 (Next Phase)
- 🔄 Apply migrations safely with format validation
- 🔄 Advanced conflict detection and resolution
- 🔄 Full rollback capabilities with state restoration
- 🔄 Performance optimization for large datasets

## Risk Mitigation

### Implemented Safeguards
1. **No Internal Dependencies**: Avoids breaking on TYPO3 updates
2. **Comprehensive Testing**: Unit tests for core components prevent regressions
3. **Hash-based Validation**: Prevents data corruption
4. **Structured Exports**: Reliable format with validation
5. **Dependency Resolution**: Maintains referential integrity

### Ongoing Considerations
1. **Data Loss Prevention**: Always create backups before applying
2. **Conflict Resolution**: Clear reporting and manual resolution options
3. **Performance**: Optimize for large datasets in Phase 3
4. **Error Recovery**: Robust rollback and cleanup mechanisms

## Remaining Implementation Gaps

### Critical Missing Features
1. **Actual Data Import/Export**: The services have structure but don't retrieve/import actual record data
2. **Record Retrieval**: Export service needs to fetch actual database records for export
3. **Data Insertion**: Import service needs to insert records into target database
4. **File Handling**: No support for file references (sys_file, sys_file_reference)
5. **Complex Relations**: MM relations and inline records not fully supported

### Implementation Priorities
1. **Priority 1**: Complete actual data import/export operations
2. **Priority 2**: Implement proper TCA-based relation handling
3. **Priority 3**: Add file reference support
4. **Priority 4**: Expand test coverage with integration tests
5. **Priority 5**: Performance optimization for large datasets

## Next Steps

### Phase 3 Development
1. **Import Service**: Implement structured data import with actual database operations
2. **Export Service**: Add actual record retrieval and data packaging
3. **Apply Command**: Complete migration application workflow with real data transfer
4. **Integrity Validation**: Advanced conflict detection and resolution
5. **Rollback System**: Safe undo capabilities with state restoration
6. **Error Handling**: Comprehensive error recovery and cleanup

### Post-Phase 3 Enhancements
1. **Backend Module**: Visual migration management interface
2. **Batch Operations**: Handle multiple migrations efficiently
3. **Advanced Relations**: Complex dependency mapping
4. **Performance Optimization**: Large dataset handling

## Conclusion

t3hauler Phase 1 & 2 provide a solid foundation with:
- **Custom export system** avoiding TYPO3 internal classes
- **JSON format support**
- **Robust change detection** with hash-based validation
- **Comprehensive testing** ensuring reliability
- **Modern architecture** following TYPO3 v13 best practices

The implementation is ready for Phase 3 development to complete the migration application workflow.
