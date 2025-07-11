<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Tests\Unit\Domain\Dto;

use Cpsit\T3hauler\Domain\Dto\ChangeDetectionResult;
use Cpsit\T3hauler\Domain\Dto\ChangesSummary;
use Cpsit\T3hauler\Domain\Dto\TableChanges;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ChangesSummary DTO
 */
final class ChangesSummaryTest extends TestCase
{
    #[Test]
    public function constructorSetsPropertiesCorrectly(): void
    {
        $totalTables = 3;
        $changedTables = ['pages'];
        $unchangedTables = ['tt_content'];
        $noBaselineTables = ['tt_address'];
        $hasChanges = true;

        $summary = new ChangesSummary(
            $totalTables,
            $changedTables,
            $unchangedTables,
            $noBaselineTables,
            $hasChanges
        );

        self::assertSame($totalTables, $summary->totalTables);
        self::assertSame($changedTables, $summary->changedTables);
        self::assertSame($unchangedTables, $summary->unchangedTables);
        self::assertSame($noBaselineTables, $summary->noBaselineTables);
        self::assertSame($hasChanges, $summary->hasChanges);
    }

    #[Test]
    public function fromChangeDetectionResultCreatesCorrectSummary(): void
    {
        $tableChanges = [
            TableChanges::createChanged('pages', 'hash1', 'hash2'),
            TableChanges::createUnchanged('tt_content', 'hash3', 'hash3'),
            TableChanges::createNoBaseline('tt_address', 'hash4'),
        ];

        $result = ChangeDetectionResult::fromTableChanges($tableChanges);
        $summary = ChangesSummary::fromChangeDetectionResult($result);

        self::assertSame(3, $summary->totalTables);
        self::assertSame(['pages'], $summary->changedTables);
        self::assertSame(['tt_content'], $summary->unchangedTables);
        self::assertSame(['tt_address'], $summary->noBaselineTables);
        self::assertTrue($summary->hasChanges);
    }

    #[Test]
    public function fromChangeDetectionResultWithNoChangesCreatesCorrectSummary(): void
    {
        $tableChanges = [
            TableChanges::createUnchanged('pages', 'hash1', 'hash1'),
            TableChanges::createUnchanged('tt_content', 'hash2', 'hash2'),
        ];

        $result = ChangeDetectionResult::fromTableChanges($tableChanges);
        $summary = ChangesSummary::fromChangeDetectionResult($result);

        self::assertSame(2, $summary->totalTables);
        self::assertEmpty($summary->changedTables);
        self::assertSame(['pages', 'tt_content'], $summary->unchangedTables);
        self::assertEmpty($summary->noBaselineTables);
        self::assertFalse($summary->hasChanges);
    }

    #[Test]
    public function fromChangeDetectionResultWithEmptyResultCreatesCorrectSummary(): void
    {
        $result = ChangeDetectionResult::createEmpty();
        $summary = ChangesSummary::fromChangeDetectionResult($result);

        self::assertSame(0, $summary->totalTables);
        self::assertEmpty($summary->changedTables);
        self::assertEmpty($summary->unchangedTables);
        self::assertEmpty($summary->noBaselineTables);
        self::assertFalse($summary->hasChanges);
    }

    #[Test]
    public function fromChangeDetectionResultWithOnlyNoBaselineCreatesCorrectSummary(): void
    {
        $tableChanges = [
            TableChanges::createNoBaseline('pages', 'hash1'),
            TableChanges::createNoBaseline('tt_content', 'hash2'),
        ];

        $result = ChangeDetectionResult::fromTableChanges($tableChanges);
        $summary = ChangesSummary::fromChangeDetectionResult($result);

        self::assertSame(2, $summary->totalTables);
        self::assertEmpty($summary->changedTables);
        self::assertEmpty($summary->unchangedTables);
        self::assertSame(['pages', 'tt_content'], $summary->noBaselineTables);
        self::assertTrue($summary->hasChanges); // no_baseline is treated as changes
    }
}
