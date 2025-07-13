<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Tests\Functional;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Trait providing DataHandler utilities for change tracking tests
 *
 * Provides helper methods for simulating real TYPO3 backend operations
 * that trigger DataHandler hooks and generate change records.
 */
trait DataHandlerChangeTrackingTrait
{
    private DataHandler $dataHandler;
    private BackendUserAuthentication $backendUser;

    /**
     * Initialize DataHandler for change tracking operations
     */
    protected function initializeDataHandler(): void
    {
        // Set up backend user (required for DataHandler)
        $this->backendUser = $this->setUpBackendUser(1);

        // Create DataHandler using GeneralUtility to ensure proper initialization
        $this->dataHandler = GeneralUtility::makeInstance(DataHandler::class);

        // Initialize DataHandler with start() method to set up all required properties
        $this->dataHandler->start([], [], $GLOBALS['BE_USER']);

        // Set additional required properties
        $this->dataHandler->admin = true;
    }

    /**
     * Create new record via DataHandler
     *
     * @param string $table Table name
     * @param array $data Record data
     * @param string $newId NEW identifier for the record
     * @return int The UID of the created record
     */
    protected function createRecord(string $table, array $data, string $newId = 'NEW123abc'): int
    {
        $dataMap = [
            $table => [
                $newId => $data,
            ],
        ];

        $this->dataHandler->start($dataMap, []);
        $this->dataHandler->process_datamap();

        $newUid = $this->dataHandler->substNEWwithIDs[$newId] ?? 0;
        if ($newUid === 0) {
            throw new \RuntimeException("Failed to create record in table '{$table}'. DataHandler errors: " . implode(', ', $this->dataHandler->errorLog), 9454548071);
        }

        return (int)$newUid;
    }

    /**
     * Update existing record via DataHandler
     *
     * @param string $table Table name
     * @param int $uid Record UID
     * @param array $data Update data
     */
    protected function updateRecord(string $table, int $uid, array $data): void
    {
        $dataMap = [
            $table => [
                $uid => $data,
            ],
        ];

        $this->dataHandler->start($dataMap, []);
        $this->dataHandler->process_datamap();

        if (!empty($this->dataHandler->errorLog)) {
            throw new \RuntimeException("Failed to update record {$uid} in table '{$table}'. DataHandler errors: " . implode(', ', $this->dataHandler->errorLog), 5497664230);
        }
    }

    /**
     * Delete record via DataHandler (soft delete)
     *
     * @param string $table Table name
     * @param int $uid Record UID
     */
    protected function deleteRecord(string $table, int $uid): void
    {
        $cmdMap = [
            $table => [
                $uid => ['delete' => 1],
            ],
        ];

        $this->dataHandler->start([], $cmdMap);
        $this->dataHandler->process_cmdmap();

        if (!empty($this->dataHandler->errorLog)) {
            throw new \RuntimeException("Failed to delete record {$uid} in table '{$table}'. DataHandler errors: " . implode(', ', $this->dataHandler->errorLog), 6751487967);
        }
    }

    /**
     * Hide record via DataHandler
     *
     * @param string $table Table name
     * @param int $uid Record UID
     */
    protected function hideRecord(string $table, int $uid): void
    {
        $this->updateRecord($table, $uid, ['hidden' => 1]);
    }

    /**
     * Show (unhide) record via DataHandler
     *
     * @param string $table Table name
     * @param int $uid Record UID
     */
    protected function showRecord(string $table, int $uid): void
    {
        $this->updateRecord($table, $uid, ['hidden' => 0]);
    }

    /**
     * Move record to different page via DataHandler
     *
     * @param string $table Table name
     * @param int $uid Record UID
     * @param int $targetPid Target page ID
     */
    protected function moveRecord(string $table, int $uid, int $targetPid): void
    {
        $cmdMap = [
            $table => [
                $uid => ['move' => $targetPid],
            ],
        ];

        $this->dataHandler->start([], $cmdMap);
        $this->dataHandler->process_cmdmap();

        if (!empty($this->dataHandler->errorLog)) {
            throw new \RuntimeException("Failed to move record {$uid} in table '{$table}' to page {$targetPid}. DataHandler errors: " . implode(', ', $this->dataHandler->errorLog), 9363121408);
        }
    }

    /**
     * Copy record via DataHandler
     *
     * @param string $table Table name
     * @param int $uid Source record UID
     * @param int $targetPid Target page ID
     * @return int UID of the copied record
     */
    protected function copyRecord(string $table, int $uid, int $targetPid): int
    {
        $cmdMap = [
            $table => [
                $uid => ['copy' => $targetPid],
            ],
        ];

        $this->dataHandler->start([], $cmdMap);
        $this->dataHandler->process_cmdmap();

        if (!empty($this->dataHandler->errorLog)) {
            throw new \RuntimeException("Failed to copy record {$uid} in table '{$table}' to page {$targetPid}. DataHandler errors: " . implode(', ', $this->dataHandler->errorLog), 1969471745);
        }

        // Find the UID of the copied record
        $copyMappingArray = $this->dataHandler->copyMappingArray[$table] ?? [];
        if (!isset($copyMappingArray[$uid])) {
            throw new \RuntimeException('Copy operation succeeded but could not determine new UID for copied record', 2024059209);
        }

        return (int)$copyMappingArray[$uid];
    }

