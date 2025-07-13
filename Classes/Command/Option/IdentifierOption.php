<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Command\Option;

use DWenzel\T3extensionTools\Command\Option\InputOptionInterface;
use DWenzel\T3extensionTools\Traits\Command\Option\InputOptionTrait;
use Symfony\Component\Console\Input\InputOption;

/**
 * Option for custom snapshot/migration identifier
 */
class IdentifierOption implements InputOptionInterface
{
    use InputOptionTrait;

    public const string NAME = 'identifier';
    public const string HELP = 'Custom identifier for the snapshot or migration';
    public const int MODE = InputOption::VALUE_OPTIONAL;
    public const string DESCRIPTION = 'Custom identifier';
    public const string SHORTCUT = 'i';
    public const ?string DEFAULT = null;
}
