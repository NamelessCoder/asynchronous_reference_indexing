<?php
defined('TYPO3_MODE') || die('Access denied');

$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Core\DataHandling\DataHandler::class]['className'] =
    \NamelessCoder\AsyncReferenceIndexing\DataHandling\DataHandler::class;

$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Core\Database\ReferenceIndex::class]['className'] =
    \NamelessCoder\AsyncReferenceIndexing\Database\ReferenceIndex::class;

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['extbase']['commandControllers'][] =
    \NamelessCoder\AsyncReferenceIndexing\Command\ReferenceIndexCommandController::class;
