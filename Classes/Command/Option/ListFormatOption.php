<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Command\Option;

use DWenzel\T3extensionTools\Command\Option\InputOptionInterface;
use DWenzel\T3extensionTools\Traits\Command\Option\InputOptionTrait;
use Symfony\Component\Console\Input\InputOption;

/**
 * Option for list output format
 */
class ListFormatOption implements InputOptionInterface
{
    use InputOptionTrait;

    public const string NAME = 'format';
    public const string HELP = 'Output format (table, json)';
    public const int MODE = InputOption::VALUE_OPTIONAL;
    public const string DESCRIPTION = 'Output format';
    public const string SHORTCUT = 'f';
    public const string DEFAULT = 'table';
}