    /**
     * Perform bulk operations in a single DataHandler transaction
     *
     * @param array $dataMap Data map for creating/updating records
     * @param array $cmdMap Command map for other operations
     * @return array Substitution array for NEW record IDs
     */
    protected function performBulkOperations(array $dataMap = [], array $cmdMap = []): array
    {
        $this->dataHandler->start($dataMap, $cmdMap);

        if (!empty($dataMap)) {
            $this->dataHandler->process_datamap();
        }

        if (!empty($cmdMap)) {
            $this->dataHandler->process_cmdmap();
        }

        if (!empty($this->dataHandler->errorLog)) {
            throw new \RuntimeException('Bulk operations failed. DataHandler errors: ' . implode(', ', $this->dataHandler->errorLog), 2284888707);
        }

        return $this->dataHandler->substNEWwithIDs;
    }

    /**
     * Create a simple page record
     *
     * @param int $pid Parent page ID
     * @param string $title Page title
     * @param int $doktype Document type (default: 1 = standard page)
     * @param array $additionalData Additional page data
     * @return int Created page UID
     */
    protected function createPage(int $pid, string $title, int $doktype = 1, array $additionalData = []): int
    {
        $pageData = array_merge([
            'pid' => $pid,
            'title' => $title,
            'doktype' => $doktype,
        ], $additionalData);

        return $this->createRecord('pages', $pageData);
    }

    /**
     * Create a simple content element
     *
     * @param int $pid Page ID
     * @param string $header Content header
     * @param string $ctype Content type (default: 'text')
     * @param array $additionalData Additional content data
     * @return int Created content UID
     */
    protected function createContentElement(int $pid, string $header, string $ctype = 'text', array $additionalData = []): int
    {
        $contentData = array_merge([
            'pid' => $pid,
            'header' => $header,
            'CType' => $ctype,
        ], $additionalData);

        return $this->createRecord('tt_content', $contentData);
    }

    /**
     * Verify DataHandler operation completed without errors
     */
    protected function assertDataHandlerSuccess(): void
    {
        if (!empty($this->dataHandler->errorLog)) {
            $errors = implode(', ', $this->dataHandler->errorLog);
            self::fail("DataHandler operation failed with errors: {$errors}");
        }
    }

    /**
     * Get the last DataHandler error messages
     *
     * @return array Array of error messages
     */
    protected function getDataHandlerErrors(): array
    {
        return $this->dataHandler->errorLog;
    }

    /**
     * Clear DataHandler error log
     */
    protected function clearDataHandlerErrors(): void
    {
        $this->dataHandler->errorLog = [];
    }

    /**
     * Create test scenario with related records
     *
     * Creates a page with content elements for testing complex scenarios
     *
     * @param string $pageTitle Page title
     * @param array $contentHeaders Array of content headers to create
     * @param int $parentPid Parent page ID (default: 0 = root level)
     * @return array ['page_uid' => int, 'content_uids' => array]
     */
    protected function createTestScenario(string $pageTitle, array $contentHeaders = [], int $parentPid = 0): array
    {
        // Create the page
        $pageUid = $this->createPage($parentPid, $pageTitle);

        // Create content elements
        $contentUids = [];
        foreach ($contentHeaders as $header) {
            $contentUids[] = $this->createContentElement($pageUid, $header);
        }

        return [
            'page_uid' => $pageUid,
            'content_uids' => $contentUids,
        ];
    }

    /**
     * Simulate a complex editing workflow
     *
     * Creates, updates, and modifies records to simulate real user behavior
     *
     * @param int $basePid Base page for operations
     * @return array Summary of created/modified record UIDs
     */
    protected function simulateEditingWorkflow(int $basePid = 0): array
    {
        $results = [];

        // 1. Create initial page structure
        $homePageUid = $this->createPage($basePid, 'Home');
        $aboutPageUid = $this->createPage($basePid, 'About');
        $results['pages'] = [$homePageUid, $aboutPageUid];

        // 2. Add content to pages
        $homeContentUid = $this->createContentElement($homePageUid, 'Welcome to our site');
        $aboutContentUid = $this->createContentElement($aboutPageUid, 'About us');
        $results['content_created'] = [$homeContentUid, $aboutContentUid];

        // 3. Update existing content
        $this->updateRecord('tt_content', $homeContentUid, [
            'header' => 'Welcome to our updated site',
            'bodytext' => 'This content has been updated',
        ]);
        $results['content_updated'] = [$homeContentUid];

        // 4. Hide and show content
        $this->hideRecord('tt_content', $aboutContentUid);
        $this->showRecord('tt_content', $aboutContentUid);
        $results['content_toggled'] = [$aboutContentUid];

        // 5. Move content between pages
        $newContentUid = $this->createContentElement($homePageUid, 'Content to move');
        $this->moveRecord('tt_content', $newContentUid, $aboutPageUid);
        $results['content_moved'] = [$newContentUid];

        // 6. Copy content
        $copiedContentUid = $this->copyRecord('tt_content', $homeContentUid, $aboutPageUid);
        $results['content_copied'] = [$copiedContentUid];

        return $results;
    }
}
