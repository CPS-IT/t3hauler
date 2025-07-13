<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Tests\Unit\Domain\Model;

use Cpsit\T3hauler\Domain\Enumeration\RecordChangeType;
use Cpsit\T3hauler\Domain\Model\ChangeRecord;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ChangeRecord domain model
 */
final class ChangeRecordTest extends TestCase
{
    private ChangeRecord $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new ChangeRecord();
    }

    #[Test]
    public function getUidReturnsNullByDefault(): void
    {
        self::assertNull($this->subject->getUid());
    }

    #[Test]
    public function setUidSetsAndReturnsUid(): void
    {
        $uid = 123;
        $this->subject->setUid($uid);
        self::assertSame($uid, $this->subject->getUid());
    }

    #[Test]
    public function getSnapshotUidReturnsZeroByDefault(): void
    {
        self::assertSame(0, $this->subject->getSnapshotUid());
    }

    #[Test]
    public function setSnapshotUidSetsAndReturnsSnapshotUid(): void
    {
        $snapshotUid = 456;
        $this->subject->setSnapshotUid($snapshotUid);
        self::assertSame($snapshotUid, $this->subject->getSnapshotUid());
    }

    #[Test]
    public function getTableNameReturnsEmptyStringByDefault(): void
    {
        self::assertSame('', $this->subject->getTableName());
    }

    #[Test]
    public function setTableNameSetsAndReturnsTableName(): void
    {
        $tableName = 'pages';
        $this->subject->setTableName($tableName);
        self::assertSame($tableName, $this->subject->getTableName());
    }

    #[Test]
    public function getRecordUidReturnsZeroByDefault(): void
    {
        self::assertSame(0, $this->subject->getRecordUid());
    }

    #[Test]
    public function setRecordUidSetsAndReturnsRecordUid(): void
    {
        $recordUid = 789;
        $this->subject->setRecordUid($recordUid);
        self::assertSame($recordUid, $this->subject->getRecordUid());
    }

    #[Test]
    public function getRecordPidReturnsZeroByDefault(): void
    {
        self::assertSame(0, $this->subject->getRecordPid());
    }

    #[Test]
    public function setRecordPidSetsAndReturnsRecordPid(): void
    {
        $recordPid = 12;
        $this->subject->setRecordPid($recordPid);
        self::assertSame($recordPid, $this->subject->getRecordPid());
    }

    #[Test]
    public function getChangeTypeReturnsUpdateByDefault(): void
    {
        self::assertSame(RecordChangeType::UPDATE, $this->subject->getChangeType());
    }

    #[Test]
    public function setChangeTypeSetsAndReturnsChangeType(): void
    {
        $changeType = RecordChangeType::INSERT;
        $this->subject->setChangeType($changeType);
        self::assertSame($changeType, $this->subject->getChangeType());
    }

    #[Test]
    public function getFieldChangesReturnsEmptyStringByDefault(): void
    {
        self::assertSame('', $this->subject->getFieldChanges());
    }

    #[Test]
    public function setFieldChangesSetsAndReturnsFieldChanges(): void
    {
        $fieldChanges = '{"title": {"old": "Old", "new": "New"}}';
        $this->subject->setFieldChanges($fieldChanges);
        self::assertSame($fieldChanges, $this->subject->getFieldChanges());
    }

    #[Test]
    public function getFieldChangesArrayReturnsEmptyArrayForEmptyString(): void
    {
        self::assertSame([], $this->subject->getFieldChangesArray());
    }

    #[Test]
    public function getFieldChangesArrayReturnsDecodedJsonArray(): void
    {
        $fieldChanges = ['title' => ['old' => 'Old', 'new' => 'New']];
        $this->subject->setFieldChanges(json_encode($fieldChanges, JSON_THROW_ON_ERROR));
        self::assertSame($fieldChanges, $this->subject->getFieldChangesArray());
    }

    #[Test]
    public function getFieldChangesArrayReturnsEmptyArrayForInvalidJson(): void
    {
        $this->subject->setFieldChanges('invalid json');
        self::assertSame([], $this->subject->getFieldChangesArray());
    }

    #[Test]
    public function getRecordHashReturnsEmptyStringByDefault(): void
    {
        self::assertSame('', $this->subject->getRecordHash());
    }

    #[Test]
    public function setRecordHashSetsAndReturnsRecordHash(): void
    {
        $recordHash = 'abc123def456';
        $this->subject->setRecordHash($recordHash);
        self::assertSame($recordHash, $this->subject->getRecordHash());
    }

    #[Test]
    public function getPreviousHashReturnsNullByDefault(): void
    {
        self::assertNull($this->subject->getPreviousHash());
    }

    #[Test]
    public function setPreviousHashSetsAndReturnsPreviousHash(): void
    {
        $previousHash = 'xyz789abc123';
        $this->subject->setPreviousHash($previousHash);
        self::assertSame($previousHash, $this->subject->getPreviousHash());
    }

    #[Test]
    public function getDetectedAtReturnsZeroByDefault(): void
    {
        self::assertSame(0, $this->subject->getDetectedAt());
    }

    #[Test]
    public function setDetectedAtSetsAndReturnsDetectedAt(): void
    {
        $detectedAt = time();
        $this->subject->setDetectedAt($detectedAt);
        self::assertSame($detectedAt, $this->subject->getDetectedAt());
    }

    #[Test]
    public function getBeUserReturnsZeroByDefault(): void
    {
        self::assertSame(0, $this->subject->getBeUser());
    }

    #[Test]
    public function setBeUserSetsAndReturnsBeUser(): void
    {
        $beUser = 1;
        $this->subject->setBeUser($beUser);
        self::assertSame($beUser, $this->subject->getBeUser());
    }

    #[Test]
    public function getWorkspaceReturnsZeroByDefault(): void
    {
        self::assertSame(0, $this->subject->getWorkspace());
    }

    #[Test]
    public function setWorkspaceSetsAndReturnsWorkspace(): void
    {
        $workspace = 1;
        $this->subject->setWorkspace($workspace);
        self::assertSame($workspace, $this->subject->getWorkspace());
    }

    #[Test]
    public function getLanguageUidReturnsZeroByDefault(): void
    {
        self::assertSame(0, $this->subject->getLanguageUid());
    }

    #[Test]
    public function setLanguageUidSetsAndReturnsLanguageUid(): void
    {
        $languageUid = 1;
        $this->subject->setLanguageUid($languageUid);
        self::assertSame($languageUid, $this->subject->getLanguageUid());
    }

    #[Test]
    public function getCorrelationIdReturnsEmptyStringByDefault(): void
    {
        self::assertSame('', $this->subject->getCorrelationId());
    }

    #[Test]
    public function setCorrelationIdSetsAndReturnsCorrelationId(): void
    {
        $correlationId = 't3h_abc123';
        $this->subject->setCorrelationId($correlationId);
        self::assertSame($correlationId, $this->subject->getCorrelationId());
    }

    #[Test]
    public function isInsertReturnsTrueForInsertChangeType(): void
    {
        $this->subject->setChangeType(RecordChangeType::INSERT);
        self::assertTrue($this->subject->isInsert());
    }

    #[Test]
    public function isInsertReturnsFalseForNonInsertChangeType(): void
    {
        $this->subject->setChangeType(RecordChangeType::UPDATE);
        self::assertFalse($this->subject->isInsert());
    }

    #[Test]
    public function isUpdateReturnsTrueForUpdateChangeType(): void
    {
        $this->subject->setChangeType(RecordChangeType::UPDATE);
        self::assertTrue($this->subject->isUpdate());
    }

    #[Test]
    public function isUpdateReturnsFalseForNonUpdateChangeType(): void
    {
        $this->subject->setChangeType(RecordChangeType::INSERT);
        self::assertFalse($this->subject->isUpdate());
    }

    #[Test]
    public function isDeleteReturnsTrueForDeleteChangeType(): void
    {
        $this->subject->setChangeType(RecordChangeType::DELETE);
        self::assertTrue($this->subject->isDelete());
    }

    #[Test]
    public function isDeleteReturnsFalseForNonDeleteChangeType(): void
    {
        $this->subject->setChangeType(RecordChangeType::UPDATE);
        self::assertFalse($this->subject->isDelete());
    }

    #[Test]
    public function isMoveReturnsTrueForMoveChangeType(): void
    {
        $this->subject->setChangeType(RecordChangeType::MOVE);
        self::assertTrue($this->subject->isMove());
    }

    #[Test]
    public function isMoveReturnsFalseForNonMoveChangeType(): void
    {
        $this->subject->setChangeType(RecordChangeType::UPDATE);
        self::assertFalse($this->subject->isMove());
    }

    #[Test]
    public function toStringReturnsFormattedString(): void
    {
        $this->subject->setChangeType(RecordChangeType::INSERT);
        $this->subject->setTableName('pages');
        $this->subject->setRecordUid(123);
        $this->subject->setDetectedAt(1701432000);

        $expected = 'Insert pages:123 (2023-12-01 12:00:00)';
        self::assertSame($expected, (string)$this->subject);
    }
}
