<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Hook;

use Cpsit\T3hauler\Configuration\T3HaulerConfiguration;
use Cpsit\T3hauler\Domain\Repository\DataSnapshotRepository;
use Cpsit\T3hauler\Service\ChangeTrackingService;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * DataHandler hook to detect record changes and track them for T3Hauler migrations
 */
class DataHandlerHook implements SingletonInterface
{
    private ChangeTrackingService $changeTrackingService;
    private T3HaulerConfiguration $configuration;
    private DataSnapshotRepository $snapshotRepository;
    private array $beforeUpdateData = [];

    public function __construct(
        ChangeTrackingService $changeTrackingService,
        T3HaulerConfiguration $configuration,
        DataSnapshotRepository $snapshotRepository,
        private readonly \TYPO3\CMS\Core\Database\ConnectionPool $connectionPool
    ) {
        $this->changeTrackingService = $changeTrackingService;
        $this->configuration = $configuration;
        $this->snapshotRepository = $snapshotRepository;
    }

    /**
     * Hook called before data is processed by DataHandler
     * Store original data for comparison
     */
    public function processDatamap_beforeStart(DataHandler $dataHandler): void
    {
        $this->beforeUpdateData = [];

        // Only track if we have enabled tables configured
        $enabledTables = $this->configuration->get('detection.enabledTables', []);
        if (empty($enabledTables)) {
            return;
        }

        // Store original data for updates
        if (!empty($dataHandler->datamap)) {
            foreach ($dataHandler->datamap as $tableName => $records) {
                if (!in_array($tableName, $enabledTables, true)) {
                    continue;
                }

                foreach ($records as $uid => $recordData) {
                    if (is_numeric($uid)) {
                        // This is an update - fetch current data
                        $currentRecord = $this->fetchCurrentRecord($tableName, (int)$uid);
                        if ($currentRecord) {
                            $this->beforeUpdateData[$tableName][$uid] = $currentRecord;
                        }
                    }
                }
            }
        }
    }

    /**
     * Hook called after a record is inserted
     */
    public function processDatamap_afterDatabaseOperations(
        string $status,
        string $table,
        $id,
        array $fieldArray,
        DataHandler $dataHandler
    ): void {
        try {
            $enabledTables = $this->configuration->get('detection.enabledTables', []);
            if (!in_array($table, $enabledTables, true)) {
                return;
            }

            $currentSnapshot = $this->snapshotRepository->findCurrentSnapshot();
            if (!$currentSnapshot) {
                return;
            }

            $backendUser = $this->getBackendUser();
            $correlationId = $this->generateCorrelationId();

            switch ($status) {
                case 'new':
                    // Record was inserted
                    $realId = $dataHandler->substNEWwithIDs[$id] ?? $id;
                    if (is_numeric($realId)) {
                        $this->trackRecordChange(
                            $currentSnapshot->getUid(),
                            $table,
                            (int)$realId,
                            'insert',
                            $fieldArray,
                            null,
                            $backendUser,
                            $correlationId
                        );
                    }
                    break;

                case 'update':
                    // Record was updated
                    if (is_numeric($id)) {
                        $previousData = $this->beforeUpdateData[$table][$id] ?? null;
                        $this->trackRecordChange(
                            $currentSnapshot->getUid(),
                            $table,
                            (int)$id,
                            'update',
                            $fieldArray,
                            $previousData,
                            $backendUser,
                            $correlationId
                        );
                    }
                    break;
            }
        } catch (\Throwable $e) {
            // Log error but don't interrupt the DataHandler process
            $this->logError('Error tracking record change', $e);
        }
    }

    /**
     * Hook called after a record is deleted
     */
    public function processCmdmap_deleteAction(
        string $table,
        int $uid,
        array $record,
        DataHandler $dataHandler
    ): void {
        try {
            $enabledTables = $this->configuration->get('detection.enabledTables', []);
            if (!in_array($table, $enabledTables, true)) {
                return;
            }

            $currentSnapshot = $this->snapshotRepository->findCurrentSnapshot();
            if (!$currentSnapshot) {
                return;
            }

            $backendUser = $this->getBackendUser();
            $correlationId = $this->generateCorrelationId();

            $this->trackRecordChange(
                $currentSnapshot->getUid(),
                $table,
                $uid,
                'delete',
                $record,
                $record,
                $backendUser,
                $correlationId
            );
        } catch (\Throwable $e) {
            $this->logError('Error tracking record deletion', $e);
        }
    }

    /**
     * Hook called after a record is moved
     */
    public function processCmdmap_moveAction(
        string $table,
        int $uid,
        int $destPid,
        array $record,
        DataHandler $dataHandler
    ): void {
        try {
            $enabledTables = $this->configuration->get('detection.enabledTables', []);
            if (!in_array($table, $enabledTables, true)) {
                return;
            }

            $currentSnapshot = $this->snapshotRepository->findCurrentSnapshot();
            if (!$currentSnapshot) {
                return;
            }

            $backendUser = $this->getBackendUser();
            $correlationId = $this->generateCorrelationId();

            $previousData = $this->beforeUpdateData[$table][$uid] ?? $record;
            $this->trackRecordChange(
                $currentSnapshot->getUid(),
                $table,
                $uid,
                'move',
                ['pid' => $destPid],
                $previousData,
                $backendUser,
                $correlationId
            );
        } catch (\Throwable $e) {
            $this->logError('Error tracking record move', $e);
        }
    }

    /**
     * Track a record change
     */
    private function trackRecordChange(
        int $snapshotUid,
        string $tableName,
        int $recordUid,
        string $changeType,
        array $newData,
        ?array $previousData,
        ?BackendUserAuthentication $backendUser,
        string $correlationId
    ): void {
        $this->changeTrackingService->trackChange([
            'snapshot_uid' => $snapshotUid,
            'table_name' => $tableName,
            'record_uid' => $recordUid,
            'change_type' => $changeType,
            'new_data' => $newData,
            'previous_data' => $previousData,
            'be_user' => $backendUser ? $backendUser->user['uid'] : 0,
            'workspace' => $backendUser ? $backendUser->workspace : 0,
            'correlation_id' => $correlationId,
        ]);
    }

    /**
     * Fetch current record from database
     */
    private function fetchCurrentRecord(string $tableName, int $uid): ?array
    {
        try {
            $queryBuilder = $this->connectionPool
                ->getQueryBuilderForTable($tableName);

            $record = $queryBuilder
                ->select('*')
                ->from($tableName)
                ->where(
                    $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid))
                )
                ->executeQuery()
                ->fetchAssociative();

            return $record ?: null;
        } catch (\Throwable $e) {
            // Gracefully handle database connection issues (e.g., during unit tests)
            return null;
        }
    }

    /**
     * Get current backend user
     */
    private function getBackendUser(): ?BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'] ?? null;
    }

    /**
     * Generate correlation ID for grouping related changes
     */
    private function generateCorrelationId(): string
    {
        return uniqid('t3h_', true);
    }

    /**
     * Log error without interrupting DataHandler process
     */
    private function logError(string $message, \Throwable $exception): void
    {
        $logger = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Log\LogManager::class)
            ->getLogger(__CLASS__);

        $logger->error($message, [
            'exception' => $exception,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);
    }
}
