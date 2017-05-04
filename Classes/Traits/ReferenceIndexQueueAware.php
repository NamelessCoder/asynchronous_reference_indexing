<?php
namespace NamelessCoder\AsyncReferenceIndexing\Traits;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Reference Index Queue Aware
 *
 * Contains methods necessary to maintain and interact with the
 * reference indexing queue.
 *
 * Provides methods to classes which perform database operations.
 */
trait ReferenceIndexQueueAware
{
    /**
     * Storage for queued items.
     *
     * @var array
     */
    protected static $queuedReferenceItems = [];

    /**
     * Array of class names being captured. Invoking $class::captureReferenceIndex()
     * with true or false as argument sets the behavior. Defaults to true,
     * e.g. will by default capture in any class. This allows outside
     * callers to toggle the interception of each class.
     *
     * @var boolean[]
     */
    protected static $capturing = [];

    /**
     * @param boolean $capture
     */
    public static function captureReferenceIndex($capture)
    {
        static::$capturing[static::class] = $capture;
    }

    /**
     * Returns true only if this particular class is captured.
     *
     * @return boolean
     */
    protected function isCaptured()
    {
        return !isset(static::$capturing[static::class]) || (boolean) static::$capturing[static::class];
    }

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
     * @param integer $workspace
     * @return void
     */
    protected function addReferenceIndexItemToQueue($table, $id, $workspace = 0)
    {
        if (
            $workspace === 0
            && BackendUtility::isTableWorkspaceEnabled($table)
            && isset($GLOBALS['BE_USER'])
            && $GLOBALS['BE_USER']->workspace > 0
        ) {
            $workspace = $GLOBALS['BE_USER']->workspace;
        }
        static::$queuedReferenceItems[$table . ':' . $id . ':' . $workspace] = [
            'reference_table' => $table,
            'reference_uid' => $id,
            'reference_workspace' => $workspace
        ];
    }

    /**
     * @param $table
     * @param string $where
     * @return int
     */
    protected function performCount($table, $where = '1=1')
    {
        return $this->getDoctrineConnectionPool()->getConnectionForTable($table)->query('SELECT reference_uid FROM ' . $table . ' WHERE ' . $where)->rowCount();
    }

    /**
     * @param string $table
     * @param string $where
     * @return boolean
     */
    protected function performDeletion($table, $where)
    {
        return $this->getDoctrineConnectionPool()->getConnectionForTable($table)->exec(
            sprintf(
                'DELETE FROM %s WHERE %s',
                $table,
                $where
            )
        );
    }

    /**
     * @param string $table
     * @param array $fields
     * @param array $records
     * @return void
     */
    protected function performMultipleInsert($table, array $fields, array $records)
    {
        // Split records into chunks since bulkInsert will fail if insert into statement becomes too large
        $recordChunks = array_chunk($records, 4096);
        foreach ($recordChunks as $recordChunk) {
            $this->getDoctrineConnectionPool()->getConnectionForTable($table)->bulkInsert($table, $recordChunk, $fields);
        }
    }

    /**
     * @param string $table
     * @return \Generator
     */
    protected function getRowsWithGenerator($table)
    {
        foreach ($this->getQueryBuilderForTable($table)->select('*')->from($table)->execute() as $row) {
            yield $row;
        }
    }

    /**
     * @return ConnectionPool
     */
    private function getDoctrineConnectionPool()
    {
        return GeneralUtility::makeInstance(ConnectionPool::class);
    }

    /**
     * @param string $table
     * @return QueryBuilder
     */
    private function getQueryBuilderForTable($table)
    {
        return $this->getDoctrineConnectionPool()->getQueryBuilderForTable($table);
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

        // Loop through items and check if entry already exists in $queuedReferenceItems
        foreach (static::$queuedReferenceItems as $key => $item) {
            $where = sprintf(
                'reference_table = \'%s\' AND reference_uid = %d AND reference_workspace = %d',
                (string)$item['reference_table'],
                (integer)$item['reference_uid'],
                (integer)$item['reference_workspace']
            );

            // If entry is already in DB - remove it from $queuedReferenceItems
            if ($this->performCount('tx_asyncreferenceindexing_queue', $where) > 0) {
                unset(static::$queuedReferenceItems[$key]);
            }
        }

        // It might occur that the loop above has removed all element so check before
        // bulk insert is performed
        if (!empty(static::$queuedReferenceItems)) {
            $this->performMultipleInsert(
                'tx_asyncreferenceindexing_queue',
                [
                    'reference_table',
                    'reference_uid',
                    'reference_workspace'
                ],
                static::$queuedReferenceItems
            );
        }

        static::$queuedReferenceItems = [];
    }

}
