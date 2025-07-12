<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Command\Option;

use DWenzel\T3extensionTools\Command\Option\InputOptionInterface;
use DWenzel\T3extensionTools\Traits\Command\Option\InputOptionTrait;
use Symfony\Component\Console\Input\InputOption;

/**
 * Option for ordering results
 */
class OrderOption implements InputOptionInterface
{
    use InputOptionTrait;

    public const string NAME = 'order';
    public const string HELP = 'Order by (created_at, snapshot_id, hash)';
    public const int MODE = InputOption::VALUE_OPTIONAL;
    public const string DESCRIPTION = 'Order field';
    public const string SHORTCUT = 'o';
    public const string DEFAULT = 'created_at';
}
