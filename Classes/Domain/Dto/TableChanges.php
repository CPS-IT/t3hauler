<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Domain\Dto;

use Cpsit\T3hauler\Domain\Enumeration\TableStatus;

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
 * Data transfer object for table change detection results
 */
readonly class TableChanges
{
    public function __construct(
        public string $tableName,
        public TableStatus $status,
        public bool $hasChanges,
        public string $message,
        public string $currentHash,
        public ?string $baselineHash = null,
        public ?\DateTimeImmutable $baselineCreatedAt = null
    ) {}

    /**
     * Create TableChanges for a table with no baseline
     */
    public static function createNoBaseline(string $tableName, string $currentHash): self
    {
        return new self(
            tableName: $tableName,
            status: TableStatus::NO_BASELINE,
            hasChanges: true,
            message: "No baseline snapshot found for table {$tableName}",
            currentHash: $currentHash,
            baselineHash: null,
            baselineCreatedAt: null
        );
    }

    /**
     * Create TableChanges for a table with changes
     */
    public static function createChanged(
        string $tableName,
        string $currentHash,
        string $baselineHash,
        ?\DateTimeImmutable $baselineCreatedAt = null
    ): self {
        return new self(
            tableName: $tableName,
            status: TableStatus::CHANGED,
            hasChanges: true,
            message: "Changes detected in table {$tableName}",
            currentHash: $currentHash,
            baselineHash: $baselineHash,
            baselineCreatedAt: $baselineCreatedAt
        );
    }

    /**
     * Create TableChanges for a table without changes
     */
    public static function createUnchanged(
        string $tableName,
        string $currentHash,
        string $baselineHash,
        ?\DateTimeImmutable $baselineCreatedAt = null
    ): self {
        return new self(
            tableName: $tableName,
            status: TableStatus::UNCHANGED,
            hasChanges: false,
            message: "No changes detected in table {$tableName}",
            currentHash: $currentHash,
            baselineHash: $baselineHash,
            baselineCreatedAt: $baselineCreatedAt
        );
    }
}
