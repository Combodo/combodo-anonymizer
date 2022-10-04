<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

use Combodo\iTop\Anonymizer\Helper\AnonymizerHelper;


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
				$sJSUrl = utils::GetAbsoluteUrlModulesRoot().basename(__DIR__).'/assets/js/anonymize.js';
			} else {
				$sJSUrl = 'env-'.utils::GetCurrentEnvironment().'/'.basename(__DIR__).'/assets/js/anonymize.js';
			}
			switch ($iMenuId) {
				case iPopupMenuExtension::MENU_OBJLIST_ACTIONS:
					/**
					 * @var DBObjectSet $param
					 */
					if ($param->GetClass() == 'Person') {
						$aExtraMenus[] = new JSPopupMenuItem('Anonymize', Dict::S('Anonymization:AnonymizeAll'), 'AnonymizeAListOfPersons('.json_encode($param->GetFilter()->serialize()).', '.$param->Count().');', array($sJSUrl));
					}
					break;

				case iPopupMenuExtension::MENU_OBJDETAILS_ACTIONS:
					/**
					 * @var DBObject $param
					 */
					if ($param instanceof Person) {
						$aExtraMenus[] = new JSPopupMenuItem('Anonymize', Dict::S('Anonymization:AnonymizeOne'), 'AnonymizeOnePerson('.$param->GetKey().');', array($sJSUrl));
					}
					break;

				default:
					// Do nothing
			}
		}

		return $aExtraMenus;
	}
}