<?php
namespace NamelessCoder\AsyncReferenceIndexing\Command;

use NamelessCoder\AsyncReferenceIndexing\DataHandling\DataHandler;
use NamelessCoder\AsyncReferenceIndexing\Traits\DatabaseAwareTrait;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\ReferenceIndex;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\CommandController;

/**
 * Reference Index Commands
 *
 * Commandline execution for reference index updating
 * based on the queue maintained by the DataHandler
 * override shipped with this extension.
 */
class ReferenceIndexCommandController extends CommandController
{
    use DatabaseAwareTrait;

    const LEGACY_LOCKFILE = 'typo3temp/reference-indexing-running.lock';
    const LOCKFILE = 'typo3temp/var/reference-indexing-running.lock';

    /**
     * Update Reference Index
     *
     * Updates the reference index by
     * processing the queue maintained by
     * the overridden DataHandler class.
     *
     * @return void
     */
    public function updateCommand()
    {
        $lockFile = GeneralUtility::getFileAbsFileName(static::LOCKFILE);
        if (file_exists($lockFile)) {
            $this->response->setContent('Another process is updating the reference index - skipping' . PHP_EOL);
            return;
        }

        $count = $this->performCount(DataHandler::QUEUE_TABLE);

        if (!$count) {
            $this->response->setContent('No reference indexing tasks queued - nothing to do.' . PHP_EOL);
            $this->sendAndExit();
        }

        $this->lock();

        $this->response->setContent(
            'Processing reference index for ' . $count . ' record(s)' . PHP_EOL
        );

        // Note about loop: a fresh instance of ReferenceIndex is *intentional*. The class mutates
        // internal state during processing. Furthermore, we catch any errors and exit only after
        // removing the lock file. Any error causes processing to stop completely.
        try {

            foreach ($this->getRowsWithGenerator(DataHandler::QUEUE_TABLE) as $queueItem) {

                /** @var $referenceIndex ReferenceIndex */
                $referenceIndex = GeneralUtility::makeInstance(ReferenceIndex::class);
                if (!empty($queueItem['reference_workspace']) && BackendUtility::isTableWorkspaceEnabled($queueItem['reference_table'])) {
                    $referenceIndex->setWorkspaceId($queueItem['reference_workspace']);
                }
                $referenceIndex->updateRefIndexTable($queueItem['reference_table'], $queueItem['reference_uid']);
                $this->performDeletion(
                    DataHandler::QUEUE_TABLE,
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
            $this->sendAndExit(1);
        }
    }

    /**
     * @return string
     */
    protected function getLockFile()
    {
        $candidate = GeneralUtility::getFileAbsFileName(static::LOCKFILE);
        if (!is_dir(dirname($candidate))) {
            $candidate = GeneralUtility::getFileAbsFileName(static::LEGACY_LOCKFILE);
        }
        return $candidate;
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
