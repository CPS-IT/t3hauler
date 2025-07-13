<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Command\Option;

use DWenzel\T3extensionTools\Command\Option\InputOptionInterface;
use DWenzel\T3extensionTools\Traits\Command\Option\InputOptionTrait;
use Symfony\Component\Console\Input\InputOption;

/**
 * Option for path filtering
 */
class PathOption implements InputOptionInterface
{
    use InputOptionTrait;

    public const string NAME = 'path';
    public const string HELP = 'Show migrations from specific path only';
    public const int MODE = InputOption::VALUE_OPTIONAL;
    public const string DESCRIPTION = 'Path filter';
    public const string SHORTCUT = 'p';
    public const ?string DEFAULT = null;
}
