<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Command\Option;

use DWenzel\T3extensionTools\Command\Option\InputOptionInterface;
use DWenzel\T3extensionTools\Traits\Command\Option\InputOptionTrait;
use Symfony\Component\Console\Input\InputOption;

/**
 * Option for showing summary only
 */
class SummaryOption implements InputOptionInterface
{
    use InputOptionTrait;

    public const string NAME = 'summary';
    public const string HELP = 'Show only a summary of changes';
    public const int MODE = InputOption::VALUE_NONE;
    public const string DESCRIPTION = 'Show summary only';
    public const string SHORTCUT = 's';
    public const ?string DEFAULT = null;
}
