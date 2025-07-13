<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Command\Option;

use DWenzel\T3extensionTools\Command\Option\InputOptionInterface;
use DWenzel\T3extensionTools\Traits\Command\Option\InputOptionTrait;
use Symfony\Component\Console\Input\InputOption;

/**
 * Option for showing detailed information
 */
class DetailsOption implements InputOptionInterface
{
    use InputOptionTrait;

    public const string NAME = 'details';
    public const string HELP = 'Show detailed information including table breakdown';
    public const int MODE = InputOption::VALUE_NONE;
    public const string DESCRIPTION = 'Show details';
    public const string SHORTCUT = '';
    public const ?string DEFAULT = null;
}
