<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Command\Option;

use DWenzel\T3extensionTools\Command\Option\InputOptionInterface;
use DWenzel\T3extensionTools\Traits\Command\Option\InputOptionTrait;
use Symfony\Component\Console\Input\InputOption;

/**
 * Option for dry run mode
 */
class DryRunOption implements InputOptionInterface
{
    use InputOptionTrait;

    public const string NAME = 'dry-run';
    public const string HELP = 'Show what would be done without actually performing the operation';
    public const int MODE = InputOption::VALUE_NONE;
    public const string DESCRIPTION = 'Dry run mode';
    public const string SHORTCUT = 'd';
    public const ?string DEFAULT = null;
}
