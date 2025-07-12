<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Command\Option;

use DWenzel\T3extensionTools\Command\Option\InputOptionInterface;
use DWenzel\T3extensionTools\Traits\Command\Option\InputOptionTrait;
use Symfony\Component\Console\Input\InputOption;

/**
 * Option for filtering results
 */
class FilterOption implements InputOptionInterface
{
    use InputOptionTrait;

    public const string NAME = 'filter';
    public const string HELP = 'Filter snapshots by name pattern';
    public const int MODE = InputOption::VALUE_OPTIONAL;
    public const string DESCRIPTION = 'Filter pattern';
    public const ?string SHORTCUT = null;
    public const ?string DEFAULT = null;
}
