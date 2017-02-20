<?php
namespace NamelessCoder\AsyncReferenceIndexing\Traits;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\DatabaseConnection;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Reference Index Queue Aware
 *
 * Contains methods necessary to maintain and interact with the
 * reference indexing queue.
 *
 * Provides methods to classes which perform database operations,
 * making it possible to consume them using the same API on both
 * TYPO3 7.6 LTS and 8.5+.
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
    protected function performCount($table, $where = '1==1')
    {
        if ($this->isLegacyDatabaseConnection()) {
            return $this->getLegacyDatabaseConnection()->exec_SELECTcountRows('*', $table, $where);
        }
        return $this->getDoctrineConnectionPool()->getConnectionForTable($table)->query('SELECT reference_uid FROM ' . $table . ' WHERE ' . $where)->rowCount();
    }

    /**
     * @param string $table
     * @param string $where
     * @return boolean
     */
    protected function performDeletion($table, $where)
    {
        if ($this->isLegacyDatabaseConnection()) {
            return $this->getLegacyDatabaseConnection()->exec_DELETEquery($table, $where);
        }
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
        if ($this->isLegacyDatabaseConnection()) {
            $this->getLegacyDatabaseConnection()->exec_INSERTmultipleRows($table, $fields, array_values($records));
        } else {
            $this->getDoctrineConnectionPool()->getConnectionForTable($table)->bulkInsert($table, $records, $fields);
        }
    }

    /**
     * @param string $table
     * @return \Generator
     */
    protected function getRowsWithGenerator($table)
    {
        if ($this->isLegacyDatabaseConnection()) {
            $connection = $this->getLegacyDatabaseConnection();
            $result = $connection->sql_query('SELECT * FROM ' . $table);
            while ($row = $connection->sql_fetch_assoc($result)) {
                yield $row;
            }
            $this->getLegacyDatabaseConnection()->sql_free_result($result);
        } else {
            foreach ($this->getQueryBuilderForTable($table)->select('*')->from($table)->execute() as $row) {
                yield $row;
            }
        }
    }

    /**
     * @return boolean
     */
    private function isLegacyDatabaseConnection()
    {
        return !class_exists(ConnectionPool::class);
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
     * @return DatabaseConnection
     */
    private function getLegacyDatabaseConnection()
    {
        return $GLOBALS['TYPO3_DB'];
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
