# T3Hauler Export Format

T3Hauler uses a custom structured export format to handle TYPO3 data exports with relation dependency resolution.

## Supported Formats

- **JSON**: Human-readable, widely supported (only supported format)

## File Structure

All exports contain four main sections:

### 1. Metadata
Contains export information and configuration:
```json
{
  "metadata": {
    "created_at": 1701432000,
    "created_by": "t3hauler",
    "format_version": "2.0",
    "charset": "utf-8",
    "page_id": 0,
    "export_type": "changed_data",
    "exclude_disabled": false,
    "processed_at": 1701432100,
    "total_records": 25,
    "exported_tables": ["pages", "tt_content", "sys_file_reference"],
    "snapshot_uid": 123,
    "baseline_snapshot": "snapshot_20241201_120000"
  }
}
```

### 2. Records
Lists UIDs of exported records organized by table:
```json
{
  "records": {
    "pages": [1, 2, 3, 15],
    "tt_content": [101, 102, 103, 104, 105],
    "sys_file_reference": [201, 202]
  }
}
```

### 3. Relations
Defines relationships between records for proper import ordering:
```json
{
  "relations": {
    "tt_content:101": [
      {
        "field": "pid",
        "to_table": "pages",
        "to_uid": 1
      }
    ],
    "sys_file_reference:201": [
      {
        "field": "uid_foreign",
        "to_table": "tt_content",
        "to_uid": 101
      }
    ]
  }
}
```

### 4. Change Records
Contains detailed information about individual record changes:
```json
{
  "change_records": {
    "summary": {
      "insert": 5,
      "update": 15,
      "delete": 2,
      "move": 3,
      "total": 25
    },
    "records": [
      {
        "table_name": "pages",
        "record_uid": 1,
        "change_type": "insert",
        "field_changes": {
          "title": {"old": null, "new": "New Page"},
          "hidden": {"old": null, "new": 0}
        },
        "record_hash": "abc123...",
        "previous_hash": null,
        "detected_at": 1701432000,
        "be_user": 1,
        "workspace": 0,
        "language_uid": 0,
        "correlation_id": "t3h_abc123"
      },
      {
        "table_name": "tt_content",
        "record_uid": 101,
        "change_type": "update",
        "field_changes": {
          "header": {"old": "Old Header", "new": "New Header"},
          "bodytext": {"old": "Old text", "new": "New text"}
        },
        "record_hash": "def456...",
        "previous_hash": "xyz789...",
        "detected_at": 1701432050,
        "be_user": 1,
        "workspace": 0,
        "language_uid": 0,
        "correlation_id": "t3h_def456"
      }
    ]
  }
}
```

## Dependency Resolution

The export system automatically:

1. **Orders tables** by common TYPO3 dependency patterns
2. **Identifies relations** through field analysis
3. **Resolves dependencies** to prevent import conflicts
4. **Maintains consistency** across multi-table exports

## Import Process

When importing, the system:

1. Validates export file structure and format
2. Processes tables in dependency order
3. Resolves relations during record creation
4. Maintains referential integrity

## Example Export File

```json
{
  "metadata": {
    "created_at": 1701432000,
    "format_version": "1.0",
    "total_records": 3
  },
  "records": {
    "pages": [1],
    "tt_content": [101, 102]
  },
  "relations": {
    "tt_content:101": [{"field": "pid", "to_table": "pages", "to_uid": 1}],
    "tt_content:102": [{"field": "pid", "to_table": "pages", "to_uid": 1}]
  }
}
```

## Enhanced Change Detection

The improved change detection system provides:

### Record-Level Tracking
- **Individual record changes** are tracked through DataHandler hooks
- **Field-level changes** show exactly what was modified
- **Change types** include insert, update, delete, and move operations
- **User context** tracks who made the changes and when

### Change Metadata
- **Correlation IDs** group related changes together
- **Workspace support** tracks changes in different workspaces
- **Language support** handles multilingual content changes
- **Backend user tracking** shows who made each change

### Migration Benefits
- **Precise exports** only include actually changed records
- **Detailed change information** helps with conflict resolution
- **Audit trail** provides complete change history
- **Rollback support** enables safe undo operations

## Migration Workflow

1. **Snapshot Creation**: Create baseline snapshots for change tracking
2. **Change Detection**: DataHandler hooks automatically track record changes
3. **Export Generation**: Create structured export with dependency resolution
4. **Change Records**: Include detailed change information in migration
5. **Transfer**: Deploy migration files to target environment
6. **Import**: Apply changes with integrity validation and conflict detection
7. **Verify**: Confirm successful import and update baselines

This format ensures reliable, ordered data migration while maintaining TYPO3 relationships and referential integrity.
