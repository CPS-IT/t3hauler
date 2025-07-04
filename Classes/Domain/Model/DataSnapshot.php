<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Domain\Model;

/**
 * Domain model for database state snapshots
 *
 * Represents a snapshot of table data at a specific point in time
 */
class DataSnapshot
{
    private ?int $uid = null;
    private string $identifier;
    private string $tableName;
    private string $hash;
    private \DateTimeImmutable $createdAt;
    private ?string $migrationVersion = null;
    private array $metadata = [];

    public function __construct(
        string $identifier,
        string $tableName,
        string $hash,
        ?\DateTimeImmutable $createdAt = null
    ) {
        $this->identifier = $identifier;
        $this->tableName = $tableName;
        $this->hash = $hash;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
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

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): self
    {
        $this->identifier = $identifier;
        return $this;
    }

    public function getTableName(): string
    {
        return $this->tableName;
    }

    public function setTableName(string $tableName): self
    {
        $this->tableName = $tableName;
        return $this;
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    public function setHash(string $hash): self
    {
        $this->hash = $hash;
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

    public function getMigrationVersion(): ?string
    {
        return $this->migrationVersion;
    }

    public function setMigrationVersion(?string $migrationVersion): self
    {
        $this->migrationVersion = $migrationVersion;
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

    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Check if this snapshot matches another hash
     */
    public function matchesHash(string $hash): bool
    {
        return hash_equals($this->hash, $hash);
    }

    /**
     * Get age of snapshot in seconds
     */
    public function getAgeInSeconds(): int
    {
        return time() - $this->createdAt->getTimestamp();
    }

    /**
     * Check if snapshot is older than specified seconds
     */
    public function isOlderThan(int $seconds): bool
    {
        return $this->getAgeInSeconds() > $seconds;
    }

    /**
     * Generate a unique identifier for the snapshot
     */
    public static function generateIdentifier(string $tableName, ?string $suffix = null): string
    {
        $base = $tableName . '_' . date('Y-m-d_H:i:s') . '_' . uuid_create();
        return $suffix ? $base . '_' . $suffix : $base;
    }

    /**
     * Convert to array representation
     */
    public function toArray(): array
    {
        return [
            'uid' => $this->uid,
            'identifier' => $this->identifier,
            'table_name' => $this->tableName,
            'hash' => $this->hash,
            'created_at' => $this->createdAt->getTimestamp(),
            'migration_version' => $this->migrationVersion,
            'metadata' => json_encode($this->metadata),
        ];
    }

    /**
     * Create from array representation
     */
    public static function fromArray(array $data): self
    {
        $snapshot = new self(
            $data['identifier'],
            $data['table_name'],
            $data['hash'],
            new \DateTimeImmutable('@' . $data['created_at'])
        );

        if (isset($data['uid'])) {
            $snapshot->setUid((int)$data['uid']);
        }

        if (isset($data['migration_version'])) {
            $snapshot->setMigrationVersion($data['migration_version']);
        }

        if (isset($data['metadata']) && is_string($data['metadata'])) {
            $metadata = json_decode($data['metadata'], true);
            if (is_array($metadata)) {
                $snapshot->setMetadata($metadata);
            }
        }

        return $snapshot;
    }
}
