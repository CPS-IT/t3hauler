<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Domain\Repository;

use Cpsit\T3hauler\Domain\Model\Migration;

/**
 * Repository for Migration domain objects
 */
class MigrationRepository extends AbstractRepository
{
    protected const string TABLE_NAME = 'tx_t3hauler_migrations';

    /**
     * Find migration by migration ID
     */
    public function findByMigrationId(string $migrationId): ?Migration
    {
        $row = $this->findOneByConditions(['migration_id' => $migrationId]);
        return $row ? Migration::fromArray($row) : null;
    }

    /**
     * Find migration by UID
     */
    public function findByUid(int $uid): ?Migration
    {
        $row = $this->findOneByConditions(['uid' => $uid]);
        return $row ? Migration::fromArray($row) : null;
    }

    /**
     * Find all migrations
     */
    public function findAll(string $orderBy = 'created_at', string $direction = 'DESC'): array
    {
        $rows = $this->findByConditions([], [$orderBy => $direction]);
        return $this->mapRowsToObjects($rows, fn($row) => Migration::fromArray($row));
    }

    /**
     * Find migrations by status
     */
    public function findByStatus(string $status): array
    {
        $rows = $this->findByConditions(
            ['status' => $status],
            ['created_at' => 'DESC']
        );
        return $this->mapRowsToObjects($rows, fn($row) => Migration::fromArray($row));
    }

    /**
     * Find pending migrations
     */
    public function findPending(): array
    {
        return $this->findByStatus('pending');
    }

    /**
     * Find applied migrations
     */
    public function findApplied(): array
    {
        return $this->findByStatus('applied');
    }

    /**
     * Find failed migrations
     */
    public function findFailed(): array
    {
        return $this->findByStatus('failed');
    }

    /**
     * Find latest migration
     */
    public function findLatest(): ?Migration
    {
        $rows = $this->findByConditions([], ['created_at' => 'DESC'], 1);
        return $rows ? Migration::fromArray($rows[0]) : null;
    }

    /**
     * Save migration
     */
    public function save(Migration $migration): Migration
    {
        $data = $migration->toArray();

        if ($migration->getUid() === null) {
            $uid = $this->insertRecord($data);
            $migration->setUid($uid);
        } else {
            $this->updateRecord($migration->getUid(), $data);
        }

        return $migration;
    }

    /**
     * Delete migration
     */
    public function delete(Migration $migration): bool
    {
        if ($migration->getUid() === null) {
            return false;
        }

        return $this->deleteRecord($migration->getUid());
    }

    /**
     * Delete migration by migration ID
     */
    public function deleteByMigrationId(string $migrationId): bool
    {
        $affectedRows = $this->deleteByConditions(['migration_id' => $migrationId]);
        return $affectedRows > 0;
    }

    /**
     * Check if migration exists
     */
    public function exists(string $migrationId): bool
    {
        return $this->existsByConditions(['migration_id' => $migrationId]);
    }

    /**
     * Count migrations by status
     */
    public function countByStatus(string $status): int
    {
        return $this->countByConditions(['status' => $status]);
    }

    /**
     * Get migrations summary
     */
    public function getSummary(): array
    {
        return [
            'total' => $this->count(),
            'pending' => $this->countByStatus('pending'),
            'applied' => $this->countByStatus('applied'),
            'failed' => $this->countByStatus('failed'),
            'rolled_back' => $this->countByStatus('rolled_back'),
        ];
    }

    /**
     * Count all migrations
     */
    public function count(): int
    {
        return $this->countByConditions([]);
    }

    /**
     * Delete old migrations
     */
    public function deleteMigrationsOlderThan(\DateTimeInterface $date): int
    {
        return $this->deleteOlderThan('created_at', $date);
    }

}
