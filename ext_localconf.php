<?php

defined('TYPO3') or die();

// Register DataHandler hooks for change tracking
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] = \Cpsit\T3hauler\Hook\DataHandlerHook::class;
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass'][] = \Cpsit\T3hauler\Hook\DataHandlerHook::class;
