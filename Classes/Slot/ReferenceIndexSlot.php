<?php
namespace NamelessCoder\AsyncReferenceIndexing\Slot;

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class ReferenceIndexSlot
 *
 */
class ReferenceIndexSlot
{
    /**
     * Exclude tables from ReferenceIndex which cannot contain a reference
     *
     * @param string $tableName Name of the table
     * @param bool &$excludeTable Reference to a boolean whether to exclude the table from ReferenceIndex or not
     */
    public function shouldExcludeTableFromReferenceIndex($tableName, &$excludeTable)
    {
        if ($excludeTable) {
            return;
        }

        $excludeTable = false;
        if (empty($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['asynchronous_reference_indexing']['excludeTablesFromReferenceIndexing'])) {
            $excludeTable = false;
            return;
        }

        $excludeTableArray = GeneralUtility::trimExplode(',',
            $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['asynchronous_reference_indexing']['excludeTablesFromReferenceIndexing']);
        if (in_array($tableName, $excludeTableArray)) {
            $excludeTable = true;
        }
    }
}

