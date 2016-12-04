<?php
namespace NamelessCoder\AsyncReferenceIndexing\DataHandling;

use NamelessCoder\AsyncReferenceIndexing\Traits\DatabaseAwareTrait;
use TYPO3\CMS\Backend\Utility\BackendUtility;

/**
 * Class DataHandler
 *
 * Override of core's DataHandler to remove capability to do on-the-fly
 * reference indexing, instead delegating that task to the provided
 * command controller. The command controller can be used directly from
 * CLI, put into crontab or via the Scheduler system extension.
 *
 * The runs can be scheduled as frequently as desired. Indexing will only
 * occur if an action has been taken which normally would trigger indexing.
 *
 * Uses __destruct() lifecycle method to finally store the queue.
 */
class DataHandler extends \TYPO3\CMS\Core\DataHandling\DataHandler
{
    use DatabaseAwareTrait;

    const QUEUE_TABLE = 'tx_asyncreferenceindexing_queue';

    /**
     * Storage for queued items.
     *
     * @var array
     */
    protected static $queuedReferenceItems = [];

    /**
     * Overridden implementation of reference indexing trigger method.
     *
     * This function gets called from multiple places when DataHandler
     * or other classes have performed database operations on any given
     * record. The override prevents the normal reference indexing and
     * instead stores the table+uid combination into a queue (which
     * prevents storing duplicates).
     *
     * @param string $table
     * @param integer $id
     * @return void
     */
    public function updateRefIndex($table, $id)
    {
        $workspaceId = BackendUtility::isTableWorkspaceEnabled($table) ? $this->BE_USER->workspace : 0;
        static::$queuedReferenceItems[$table . ':' . $id . ':' . $workspaceId] = [
            'reference_table' => $table,
            'reference_uid' => $id,
            'reference_workspace' => $workspaceId
        ];
    }

    /**
     * Lifecycle end - store queued updates into the queue table.
     *
     * @return void
     */
    public function __destruct()
    {
        if (empty(static::$queuedReferenceItems)) {
            return;
        }

        $quotedImplodedKeys = implode(
            '\', \'',
            array_keys(static::$queuedReferenceItems)
        );

        // remove *ALL* duplicates from queue
        $this->performDeletion(
            static::QUEUE_TABLE,
            'CONCAT(reference_table, \':\', reference_uid, \':\', reference_workspace) IN (\'' . $quotedImplodedKeys . '\')'
        );

        // insert *ALL* queued items in bulk
        $this->performMultipleInsert(
            static::QUEUE_TABLE,
            [
                'reference_table',
                'reference_uid',
                'reference_workspace'
            ],
            static::$queuedReferenceItems
        );

        static::$queuedReferenceItems = [];
    }

}
