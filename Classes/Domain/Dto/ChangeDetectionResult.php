<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Domain\Dto;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2025 Dirk Wenzel <wenzel@cps-it.de>
 *  All rights reserved
 *
 * The GNU General Public License can be found at
 * http://www.gnu.org/copyleft/gpl.html.
 * A copy is found in the text file GPL.txt and important notices to the license
 * from the author is found in LICENSE.txt distributed with these scripts.
 * This script is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Data transfer object for change detection results across multiple tables
 */
readonly class ChangeDetectionResult
{
    /**
     * @param array<string, TableChanges> $tableChanges Keyed by table name
     */
    public function __construct(
        public array $tableChanges,
        public bool $hasChanges
    ) {}

    /**
     * Create an empty result
     */
    public static function createEmpty(): self
    {
        return new self(
            tableChanges: [],
            hasChanges: false
        );
    }

    /**
     * Create from array of TableChanges
     *
     * @param array<TableChanges> $tableChanges
     */
    public static function fromTableChanges(array $tableChanges): self
    {
        $keyed = [];
        $hasChanges = false;

        foreach ($tableChanges as $tableChange) {
            $keyed[$tableChange->tableName] = $tableChange;
            if ($tableChange->hasChanges) {
                $hasChanges = true;
            }
        }

        return new self(
            tableChanges: $keyed,
            hasChanges: $hasChanges
        );
    }

    /**
     * Get table changes for a specific table
     */
    public function getTableChanges(string $tableName): ?TableChanges
    {
        return $this->tableChanges[$tableName] ?? null;
    }

    /**
     * Get all table names
     *
     * @return array<string>
     */
    public function getTableNames(): array
    {
        return array_keys($this->tableChanges);
    }

    /**
     * Check if a specific table has changes
     */
    public function hasTableChanges(string $tableName): bool
    {
        $tableChanges = $this->getTableChanges($tableName);
        return $tableChanges->hasChanges ?? false;
    }
}
