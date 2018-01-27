<?php
namespace NamelessCoder\AsyncReferenceIndexing\Command;

use NamelessCoder\AsyncReferenceIndexing\Database\ReferenceIndex as AsyncReferenceIndex;
use NamelessCoder\AsyncReferenceIndexing\Traits\ReferenceIndexQueueAware;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\ReferenceIndex;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\CommandController;

/**
 * Async Reference Index Commands
 *
 * Commandline execution for reference index updating
 * based on the queue maintained by the DataHandler
 * override shipped with this extension.
 */
class AsyncReferenceIndexCommandController extends CommandController
{
    use ReferenceIndexQueueAware;

    const LOCKFILE = 'typo3temp/var/reference-indexing-running.lock';

    /**
     * Update Reference Index
     *
     * Updates the reference index - if providing the -f parameter the
     * indexing will index directly to sys_refindex - else the
     *
     * @param boolean $force
     * @param boolean $check
     * @param boolean $silent
     *
     * @return void
     */
    public function updateCommand($force = false, $check = false, $silent = false) {
        if ($force) {
            AsyncReferenceIndex::captureReferenceIndex(false);
            $refIndexObj = GeneralUtility::makeInstance(ReferenceIndex::class);
            $refIndexObj->updateIndex($check, !$silent);
        }
        else {
            $this->updateReferenceIndex();
        }
    }

    /**
     * Update Reference Index
     *
     * Updates the reference index by
     * processing the queue maintained by
     * the overridden DataHandler class.
     *
     * @return void
     */
    protected function updateReferenceIndex()
    {
        $lockFile = GeneralUtility::getFileAbsFileName(static::LOCKFILE);
        if (file_exists($lockFile)) {
            $this->response->setContent('Another process is updating the reference index - skipping' . PHP_EOL);
            return;
        }

        $count = $this->performCount('tx_asyncreferenceindexing_queue');

        if (!$count) {
            $this->response->setContent('No reference indexing tasks queued - nothing to do.' . PHP_EOL);
            return;
        }

        $this->lock();

        $this->response->setContent(
            'Processing reference index for ' . $count . ' record(s)' . PHP_EOL
        );

        // Note about loop: a fresh instance of ReferenceIndex is *intentional*. The class mutates
        // internal state during processing. Furthermore, we catch any errors and exit only after
        // removing the lock file. Any error causes processing to stop completely.
        try {

            // Force the reference index override to disable capturing. Will apply to *all* instances
            // of ReferenceIndex (but of course only when the override gets loaded).
            AsyncReferenceIndex::captureReferenceIndex(false);

            foreach ($this->getRowsWithGenerator('tx_asyncreferenceindexing_queue') as $queueItem) {

                /** @var $referenceIndex ReferenceIndex */
                $referenceIndex = GeneralUtility::makeInstance(ReferenceIndex::class);
                if (!empty($queueItem['reference_workspace']) && BackendUtility::isTableWorkspaceEnabled($queueItem['reference_table'])) {
                    $referenceIndex->setWorkspaceId($queueItem['reference_workspace']);
                }
                $referenceIndex->updateRefIndexTable($queueItem['reference_table'], $queueItem['reference_uid']);
                $this->performDeletion(
                    'tx_asyncreferenceindexing_queue',
                    sprintf(
                        'reference_table = \'%s\' AND reference_uid = %d AND reference_workspace = %d',
                        (string) $queueItem['reference_table'],
                        (integer) $queueItem['reference_uid'],
                        (integer) $queueItem['reference_workspace']
                    )
                );

            }
            $this->response->appendContent('Reference indexing complete!' . PHP_EOL);
            $this->unlock();

        } catch (\Exception $error) {

            $this->response->appendContent('ERROR! ' . $error->getMessage() . ' (' . $error->getCode() . ')' . PHP_EOL);
            $this->unlock();

        }
    }

    /**
     * @return string
     */
    protected function getLockFile()
    {
        return GeneralUtility::getFileAbsFileName(static::LOCKFILE);
    }

    /**
     * Lock so that other command instances do not start running.
     *
     * @return void
     */
    protected function lock()
    {
        touch(
            $this->getLockFile()
        );
    }

    /**
     * Removes run protection lock
     *
     * @return void
     */
    protected function unlock()
    {
        unlink(
            $this->getLockFile()
        );
    }
}
