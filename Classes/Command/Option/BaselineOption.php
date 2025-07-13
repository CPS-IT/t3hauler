<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Command\Option;

use DWenzel\T3extensionTools\Command\Option\InputOptionInterface;
use DWenzel\T3extensionTools\Traits\Command\Option\InputOptionTrait;
use Symfony\Component\Console\Input\InputOption;

/**
 * Option for baseline snapshot identifier
 */
class BaselineOption implements InputOptionInterface
{
    use InputOptionTrait;

    public const string NAME = 'baseline';
    public const string HELP = 'Specific baseline snapshot identifier to compare against';
    public const int MODE = InputOption::VALUE_OPTIONAL;
    public const string DESCRIPTION = 'Baseline snapshot identifier';
    public const string SHORTCUT = 'b';
    public const ?string DEFAULT = null;
}
