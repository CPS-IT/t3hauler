<?php

defined('TYPO3') or die();

// Register DataHandler hooks for change tracking
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] = \Cpsit\T3hauler\Hook\DataHandlerHook::class;
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass'][] = \Cpsit\T3hauler\Hook\DataHandlerHook::class;

// Alternative hook registration for TYPO3 v13 (using new keys)
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['TYPO3\\CMS\\Core\\DataHandling\\DataHandler']['processDatamapClass'][] = \Cpsit\T3hauler\Hook\DataHandlerHook::class;
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['TYPO3\\CMS\\Core\\DataHandling\\DataHandler']['processCmdmapClass'][] = \Cpsit\T3hauler\Hook\DataHandlerHook::class;
