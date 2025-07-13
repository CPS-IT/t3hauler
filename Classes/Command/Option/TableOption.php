<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Command\Option;

use DWenzel\T3extensionTools\Command\Option\InputOptionInterface;
use DWenzel\T3extensionTools\Traits\Command\Option\InputOptionTrait;
use Symfony\Component\Console\Input\InputOption;

/**
 * Option for specific table name
 */
class TableOption implements InputOptionInterface
{
    use InputOptionTrait;

    public const string NAME = 'table';
    public const string HELP = 'Check changes for a specific table only';
    public const int MODE = InputOption::VALUE_OPTIONAL;
    public const string DESCRIPTION = 'Specific table name';
    public const string SHORTCUT = 't';
    public const ?string DEFAULT = null;
}
