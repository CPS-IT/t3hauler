# T3Hauler Export Format

T3Hauler uses a custom structured export format to handle TYPO3 data exports with relation dependency resolution.

## Supported Formats

- **JSON** (default): Human-readable, widely supported
- **XML**: Structured, compatible with TYPO3 legacy systems  
- **YAML**: Human-readable, configuration-friendly

## File Structure

All export formats contain three main sections:

### 1. Metadata
Contains export information and configuration:
```json
{
  "metadata": {
    "created_at": 1701432000,
    "created_by": "t3hauler",
    "format_version": "1.0",
    "charset": "utf-8",
    "page_id": 0,
    "export_type": "changed_data",
    "exclude_disabled": false,
    "processed_at": 1701432100,
    "total_records": 25,
    "exported_tables": ["pages", "tt_content", "sys_file_reference"]
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

## Example Export Files

### JSON Format
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

### XML Format
```xml
<?xml version="1.0" encoding="utf-8"?>
<t3hauler_export>
  <metadata>
    <created_at>1701432000</created_at>
    <format_version>1.0</format_version>
    <total_records>3</total_records>
  </metadata>
  <records>
    <table name="pages">
      <record uid="1"/>
    </table>
    <table name="tt_content">
      <record uid="101"/>
      <record uid="102"/>
    </table>
  </records>
  <relations>
    <relation from="tt_content:101" field="pid" to_table="pages" to_uid="1"/>
    <relation from="tt_content:102" field="pid" to_table="pages" to_uid="1"/>
  </relations>
</t3hauler_export>
```

### YAML Format
```yaml
# T3Hauler Export File
# Generated: 2024-12-01 12:00:00

metadata:
  created_at: 1701432000
  format_version: "1.0"
  total_records: 3

records:
  pages:
    - 1
  tt_content:
    - 101
    - 102

relations:
  "tt_content:101":
    - field: "pid"
      to_table: "pages"
      to_uid: 1
  "tt_content:102":
    - field: "pid" 
      to_table: "pages"
      to_uid: 1
```

## Migration Workflow

1. **Detection**: Identify changed tables since last snapshot
2. **Export**: Create structured export with dependency resolution
3. **Package**: Combine export with metadata for migration
4. **Transfer**: Deploy migration files to target environment
5. **Import**: Apply changes with integrity validation
6. **Verify**: Confirm successful import and update baselines

This format ensures reliable, ordered data migration while maintaining TYPO3 relationships and referential integrity.