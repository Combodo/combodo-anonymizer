<?php
/**
 * Copyright (C) 2013-2020 Combodo SARL
 *
 * This file is part of iTop.
 *
 * iTop is free software; you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * iTop is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 */

/**
 * Class AnonymizationPlugIn
 */
class AnonymizationPlugIn implements iPopupMenuExtension, iPageUIExtension
{
	/**
	 * @inheritDoc
	 * @throws \Exception
	 */
	public static function EnumItems($iMenuId, $param)
	{
		$aExtraMenus = array();
		$sJSUrl = utils::GetAbsoluteUrlModulesRoot().basename(__DIR__).'/js/anonymize.js';
		switch($iMenuId)
		{
			case iPopupMenuExtension::MENU_OBJLIST_ACTIONS:
			/**
			 * @var DBObjectSet $param
			 */
			if ($param->GetClass() == 'Person')
			{
				$aExtraMenus[] = new JSPopupMenuItem('Anonymize', Dict::S('Anonymization:AnonymizeAll'), 'AnonymizeAListOfPersons('.json_encode($param->GetFilter()->serialize()).', '.$param->Count().');', array($sJSUrl));
			}
			break;
			
			case iPopupMenuExtension::MENU_OBJDETAILS_ACTIONS:
			/**
			 * @var DBObject $param
			 */
			if ($param instanceof Person)
			{
				$aExtraMenus[] = new JSPopupMenuItem('Anonymize', Dict::S('Anonymization:AnonymizeOne'), 'AnonymizeOnePerson('.$param->GetKey().');', array($sJSUrl));
			}
			break;
			
			default:
				// Do nothing
		}
		return $aExtraMenus;
	}
	
	/**
	 * @inheritDoc
	 */
	public function GetNorthPaneHtml(iTopWebPage $oPage)
	{
		// backward compatbility with iTop 2.4, emulate add_dict_entries
		$aDictEntries = array(
			'Anonymization:AnonymizeAll',
			'Anonymization:AnonymizeOne',
			'Anonymization:OnePersonWarning',
			'Anonymization:ListOfPersonsWarning',
			'Anonymization:Confirmation',
			'Anonymization:Information',
			'Anonymization:RefreshTheList',
			'Anonymization:DoneOnePerson',
			'Anonymization:InProgress',
			'Anonymization:Success',
			'Anonymization:Error',
			'Anonymization:Close',
			'Anonymization:Configuration',
			'Menu:ConfigAnonymizer',
			'Anonymization:AutomationParameters',
			'Anonymization:NotificationsPurgeParameters',
			'Anonymization:AnonymizationDelay_Input',
			'Anonymization:PurgeDelay_Input',
			'Anonymization:Person:name',
			'Anonymization:Person:first_name',
			'UI:Button:Ok',
		);
		foreach($aDictEntries as $sDictCode)
		{
			$oPage->add_dict_entry($sDictCode);
		}
	}
	
	/**
	 * @inheritDoc
	 */
	public function GetSouthPaneHtml(iTopWebPage $oPage)
	{
		
	}

	/**
	 * @inheritDoc
	 */
	public function GetBannerHtml(iTopWebPage $oPage)
	{
		
	}
}


//
// Menus
//
class CombodoAnonymizerBackwardCompatMenuHandler extends ModuleHandlerAPI
{
	/**
	 * Create the menu to manage the configuration of the extension, but only for
	 * users allowed to manage the configuration
	 * Handle the differences between iTop 2.4 and 2.5
	 *
	 * @inheritDoc
	 * @throws \Exception
	 */
	public static function OnMenuCreation()
	{
		$bConfigMenuEnabled = false;
		
		if (MetaModel::IsValidClass('ResourceAdminMenu'))
		{
			// iTop version 2.5 or newer, check the rights used when defining the admin menu
			// We cannot directly check if the admin menu is enabled right now, since we are in the process of building the list of menus
			$bConfigMenuEnabled = UserRights::IsActionAllowed('ResourceAdminMenu', UR_ACTION_MODIFY);
			if ($bConfigMenuEnabled)
			{
				$oParentMenu = new MenuGroup('Configuration', 90 , null, UR_ACTION_MODIFY, UR_ALLOWED_YES, null);
				new WebPageMenuNode('ConfigAnonymizer', utils::GetAbsoluteUrlModulePage('combodo-anonymizer', "config.php"), $oParentMenu->GetIndex(), 10 , 'ResourceAdminMenu', UR_ACTION_MODIFY, UR_ALLOWED_YES, null);
			}
		}
		else
		{
			// Only administrators
			$bConfigMenuEnabled = UserRights::IsAdministrator();
			if ($bConfigMenuEnabled)
			{
				$oParentMenu = new MenuGroup('Configuration', 90 , null, UR_ACTION_MODIFY, UR_ALLOWED_YES, null);
				new WebPageMenuNode('ConfigAnonymizer', utils::GetAbsoluteUrlModulePage('combodo-anonymizer', "config.php"), $oParentMenu->GetIndex(), 10 /* fRank */);
			}
			
		}
	}
}

class AnonymisationBackgroundProcess implements iBackgroundProcess
{
	/**
	 * @inheritDoc
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 * @throws \MySQLException
	 * @throws \OQLException
	 */
	public function Process($iUnixTimeLimit)
	{
		$sModuleName = basename(__DIR__);
		$bCleanupNotification = MetaModel::GetModuleSetting($sModuleName, 'cleanup_notifications', false);
		$iCountDeleted = 0;
		if ($bCleanupNotification)
		{
			$iRetentionDays = MetaModel::GetModuleSetting($sModuleName, 'notifications_retention', -1);
			if ($iRetentionDays > 0)
			{
				$sOQL = "SELECT EventNotificationEmail WHERE date < :date";
				$oDateLimit = new DateTime();
				$oDateLimit->modify("-$iRetentionDays days");
				$sDateLimit = $oDateLimit->format(AttributeDateTime::GetSQLFormat());
				
				$oSet = new DBObjectSet(DBSearch::FromOQL($sOQL), array('date' => true), array('date' => $sDateLimit));
				while((time() < $iUnixTimeLimit) && ($oNotif = $oSet->Fetch()))
				{
					$oNotif->DBDelete();
					$iCountDeleted++;
				}
			}
		}
		$bAnonymizeObsoletePersons = MetaModel::GetModuleSetting($sModuleName, 'anonymize_obsolete_persons', false);
		$iCountAnonymized = 0;
		if ($bAnonymizeObsoletePersons)
		{
			$iRetentionDays = MetaModel::GetModuleSetting($sModuleName, 'obsolete_persons_retention', -1);
			if ($iRetentionDays > 0)
			{
				$sOQL = "SELECT Person WHERE obsolescence_flag = 1 AND anonymized = 0 AND obsolescence_date < :date";
				$oDateLimit = new DateTime();
				$oDateLimit->modify("-$iRetentionDays days");
				$sDateLimit = $oDateLimit->format(AttributeDateTime::GetSQLFormat());
				
				$oSet = new DBObjectSet(DBSearch::FromOQL($sOQL), array('obsolescence_date' => true), array('date' => $sDateLimit));
				while((time() < $iUnixTimeLimit) && ($oPerson = $oSet->Fetch()))
				{
					$oPerson->Anonymize();
					$iCountAnonymized++;
				}
			}
		}
		$sMessage = sprintf("%d notification(s) deleted, %d person(s) anonymized.", $iCountDeleted, $iCountAnonymized);
		return $sMessage;
	}
	
	/**
	 * @inheritDoc
	 */
	public function GetPeriodicity()
	{
		// Run once per day
		return 24*60*60;
	}
}
