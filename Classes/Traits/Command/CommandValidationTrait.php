<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Traits\Command;

use Cpsit\T3hauler\Command\Argument\MigrationArgument;
use Cpsit\T3hauler\Command\Argument\DescriptionArgument;
use Cpsit\T3hauler\Command\Argument\KeepDaysArgument;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Provides standardized input validation methods
 */
trait CommandValidationTrait
{
    /**
     * Validate required argument exists and is not empty
     */
    protected function validateRequiredArgument(InputInterface $input, string $argumentName): string
    {
        $value = $input->getArgument($argumentName);

        if (empty($value)) {
            throw new \InvalidArgumentException("Required argument '{$argumentName}' cannot be empty");
        }

        return (string) $value;
    }

    /**
     * Get and validate migration argument
     */
    protected function getMigrationArgument(InputInterface $input): string
    {
        return $this->validateRequiredArgument($input, MigrationArgument::NAME);
    }

    /**
     * Get and validate description argument
     */
    protected function getDescriptionArgument(InputInterface $input): string
    {
        return $this->validateRequiredArgument($input, DescriptionArgument::NAME);
    }

    /**
     * Get and validate keep days argument
     */
    protected function getKeepDaysArgument(InputInterface $input): int
    {
        $value = $this->validateRequiredArgument($input, KeepDaysArgument::NAME);
        $days = (int) $value;

        if ($days <= 0) {
            throw new \InvalidArgumentException('Keep days must be a positive integer');
        }

        return $days;
    }

    /**
     * Validate migration ID format
     */
    protected function validateMigrationId(string $migrationId): string
    {
        if (!preg_match('/^\d{14}_[a-zA-Z0-9_]+$/', $migrationId)) {
            throw new \InvalidArgumentException("Invalid migration ID format: {$migrationId}");
        }

        return $migrationId;
    }

    /**
     * Validate options combination for safety
     */
    protected function validateOptionCombination(array $options): void
    {
        if ($options['force'] && !$options['validate'] && !$options['dry_run']) {
            throw new \InvalidArgumentException('Using --force without --validate or --dry-run is not recommended');
        }
    }

    /**
     * Validate file path exists and is readable
     */
    protected function validateFilePath(string $path): string
    {
        if (!file_exists($path)) {
            throw new \InvalidArgumentException("File does not exist: {$path}");
        }

        if (!is_readable($path)) {
            throw new \InvalidArgumentException("File is not readable: {$path}");
        }

        return $path;
    }

    /**
     * Validate directory path exists and is writable
     */
    protected function validateDirectoryPath(string $path): string
    {
        if (!is_dir($path)) {
            throw new \InvalidArgumentException("Directory does not exist: {$path}");
        }

        if (!is_writable($path)) {
            throw new \InvalidArgumentException("Directory is not writable: {$path}");
        }

        return $path;
    }

    /**
     * Validate numeric argument is positive
     */
    protected function validatePositiveInteger(string $value, string $fieldName = 'value'): int
    {
        $intValue = (int) $value;

        if ($intValue <= 0) {
            throw new \InvalidArgumentException("{$fieldName} must be a positive integer, got: {$value}");
        }

        return $intValue;
    }
}