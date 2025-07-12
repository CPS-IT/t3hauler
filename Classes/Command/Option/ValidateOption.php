<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Command\Option;

use DWenzel\T3extensionTools\Command\Option\InputOptionInterface;
use DWenzel\T3extensionTools\Traits\Command\Option\InputOptionTrait;
use Symfony\Component\Console\Input\InputOption;

/**
 * Option for enabling validation
 */
class ValidateOption implements InputOptionInterface
{
    use InputOptionTrait;

    public const string NAME = 'validate';
    public const string HELP = 'Validate the migration file before applying';
    public const int MODE = InputOption::VALUE_NONE;
    public const string DESCRIPTION = 'Enable validation';
    public const string SHORTCUT = 'v';
    public const ?string DEFAULT = null;
}
