<?php
namespace NamelessCoder\AsyncReferenceIndexing\DataHandling;

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
class DataHandler extends \TYPO3\CMS\Core\DataHandling\DataHandler
{
    use ReferenceIndexQueueAware;

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
        $this->addReferenceIndexItemToQueue($table, $id);
    }

}
