<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Domain\Validator;

use Cpsit\T3hauler\Domain\Dto\ExportValidationResult;
use Cpsit\T3hauler\Domain\Enumeration\ExportValidationStatus;
use Cpsit\T3hauler\Service\FilesystemInterface;
use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;

/**
 * Validator for export JSON files
 *
 * Validates export JSON files against the t3hauler export schema.
 * Always uses the schema from Resources/Public/Spec/export.schema.json.
 */
final readonly class ExportJsonValidator
{
    public function __construct(
        private FilesystemInterface $filesystem
    ) {}

    public const string MESSAGE_FILE_NOT_FOUND = 'Export file not found';
    public const string MESSAGE_FILE_EMPTY = 'Export file is empty';
    public const string MESSAGE_INVALID_JSON = 'Export file contains invalid JSON';
    public const string MESSAGE_SCHEMA_NOT_FOUND = 'Export schema file not found';
    public const string MESSAGE_SCHEMA_INVALID = 'Export schema file is invalid';
    public const string MESSAGE_SCHEMA_VALIDATION_FAILED = 'Export file does not conform to schema';
    public const string MESSAGE_VALIDATION_SUCCESS = 'Export file is valid';

    private const string SCHEMA_PATH = __DIR__ . '/../../../Resources/Public/Spec/export.schema.json';

    /**
     * Validates an export JSON file against the export schema
     */
    public function validate(string $filePath): ExportValidationResult
    {
        if (!$this->filesystem->exists($filePath)) {
            return ExportValidationResult::invalid(
                ExportValidationStatus::FILE_NOT_FOUND,
                self::MESSAGE_FILE_NOT_FOUND . ': ' . $filePath
            );
        }

        $content = $this->filesystem->getFileContents($filePath);
        if ($content === false || $content === '') {
            return ExportValidationResult::invalid(
                ExportValidationStatus::FILE_EMPTY,
                self::MESSAGE_FILE_EMPTY
            );
        }

        // Parse JSON
        try {
            $data = json_decode($content, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return ExportValidationResult::invalid(
                ExportValidationStatus::INVALID_JSON,
                self::MESSAGE_INVALID_JSON . ': ' . $e->getMessage()
            );
        }

        // Validate against the export schema
        $schemaValidation = $this->validateWithSchema($data);
        if (!$schemaValidation->isValid()) {
            return $schemaValidation;
        }

        // Calculate record count and metadata
        $dataArray = json_decode($content, true);
        $recordCount = $this->calculateRecordCount($dataArray['records'] ?? []);
        $metadata = $dataArray['metadata'] ?? [];

        return ExportValidationResult::valid(
            self::MESSAGE_VALIDATION_SUCCESS,
            $this->filesystem->getFileSize($filePath) ?: 0,
            $recordCount,
            'json',
            $metadata
        );
    }

    /**
     * Validates data against the export JSON Schema
     */
    private function validateWithSchema(mixed $data): ExportValidationResult
    {
        if (!$this->filesystem->exists(self::SCHEMA_PATH)) {
            return ExportValidationResult::invalid(
                ExportValidationStatus::INVALID_STRUCTURE,
                self::MESSAGE_SCHEMA_NOT_FOUND . ': ' . self::SCHEMA_PATH
            );
        }

        $schemaContent = $this->filesystem->getFileContents(self::SCHEMA_PATH);
        if ($schemaContent === false) {
            return ExportValidationResult::invalid(
                ExportValidationStatus::INVALID_STRUCTURE,
                self::MESSAGE_SCHEMA_NOT_FOUND . ': Cannot read ' . self::SCHEMA_PATH
            );
        }

        try {
            $schema = json_decode($schemaContent, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return ExportValidationResult::invalid(
                ExportValidationStatus::INVALID_STRUCTURE,
                self::MESSAGE_SCHEMA_INVALID . ': ' . $e->getMessage()
            );
        }

        $validator = new Validator();
        $validator->validate($data, $schema, Constraint::CHECK_MODE_COERCE_TYPES);

        if (!$validator->isValid()) {
            $errors = [];
            foreach ($validator->getErrors() as $error) {
                $errors[] = sprintf('[%s] %s', $error['property'], $error['message']);
            }

            return ExportValidationResult::invalid(
                ExportValidationStatus::INVALID_STRUCTURE,
                self::MESSAGE_SCHEMA_VALIDATION_FAILED . ': ' . implode(', ', $errors)
            );
        }

        return ExportValidationResult::valid(
            'Schema validation successful',
            0,
            0,
            'json',
            []
        );
    }

    /**
     * Calculates the total number of records in the export
     */
    private function calculateRecordCount(array $records): int
    {
        $count = 0;
        foreach ($records as $tableRecords) {
            if (is_array($tableRecords)) {
                $count += count($tableRecords);
            }
        }
        return $count;
    }
}
