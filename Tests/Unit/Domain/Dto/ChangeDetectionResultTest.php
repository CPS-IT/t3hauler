<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Tests\Unit\Domain\Dto;

use Cpsit\T3hauler\Domain\Dto\ChangeDetectionResult;
use Cpsit\T3hauler\Domain\Dto\TableChanges;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ChangeDetectionResult DTO
 */
final class ChangeDetectionResultTest extends TestCase
{
    #[Test]
    public function constructorSetsPropertiesCorrectly(): void
    {
        $tableChanges = [
            'pages' => TableChanges::createChanged('pages', 'hash1', 'hash2'),
            'tt_content' => TableChanges::createUnchanged('tt_content', 'hash3', 'hash3'),
        ];
        $hasChanges = true;

        $result = new ChangeDetectionResult($tableChanges, $hasChanges);

        self::assertSame($tableChanges, $result->tableChanges);
        self::assertSame($hasChanges, $result->hasChanges);
    }

    #[Test]
    public function createEmptyCreatesCorrectInstance(): void
    {
        $result = ChangeDetectionResult::createEmpty();

        self::assertEmpty($result->tableChanges);
        self::assertFalse($result->hasChanges);
    }

    #[Test]
    public function fromTableChangesCreatesCorrectInstance(): void
    {
        $tableChanges = [
            TableChanges::createChanged('pages', 'hash1', 'hash2'),
            TableChanges::createUnchanged('tt_content', 'hash3', 'hash3'),
            TableChanges::createNoBaseline('tt_address', 'hash4'),
        ];

        $result = ChangeDetectionResult::fromTableChanges($tableChanges);

        self::assertCount(3, $result->tableChanges);
        self::assertTrue($result->hasChanges); // Because pages and tt_address have changes
        self::assertArrayHasKey('pages', $result->tableChanges);
        self::assertArrayHasKey('tt_content', $result->tableChanges);
        self::assertArrayHasKey('tt_address', $result->tableChanges);
    }

    #[Test]
    public function fromTableChangesWithNoChangesCreatesCorrectInstance(): void
    {
        $tableChanges = [
            TableChanges::createUnchanged('pages', 'hash1', 'hash1'),
            TableChanges::createUnchanged('tt_content', 'hash2', 'hash2'),
        ];

        $result = ChangeDetectionResult::fromTableChanges($tableChanges);

        self::assertCount(2, $result->tableChanges);
        self::assertFalse($result->hasChanges); // No changes
    }

    #[Test]
    public function getTableChangesReturnsCorrectResult(): void
    {
        $pagesChanges = TableChanges::createChanged('pages', 'hash1', 'hash2');
        $tableChanges = [
            'pages' => $pagesChanges,
        ];

        $result = new ChangeDetectionResult($tableChanges, true);

        self::assertSame($pagesChanges, $result->getTableChanges('pages'));
        self::assertNull($result->getTableChanges('nonexistent'));
    }

    #[Test]
    public function getTableNamesReturnsCorrectArray(): void
    {
        $tableChanges = [
            'pages' => TableChanges::createChanged('pages', 'hash1', 'hash2'),
            'tt_content' => TableChanges::createUnchanged('tt_content', 'hash3', 'hash3'),
        ];

        $result = new ChangeDetectionResult($tableChanges, true);

        $tableNames = $result->getTableNames();
        self::assertCount(2, $tableNames);
        self::assertContains('pages', $tableNames);
        self::assertContains('tt_content', $tableNames);
    }

    #[Test]
    public function hasTableChangesReturnsCorrectResult(): void
    {
        $tableChanges = [
            'pages' => TableChanges::createChanged('pages', 'hash1', 'hash2'),
            'tt_content' => TableChanges::createUnchanged('tt_content', 'hash3', 'hash3'),
        ];

        $result = new ChangeDetectionResult($tableChanges, true);

        self::assertTrue($result->hasTableChanges('pages'));
        self::assertFalse($result->hasTableChanges('tt_content'));
        self::assertFalse($result->hasTableChanges('nonexistent'));
    }
}
