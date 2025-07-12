<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Command\Option;

use DWenzel\T3extensionTools\Command\Option\InputOptionInterface;
use DWenzel\T3extensionTools\Traits\Command\Option\InputOptionTrait;
use Symfony\Component\Console\Input\InputOption;

/**
 * Option for export format
 */
class FormatOption implements InputOptionInterface
{
    use InputOptionTrait;

    public const string NAME = 'format';
    public const string HELP = 'Export format to use (json, xml, yaml)';
    public const int MODE = InputOption::VALUE_REQUIRED;
    public const string DESCRIPTION = 'Export format';
    public const ?string SHORTCUT = null;
    public const string DEFAULT = 'json';
}
