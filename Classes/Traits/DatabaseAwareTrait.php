<?php
namespace NamelessCoder\AsyncReferenceIndexing\Traits;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\DatabaseConnection;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Database Aware
 *
 * Provides methods to classes which perform database operations,
 * making it possible to consume them using the same API on both
 * TYPO3 7.6 LTS and 8.5+.
 */
trait DatabaseAwareTrait
{

    /**
     * @oaram string $table
     * @return integer
     */
    protected function performCount($table)
    {
        if ($this->isLegacyDatabaseConnection()) {
            return $this->getLegacyDatabaseConnection()->exec_SELECTcountRows('*', $table);
        }
        return $this->getDoctrineConnectionPool()->getConnectionForTable($table)->query('SELECT reference_uid FROM ' . $table)->rowCount();
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

}
