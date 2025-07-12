<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Command\Argument;

use DWenzel\T3extensionTools\Command\Argument\InputArgumentInterface;
use DWenzel\T3extensionTools\Traits\Command\Argument\InputArgumentTrait;
use Symfony\Component\Console\Input\InputArgument;

/**
 * Argument for migration description
 */
class DescriptionArgument implements InputArgumentInterface
{
    use InputArgumentTrait;

    public const string NAME = 'description';
    public const string HELP = 'Description of the migration';
    public const int MODE = InputArgument::REQUIRED;
    public const string DESCRIPTION = 'Description of the migration';
    public const ?string DEFAULT = null;
}
