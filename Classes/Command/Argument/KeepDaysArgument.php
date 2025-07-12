<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Command\Argument;

use DWenzel\T3extensionTools\Command\Argument\InputArgumentInterface;
use DWenzel\T3extensionTools\Traits\Command\Argument\InputArgumentTrait;
use Symfony\Component\Console\Input\InputArgument;

/**
 * Argument for number of days to keep snapshots
 */
class KeepDaysArgument implements InputArgumentInterface
{
    use InputArgumentTrait;

    public const string NAME = 'keep-days';
    public const string HELP = 'Number of days to keep snapshots';
    public const int MODE = InputArgument::OPTIONAL;
    public const string DESCRIPTION = 'Number of days to keep snapshots';
    public const ?string DEFAULT = '1';
}
