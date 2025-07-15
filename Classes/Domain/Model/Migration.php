<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Domain\Model;

use Cpsit\T3hauler\Domain\Enumeration\MigrationStatus;

/**
 * Domain model for t3hauler migrations
 *
 * Represents a migration set with metadata and data files
 */
class Migration
{
    private ?int $uid = null;
    private string $migrationId;
    private string $name;
    private string $description;
    private \DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $appliedAt = null;
    private string $author;
    private string $sourceHash;
    private ?string $targetHash = null;
    private MigrationStatus $status = MigrationStatus::PENDING;
    private string $dataFile;
    private array $metadata = [];

    public function __construct(
        string $migrationId,
        string $name,
        string $description,
        string $author,
        string $sourceHash,
        string $dataFile
    ) {
        $this->migrationId = $migrationId;
        $this->name = $name;
        $this->description = $description;
        $this->author = $author;
        $this->sourceHash = $sourceHash;
        $this->dataFile = $dataFile;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getUid(): ?int
    {
        return $this->uid;
    }

    public function setUid(int $uid): self
    {
        $this->uid = $uid;
        return $this;
    }

    public function getMigrationId(): string
    {
        return $this->migrationId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getAppliedAt(): ?\DateTimeImmutable
    {
        return $this->appliedAt;
    }

    public function setAppliedAt(?\DateTimeImmutable $appliedAt): self
    {
        $this->appliedAt = $appliedAt;
        return $this;
    }

    public function getAuthor(): string
    {
        return $this->author;
    }

    public function setAuthor(string $author): self
    {
        $this->author = $author;
        return $this;
    }

    public function getSourceHash(): string
    {
        return $this->sourceHash;
    }

    public function setSourceHash(string $sourceHash): self
    {
        $this->sourceHash = $sourceHash;
        return $this;
    }

    public function getTargetHash(): ?string
    {
        return $this->targetHash;
    }

    public function setTargetHash(?string $targetHash): self
    {
        $this->targetHash = $targetHash;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status->value;
    }

    public function getStatusEnum(): MigrationStatus
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        try {
            $this->status = MigrationStatus::from($status);
        } catch (\ValueError $e) {
            throw new \InvalidArgumentException("Invalid migration status: {$status}", 0, $e);
        }
        return $this;
    }

    public function setStatusEnum(MigrationStatus $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getDataFile(): string
    {
        return $this->dataFile;
    }

    public function setDataFile(string $dataFile): self
    {
        $this->dataFile = $dataFile;
        return $this;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function addMetadata(string $key, mixed $value): self
    {
        $this->metadata[$key] = $value;
        return $this;
    }

    public function removeMetadata(string $key): self
    {
        unset($this->metadata[$key]);
        return $this;
    }

    public function hasMetadata(string $key): bool
    {
        return array_key_exists($key, $this->metadata);
    }

    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    public function isPending(): bool
    {
        return $this->status->isPending();
    }

    public function isApplied(): bool
    {
        return $this->status->isSuccessful();
    }

    public function isFailed(): bool
    {
        return $this->status->isFailed();
    }

    public function isRolledBack(): bool
    {
        return $this->status->isRolledBack();
    }

    public function markAsApplied(\DateTimeImmutable $appliedAt = null): self
    {
        $this->status = MigrationStatus::APPLIED;
        $this->appliedAt = $appliedAt ?? new \DateTimeImmutable();
        return $this;
    }

    public function markAsFailed(): self
    {
        $this->status = MigrationStatus::FAILED;
        return $this;
    }

    public function markAsRolledBack(): self
    {
        $this->status = MigrationStatus::ROLLED_BACK;
        return $this;
    }

    /**
     * Convert to array representation
     */
    public function toArray(): array
    {
        return [
            'uid' => $this->uid,
            'migration_id' => $this->migrationId,
            'name' => $this->name,
            'description' => $this->description,
            'created_at' => $this->createdAt->getTimestamp(),
            'applied_at' => $this->appliedAt?->getTimestamp(),
            'author' => $this->author,
            'source_hash' => $this->sourceHash,
            'target_hash' => $this->targetHash,
            'status' => $this->status->value,
            'data_file' => $this->dataFile,
            'metadata' => json_encode($this->metadata),
        ];
    }

    /**
     * Create from array data
     */
    public static function fromArray(array $data): self
    {
        $migration = new self(
            $data['migration_id'],
            $data['name'],
            $data['description'],
            $data['author'],
            $data['source_hash'],
            $data['data_file']
        );

        if (isset($data['uid'])) {
            $migration->setUid((int)$data['uid']);
        }

        if (isset($data['created_at'])) {
            $migration->setCreatedAt(new \DateTimeImmutable('@' . $data['created_at']));
        }

        if (isset($data['applied_at']) && is_numeric($data['applied_at']) && $data['applied_at'] > 0) {
            $migration->setAppliedAt(new \DateTimeImmutable('@' . $data['applied_at']));
        }

        if (isset($data['target_hash'])) {
            $migration->setTargetHash($data['target_hash']);
        }

        if (isset($data['status'])) {
            $migration->setStatus($data['status']);
        }

        if (isset($data['metadata']) && is_string($data['metadata'])) {
            $metadata = json_decode($data['metadata'], true);
            if (is_array($metadata)) {
                $migration->setMetadata($metadata);
            }
        }

        return $migration;
    }
}
