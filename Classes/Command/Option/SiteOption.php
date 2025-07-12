<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Command\Option;

use DWenzel\T3extensionTools\Command\Option\InputOptionInterface;
use DWenzel\T3extensionTools\Traits\Command\Option\InputOptionTrait;
use Symfony\Component\Console\Input\InputOption;

/**
 * Option for site identifier
 */
class SiteOption implements InputOptionInterface
{
    use InputOptionTrait;

    public const string NAME = 'site';
    public const string HELP = 'Site identifier for multi-site setup';
    public const int MODE = InputOption::VALUE_OPTIONAL;
    public const string DESCRIPTION = 'Site identifier';
    public const string SHORTCUT = 's';
    public const ?string DEFAULT = null;
}
