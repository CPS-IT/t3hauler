<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Command\Option;

use DWenzel\T3extensionTools\Command\Option\InputOptionInterface;
use DWenzel\T3extensionTools\Traits\Command\Option\InputOptionTrait;
use Symfony\Component\Console\Input\InputOption;

/**
 * Option for migration version
 */
class MigrationVersionOption implements InputOptionInterface
{
    use InputOptionTrait;

    public const string NAME = 'migration-version';
    public const string HELP = 'Migration version to associate with this operation';
    public const int MODE = InputOption::VALUE_OPTIONAL;
    public const string DESCRIPTION = 'Migration version';
    public const string SHORTCUT = 'm';
    public const ?string DEFAULT = null;
}
