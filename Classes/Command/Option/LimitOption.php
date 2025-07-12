<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Command\Option;

use DWenzel\T3extensionTools\Command\Option\InputOptionInterface;
use DWenzel\T3extensionTools\Traits\Command\Option\InputOptionTrait;
use Symfony\Component\Console\Input\InputOption;

/**
 * Option for limiting output
 */
class LimitOption implements InputOptionInterface
{
    use InputOptionTrait;

    public const string NAME = 'limit';
    public const string HELP = 'Limit the number of results shown';
    public const int MODE = InputOption::VALUE_OPTIONAL;
    public const string DESCRIPTION = 'Maximum number of results';
    public const string SHORTCUT = 'l';
    public const ?string DEFAULT = null;
}
