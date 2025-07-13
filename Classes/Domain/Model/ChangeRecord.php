<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Domain\Model;

use Cpsit\T3hauler\Domain\Enumeration\RecordChangeType;

/**
 * Model for tracking individual record changes
 */
class ChangeRecord
{
    private ?int $uid = null;
    private int $snapshotUid = 0;
    private string $tableName = '';
    private int $recordUid = 0;
    private int $recordPid = 0;
    private RecordChangeType $changeType = RecordChangeType::UPDATE;
    private string $fieldChanges = '';
    private string $recordHash = '';
    private ?string $previousHash = null;
    private int $detectedAt = 0;
    private int $beUser = 0;
    private int $workspace = 0;
    private int $languageUid = 0;
    private string $correlationId = '';

    public function getUid(): ?int
    {
        return $this->uid;
    }

    public function setUid(?int $uid): void
    {
        $this->uid = $uid;
    }

    public function getSnapshotUid(): int
    {
        return $this->snapshotUid;
    }

    public function setSnapshotUid(int $snapshotUid): void
    {
        $this->snapshotUid = $snapshotUid;
    }

    public function getTableName(): string
    {
        return $this->tableName;
    }

    public function setTableName(string $tableName): void
    {
        $this->tableName = $tableName;
    }

    public function getRecordUid(): int
    {
        return $this->recordUid;
    }

    public function setRecordUid(int $recordUid): void
    {
        $this->recordUid = $recordUid;
    }

    public function getRecordPid(): int
    {
        return $this->recordPid;
    }

    public function setRecordPid(int $recordPid): void
    {
        $this->recordPid = $recordPid;
    }

    public function getChangeType(): RecordChangeType
    {
        return $this->changeType;
    }

    public function setChangeType(RecordChangeType $changeType): void
    {
        $this->changeType = $changeType;
    }

    public function getFieldChanges(): string
    {
        return $this->fieldChanges;
    }

    public function setFieldChanges(string $fieldChanges): void
    {
        $this->fieldChanges = $fieldChanges;
    }

    public function getFieldChangesArray(): array
    {
        if (empty($this->fieldChanges)) {
            return [];
        }

        try {
            return json_decode($this->fieldChanges, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return [];
        }
    }

    public function getRecordHash(): string
    {
        return $this->recordHash;
    }

    public function setRecordHash(string $recordHash): void
    {
        $this->recordHash = $recordHash;
    }

    public function getPreviousHash(): ?string
    {
        return $this->previousHash;
    }

    public function setPreviousHash(?string $previousHash): void
    {
        $this->previousHash = $previousHash;
    }

    public function getDetectedAt(): int
    {
        return $this->detectedAt;
    }

    public function setDetectedAt(int $detectedAt): void
    {
        $this->detectedAt = $detectedAt;
    }

    public function getBeUser(): int
    {
        return $this->beUser;
    }

    public function setBeUser(int $beUser): void
    {
        $this->beUser = $beUser;
    }

    public function getWorkspace(): int
    {
        return $this->workspace;
    }

    public function setWorkspace(int $workspace): void
    {
        $this->workspace = $workspace;
    }

    public function getLanguageUid(): int
    {
        return $this->languageUid;
    }

    public function setLanguageUid(int $languageUid): void
    {
        $this->languageUid = $languageUid;
    }

    public function getCorrelationId(): string
    {
        return $this->correlationId;
    }

    public function setCorrelationId(string $correlationId): void
    {
        $this->correlationId = $correlationId;
    }

    /**
     * Check if this is an insert operation
     */
    public function isInsert(): bool
    {
        return $this->changeType === RecordChangeType::INSERT;
    }

    /**
     * Check if this is an update operation
     */
    public function isUpdate(): bool
    {
        return $this->changeType === RecordChangeType::UPDATE;
    }

    /**
     * Check if this is a delete operation
     */
    public function isDelete(): bool
    {
        return $this->changeType === RecordChangeType::DELETE;
    }

    /**
     * Check if this is a move operation
     */
    public function isMove(): bool
    {
        return $this->changeType === RecordChangeType::MOVE;
    }

    /**
     * Get human-readable representation
     */
    public function __toString(): string
    {
        return sprintf(
            '%s %s:%d (%s)',
            ucfirst($this->changeType->value),
            $this->tableName,
            $this->recordUid,
            date('Y-m-d H:i:s', $this->detectedAt)
        );
    }

    /**
     * Get change type description
     */
    public function getChangeTypeDescription(): string
    {
        return $this->changeType->getDescription();
    }
}
