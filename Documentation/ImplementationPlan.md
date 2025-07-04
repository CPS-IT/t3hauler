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
- **JSON** (default): Human-readable, widely supported
- **XML**: Structured, compatible with legacy systems
- **YAML**: Configuration-friendly, human-readable

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
- **Configuration Service**: YAML-based settings with dot-notation access
- **Change Detection**: Hash-based comparison with snapshots
- **Database Models**: DataSnapshot with repository pattern
- **CLI Commands**: Basic structure with diff and snapshot commands
- **Testing**: Comprehensive unit tests (31 tests, 58 assertions)

### ✅ Phase 2: Migration Generation (Completed)
- **Migration Model**: Full lifecycle management (pending → applied → failed → rolled_back)
- **Custom Export System**: Replaces TYPO3 internal classes
- **Export Formats**: JSON, XML, YAML with relation handling
- **Migration Service**: Complete orchestration workflow
- **Enhanced CLI**: Fully functional create command with rich output
- **Comprehensive Testing**: 54 tests with 196 assertions covering all components

### 🔄 Phase 3: Migration Application (Next)
- **Import Service**: Apply migrations with format validation
- **Integrity Validation**: Pre-application conflict detection
- **Apply Command**: Complete implementation with dry-run support
- **Rollback Capability**: Undo applied migrations safely
- **Error Recovery**: Robust error handling and cleanup

## Current Architecture Benefits

### Custom Export Advantages
1. **No Internal Dependencies**: Avoids `@internal` TYPO3 classes
2. **Format Flexibility**: Multiple output formats (JSON/XML/YAML)
3. **Relation Handling**: Built-in dependency resolution
4. **Better Performance**: Optimized for T3Hauler use cases
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

```bash
# Show detected changes since last snapshot
ddev typo3 t3hauler:diff

# Create baseline snapshot
ddev typo3 t3hauler:snapshot --create --identifier="baseline_20241201"

# Create migration from detected changes
ddev typo3 t3hauler:create "Add news content and pages" --author="Developer" --site=zug

# Apply migration (when Phase 3 is complete)
ddev typo3 t3hauler:apply T3H_20241201120000_abc12345 --validate

# Show migration status
ddev typo3 t3hauler:status
```

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
- **54 tests** with **196 assertions**
- **Unit tests** for all service classes
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
4. **Migration package** contains JSON/XML/YAML export + metadata

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
- ✅ Generate migrations with structured exports (JSON/XML/YAML)
- ✅ Hash-based integrity validation system
- ✅ Configurable for zug-sitepackage project structure
- ✅ Comprehensive unit test coverage (54 tests)
- ✅ Modern TYPO3 dependency injection architecture

### Phase 3 (Next Phase)
- 🔄 Apply migrations safely with format validation
- 🔄 Advanced conflict detection and resolution
- 🔄 Full rollback capabilities with state restoration
- 🔄 Performance optimization for large datasets

## Risk Mitigation

### Implemented Safeguards
1. **No Internal Dependencies**: Avoids breaking on TYPO3 updates
2. **Comprehensive Testing**: 54 unit tests prevent regressions
3. **Hash-based Validation**: Prevents data corruption
4. **Structured Exports**: Reliable format with validation
5. **Dependency Resolution**: Maintains referential integrity

### Ongoing Considerations
1. **Data Loss Prevention**: Always create backups before applying
2. **Conflict Resolution**: Clear reporting and manual resolution options
3. **Performance**: Optimize for large datasets in Phase 3
4. **Error Recovery**: Robust rollback and cleanup mechanisms

## Next Steps

### Phase 3 Development
1. **Import Service**: Implement structured data import
2. **Apply Command**: Complete migration application workflow
3. **Integrity Validation**: Advanced conflict detection
4. **Rollback System**: Safe undo capabilities
5. **Error Handling**: Comprehensive error recovery

### Post-Phase 3 Enhancements
1. **Backend Module**: Visual migration management interface
2. **Batch Operations**: Handle multiple migrations efficiently
3. **Advanced Relations**: Complex dependency mapping
4. **Performance Optimization**: Large dataset handling

## Conclusion

T3Hauler Phase 1 & 2 provide a solid foundation with:
- **Custom export system** avoiding TYPO3 internal classes
- **Multiple format support** (JSON, XML, YAML)
- **Robust change detection** with hash-based validation
- **Comprehensive testing** ensuring reliability
- **Modern architecture** following TYPO3 v13 best practices

The implementation is ready for Phase 3 development to complete the migration application workflow.