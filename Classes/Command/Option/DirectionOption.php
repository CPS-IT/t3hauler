<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Command\Option;

use DWenzel\T3extensionTools\Command\Option\InputOptionInterface;
use DWenzel\T3extensionTools\Traits\Command\Option\InputOptionTrait;
use Symfony\Component\Console\Input\InputOption;

/**
 * Option for sort direction
 */
class DirectionOption implements InputOptionInterface
{
    use InputOptionTrait;

    public const string NAME = 'direction';
    public const string HELP = 'Sort direction (asc, desc)';
    public const int MODE = InputOption::VALUE_OPTIONAL;
    public const string DESCRIPTION = 'Sort direction';
    public const string SHORTCUT = 'd';
    public const string DEFAULT = 'desc';
}
