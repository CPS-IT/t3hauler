<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Command\Option;

use DWenzel\T3extensionTools\Command\Option\InputOptionInterface;
use DWenzel\T3extensionTools\Traits\Command\Option\InputOptionTrait;
use Symfony\Component\Console\Input\InputOption;

/**
 * Option for migration author
 */
class AuthorOption implements InputOptionInterface
{
    use InputOptionTrait;

    public const string NAME = 'author';
    public const string HELP = 'Author of the migration';
    public const int MODE = InputOption::VALUE_OPTIONAL;
    public const string DESCRIPTION = 'Author of the migration';
    public const string SHORTCUT = 'a';
    public const ?string DEFAULT = 'Developer';
}
