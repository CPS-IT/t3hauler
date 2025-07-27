<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Traits\Command;

use Cpsit\T3hauler\Command\Option\AuthorOption;
use Cpsit\T3hauler\Command\Option\DryRunOption;
use Cpsit\T3hauler\Command\Option\FilterOption;
use Cpsit\T3hauler\Command\Option\ForceOption;
use Cpsit\T3hauler\Command\Option\FormatOption;
use Cpsit\T3hauler\Command\Option\IdentifierOption;
use Cpsit\T3hauler\Command\Option\LimitOption;
use Cpsit\T3hauler\Command\Option\MigrationVersionOption;
use Cpsit\T3hauler\Command\Option\PathOption;
use Cpsit\T3hauler\Command\Option\SiteOption;
use Cpsit\T3hauler\Command\Option\StatusOption;
use Cpsit\T3hauler\Command\Option\TableOption;
use Cpsit\T3hauler\Command\Option\ValidateOption;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Provides standardized option extraction methods
 */
trait CommandOptionsTrait
{
    /**
     * Extract dry-run option safely
     */
    protected function isDryRun(InputInterface $input): bool
    {
        return (bool)$input->getOption(DryRunOption::NAME);
    }

    /**
     * Extract force option safely
     */
    protected function isForced(InputInterface $input): bool
    {
        return (bool)$input->getOption(ForceOption::NAME);
    }

    /**
     * Alias for isForced for consistent naming
     */
    protected function isForceMode(InputInterface $input): bool
    {
        return $this->isForced($input);
    }

    /**
     * Extract validate option safely
     */
    protected function shouldValidate(InputInterface $input): bool
    {
        return (bool)$input->getOption(ValidateOption::NAME);
    }

    /**
     * Alias for shouldValidate for consistent naming
     */
    protected function isValidateEnabled(InputInterface $input): bool
    {
        return $this->shouldValidate($input);
    }

    /**
     * Extract limit option with validation
     */
    protected function getLimit(InputInterface $input): ?int
    {
        $limit = $input->getOption(LimitOption::NAME);

        if ($limit === null || $limit === '' || $limit === false) {
            return null;
        }

        $limitInt = (int)$limit;

        if ($limitInt <= 0) {
            throw new \InvalidArgumentException('Limit must be a positive integer', 2255118346);
        }

        return $limitInt;
    }

    /**
     * Extract filter option safely
     */
    protected function getFilter(InputInterface $input): ?string
    {
        $filter = $input->getOption(FilterOption::NAME);

        if ($filter === null || $filter === '' || $filter === false) {
            return null;
        }

        $trimmed = trim((string)$filter);
        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * Extract identifier option safely
     */
    protected function getIdentifier(InputInterface $input): ?string
    {
        $identifier = $input->getOption(IdentifierOption::NAME);

        return $identifier ? trim((string)$identifier) : null;
    }

    /**
     * Extract migration version option safely
     */
    protected function getMigrationVersion(InputInterface $input): ?string
    {
        $version = $input->getOption(MigrationVersionOption::NAME);

        return $version ? trim((string)$version) : null;
    }

    /**
     * Extract author option safely
     */
    protected function getAuthor(InputInterface $input): ?string
    {
        $author = $input->getOption(AuthorOption::NAME);

        return $author ? trim((string)$author) : null;
    }

    /**
     * Extract site option safely
     */
    protected function getSite(InputInterface $input): ?string
    {
        $site = $input->getOption(SiteOption::NAME);

        return $site ? trim((string)$site) : null;
    }

    /**
     * Extract table option safely
     */
    protected function getTable(InputInterface $input): ?string
    {
        $table = $input->getOption(TableOption::NAME);

        return $table ? trim((string)$table) : null;
    }

    /**
     * Extract format option safely
     */
    protected function getFormat(InputInterface $input): ?string
    {
        $format = $input->getOption(FormatOption::NAME);

        return $format ? trim((string)$format) : null;
    }

    /**
     * Extract status option safely
     */
    protected function getStatus(InputInterface $input): ?string
    {
        $status = $input->getOption(StatusOption::NAME);

        return $status ? trim((string)$status) : null;
    }

    /**
     * Extract path option safely
     */
    protected function getPath(InputInterface $input): ?string
    {
        $path = $input->getOption(PathOption::NAME);

        return $path ? trim((string)$path) : null;
    }

    /**
     * Extract common options as array for bulk processing
     */
    protected function extractCommonOptions(InputInterface $input): array
    {
        return [
            'dry_run' => $this->isDryRun($input),
            'force' => $this->isForced($input),
            'validate' => $this->shouldValidate($input),
            'limit' => $this->getLimit($input),
            'filter' => $this->getFilter($input),
            'identifier' => $this->getIdentifier($input),
            'migration_version' => $this->getMigrationVersion($input),
            'author' => $this->getAuthor($input),
            'site' => $this->getSite($input),
            'table' => $this->getTable($input),
            'format' => $this->getFormat($input),
            'status' => $this->getStatus($input),
            'path' => $this->getPath($input),
        ];
    }
}
