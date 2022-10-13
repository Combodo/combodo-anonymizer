<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */


use Combodo\iTop\Anonymizer\Controller\ConfigAnonymizerController;
use Combodo\iTop\Anonymizer\Helper\AnonymizerHelper;
use Combodo\iTop\Anonymizer\Helper\AnonymizerLog;

require_once(APPROOT.'application/startup.inc.php');

if (version_compare(ITOP_DESIGN_LATEST_VERSION, '3.0') >= 0) {
	$sTemplates = MODULESROOT.AnonymizerHelper::MODULE_NAME.DIRECTORY_SEPARATOR.'templates';
} else {
	$sTemplates = MODULESROOT.AnonymizerHelper::MODULE_NAME.DIRECTORY_SEPARATOR.'templates/2.7';
}

AnonymizerLog::Enable(APPROOT.'log/error.log');

$oUpdateController = new ConfigAnonymizerController($sTemplates, AnonymizerHelper::MODULE_NAME);
$oUpdateController->SetMenuId(AnonymizerHelper::MENU_ID);
$oUpdateController->SetDefaultOperation('DisplayConfig');
$oUpdateController->DisableInDemoMode();
$oUpdateController->HandleOperation();
