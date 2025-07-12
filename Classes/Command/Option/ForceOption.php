<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Command\Option;

use DWenzel\T3extensionTools\Command\Option\InputOptionInterface;
use DWenzel\T3extensionTools\Traits\Command\Option\InputOptionTrait;
use Symfony\Component\Console\Input\InputOption;

/**
 * Option for forcing operations
 */
class ForceOption implements InputOptionInterface
{
    use InputOptionTrait;

    public const string NAME = 'force';
    public const string HELP = 'Force the operation, bypassing safety checks';
    public const int MODE = InputOption::VALUE_NONE;
    public const string DESCRIPTION = 'Force operation';
    public const string SHORTCUT = 'f';
    public const ?string DEFAULT = null;
}
