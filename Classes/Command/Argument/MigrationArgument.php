<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Command\Argument;

use DWenzel\T3extensionTools\Command\Argument\InputArgumentInterface;
use DWenzel\T3extensionTools\Traits\Command\Argument\InputArgumentTrait;
use Symfony\Component\Console\Input\InputArgument;

/**
 * Argument for migration identifier or version
 */
class MigrationArgument implements InputArgumentInterface
{
    use InputArgumentTrait;

    public const string NAME = 'migration';
    public const string HELP = 'Migration identifier or version to apply';
    public const int MODE = InputArgument::REQUIRED;
    public const string DESCRIPTION = 'Migration identifier or version';
    public const ?string DEFAULT = null;
}
