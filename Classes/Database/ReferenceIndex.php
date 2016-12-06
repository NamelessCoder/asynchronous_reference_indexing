<?php
namespace NamelessCoder\AsyncReferenceIndexing\Database;

use NamelessCoder\AsyncReferenceIndexing\Traits\ReferenceIndexQueueAware;

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
 */
class ReferenceIndex extends \TYPO3\CMS\Core\Database\ReferenceIndex
{
    use ReferenceIndexQueueAware;

    /**
     * Overridden implementation of updateRefIndexTable
     *
     * This function gets called from multiple places
     *
     * The override prevents the normal updateRefIndexTable and
     * instead stores the table+uid+workspace combination into a queue (which
     * prevents storing duplicates). Since $runUpdateRefIndexTable is normally false
     * the method will only insert the records into the queue. When the
     *
     * @param string $tableName Table name
     * @param integer $uid UID of record
     * @param boolean $testOnly If set, nothing will be written to the index but the result value will still report
     *                          statistics on what is added, deleted and kept. Can be used for mere analysis.
     *
     * @return array Array with statistics about how many index records were added, deleted and not altered plus the
     *               complete reference set for the record.
     */
    public function updateRefIndexTable($tableName, $uid, $testOnly = false)
    {
        // Decide if item should be queued or not
        if (!static::isCaptured()) {
            return parent::updateRefIndexTable($tableName, $uid, $testOnly);
        } elseif (!$testOnly) {
            $this->addReferenceIndexItemToQueue($tableName, $uid);
        }
        return [];
    }

}
