<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Command\Option;

use DWenzel\T3extensionTools\Command\Option\InputOptionInterface;
use DWenzel\T3extensionTools\Traits\Command\Option\InputOptionTrait;
use Symfony\Component\Console\Input\InputOption;

/**
 * Option for filtering by status
 */
class StatusOption implements InputOptionInterface
{
    use InputOptionTrait;

    public const string NAME = 'status';
    public const string HELP = 'Filter by status (pending, applied, failed)';
    public const int MODE = InputOption::VALUE_OPTIONAL;
    public const string DESCRIPTION = 'Filter by status';
    public const string SHORTCUT = 's';
    public const ?string DEFAULT = null;
}
