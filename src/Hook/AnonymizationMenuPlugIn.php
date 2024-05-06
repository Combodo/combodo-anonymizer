<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SAS
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

use Combodo\iTop\Anonymizer\Helper\AnonymizerHelper;
use Combodo\iTop\Anonymizer\Service\AnonymizerService;


/**
 * Class AnonymizationMenuPlugIn
 */
class AnonymizationMenuPlugIn implements iPopupMenuExtension
{
	public static function EnumItems($iMenuId, $param)
	{
		$aExtraMenus = array();
		$oHelper = new AnonymizerHelper();
		if ($oHelper->CanAnonymize()) {
			if (version_compare(ITOP_DESIGN_LATEST_VERSION, '3.0') < 0) {
				$sJSUrl = utils::GetAbsoluteUrlModulesRoot().AnonymizerHelper::MODULE_NAME.'/assets/js/anonymize.js';
			} else {
				$sJSUrl = 'env-'.utils::GetCurrentEnvironment().'/'.AnonymizerHelper::MODULE_NAME.'/assets/js/anonymize.js';
			}
			switch ($iMenuId) {
				case iPopupMenuExtension::MENU_OBJLIST_ACTIONS:
					/** @var DBObjectSet $param */
					if ($param->GetClass() == 'Person') {
						$aExtraMenus[] = new JSPopupMenuItem('Anonymize', Dict::S('Anonymization:AnonymizeAll'), 'AnonymizeAListOfPersons('.json_encode($param->GetFilter()->serialize()).', '.$param->Count().');', [$sJSUrl]);
					}
					break;

				case iPopupMenuExtension::MENU_OBJDETAILS_ACTIONS:
					/** @var DBObject $param */
					if ($param instanceof Person) {
						$oService = new AnonymizerService();
						if ($oService->IsAllowedToAnonymize(get_class($param), $param->GetKey())) {
							$aExtraMenus[] = new JSPopupMenuItem('Anonymize', Dict::S('Anonymization:AnonymizeOne'), 'AnonymizeOnePerson('.$param->GetKey().');', [$sJSUrl]);
						}
					}
					break;

				default:
					// Do nothing
			}
		}

		return $aExtraMenus;
	}
}