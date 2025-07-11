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
 * Data transfer object for change detection summary
 */
readonly class ChangesSummary
{
    /**
     * @param array<string> $changedTables
     * @param array<string> $unchangedTables
     * @param array<string> $noBaselineTables
     */
    public function __construct(
        public int $totalTables,
        public array $changedTables,
        public array $unchangedTables,
        public array $noBaselineTables,
        public bool $hasChanges
    ) {}

    /**
     * Create ChangesSummary from a ChangeDetectionResult
     */
    public static function fromChangeDetectionResult(ChangeDetectionResult $result): self
    {
        $changedTables = [];
        $unchangedTables = [];
        $noBaselineTables = [];

        foreach ($result->tableChanges as $tableChanges) {
            switch ($tableChanges->status) {
                case \Cpsit\T3hauler\Domain\Enumeration\TableStatus::CHANGED:
                    $changedTables[] = $tableChanges->tableName;
                    break;
                case \Cpsit\T3hauler\Domain\Enumeration\TableStatus::UNCHANGED:
                    $unchangedTables[] = $tableChanges->tableName;
                    break;
                case \Cpsit\T3hauler\Domain\Enumeration\TableStatus::NO_BASELINE:
                    $noBaselineTables[] = $tableChanges->tableName;
                    break;
            }
        }

        return new self(
            totalTables: count($result->tableChanges),
            changedTables: $changedTables,
            unchangedTables: $unchangedTables,
            noBaselineTables: $noBaselineTables,
            hasChanges: !empty($changedTables) || !empty($noBaselineTables)
        );
    }
}
