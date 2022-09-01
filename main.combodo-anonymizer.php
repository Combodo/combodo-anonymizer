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

//iBackofficeDictEntriesExtension can only be used since 3.0.1
if (version_compare(ITOP_DESIGN_LATEST_VERSION , '3.1') < 0) {
	/**
	 * Class AnonymizationPlugInLegacy
	 *
	 * @deprecated since 3.1.0
	 */
	class AnonymizationPlugInLegacy implements iPageUIExtension
	{
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
			foreach ($aDictEntries as $sDictCode) {
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
} else {
	/*
	 * Class AnonymizationJsPlugin
	 */

	class AnonymizationJsPlugin implements iBackofficeDictEntriesExtension
	{

		public function GetDictEntries(): array
		{
			return [
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
				'UI:Button:Ok'
			];
		}
	}
}
/**
 * Class AnonymizationMenuPlugIn
 */
class AnonymizationMenuPlugIn implements iPopupMenuExtension
{

	public static function EnumItems($iMenuId, $param)
	{
		$aExtraMenus = array();

		if ( AnonymizationUtils::CanAnonymize()) {
			if (version_compare(ITOP_DESIGN_LATEST_VERSION, '3.0') < 0) {
				$sJSUrl = utils::GetAbsoluteUrlModulesRoot().basename(__DIR__).'/js/anonymize.js';
			} else {
				$sJSUrl = 'env-'.utils::GetCurrentEnvironment().'/'.basename(__DIR__).'/js/anonymize.js';
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

class  AnonymizationUtils
{
	/**
	 * @return bool true if the user have right to anonymize
	 */
	public static function CanAnonymize()
	{
		return (UserRights::IsAdministrator() || UserRights::IsActionAllowed('RessourceAnonymization', UR_ACTION_MODIFY)) ;
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
		// From iTop 2.7, the "ConfigurationTools" menu group exists
		// Before, only "AdminTools" was available for that kind of entry
		$sParentMenuId = ApplicationMenu::GetMenuIndexById('ConfigurationTools') > -1 ? 'ConfigurationTools' : 'AdminTools';
		$sParentMenuIndex = ApplicationMenu::GetMenuIndexById($sParentMenuId);

		if (MetaModel::IsValidClass('ResourceAdminMenu'))
		{
			// iTop version 2.5 or newer, check the rights used when defining the admin menu
			// We cannot directly check if the admin menu is enabled right now, since we are in the process of building the list of menus
			if ( UserRights::IsActionAllowed('RessourceAnonymization', UR_ACTION_MODIFY)) {
				new WebPageMenuNode('ConfigAnonymizer', utils::GetAbsoluteUrlModulePage('combodo-anonymizer', "config.php"), $sParentMenuIndex, 10 , 'ResourceAdminMenu', UR_ACTION_MODIFY, UR_ALLOWED_YES, null);
			}
		}
		else
		{
			// Only administrators
			$bConfigMenuEnabled = UserRights::IsAdministrator();
			if ($bConfigMenuEnabled)
			{
				new WebPageMenuNode('ConfigAnonymizer', utils::GetAbsoluteUrlModulePage('combodo-anonymizer', "config.php"), $sParentMenuIndex, 10 /* fRank */);
			}

		}
	}
}

class PurgeEmailNotification extends AbstractWeeklyScheduledProcess
{

	const MODULE_SETTING_DEBUG = 'debug';
	const MODULE_SETTING_MAX_PER_REQUEST = 'max_buffer_size';
	const MODULE_SETTING_MAX_TIME = 'endtime';

	const DEFAULT_MODULE_SETTING_DEBUG = false;
	const DEFAULT_MODULE_SETTING_MAX_PER_REQUEST = '5';

	protected $bDebug;
	protected $iMaxItemsPerRequest;
	/* max hour of repeat process * */
	protected $sEndTime;
	/* end time of current process*/
	protected $sTimeLimit;

	protected function GetModuleName(){
		return 'combodo-anonymizer';
	}

	protected function GetDefaultModuleSettingTime(){
		return '01:00';
	}

	protected function GetDefaultModuleSettingEndTime(){
		return '05:00';
	}

	/**
	 * AutoCloseTicket constructor.
	 */
	function __construct()
	{
		$this->bDebug = (bool) MetaModel::GetModuleSetting($this->GetModuleName(), static::MODULE_SETTING_DEBUG, static::DEFAULT_MODULE_SETTING_DEBUG);
		$this->iMaxItemsPerRequest = (int) MetaModel::GetModuleSetting($this->GetModuleName(), static::MODULE_SETTING_MAX_PER_REQUEST, static::DEFAULT_MODULE_SETTING_MAX_PER_REQUEST);
		$this->sEndTime = MetaModel::GetModuleSetting($this->GetModuleName(), static::MODULE_SETTING_MAX_TIME,$this->GetDefaultModuleSettingEndTime());
		$this->sTimeLimit = time()+100;
	}
	/**
	 * @inheritDoc
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 * @throws \MySQLException
	 * @throws \OQLException
	 */
	public function Process($iUnixTimeLimit)
	{
		$this->sTimeLimit = $iUnixTimeLimit;
		$iMaxBufferSize =   MetaModel::GetModuleSetting('combodo-anonymizer', 'max_buffer_size', 1000);
		$bCleanupNotification = MetaModel::GetModuleSetting($this->GetModuleName(), 'cleanup_notifications', false);
		$iCountDeleted = 0;
		if ($bCleanupNotification)
		{
			$iRetentionDays = MetaModel::GetModuleSetting($this->GetModuleName(), 'notifications_retention', -1);
			if ($iRetentionDays > 0)
			{
				$sOQL = "SELECT EventNotificationEmail WHERE date < :date";
				$oDateLimit = new DateTime();
				$oDateLimit->modify("-$iRetentionDays days");
				$sDateLimit = $oDateLimit->format(AttributeDateTime::GetSQLFormat());

				$this->Trace('|- Parameters:');
				$this->Trace('|  |- OQL scope: '.$sOQL);
				$this->Trace('|  |- sDateLimit: '.$sDateLimit);

				$bExecuteQuery = true;
				//split update by lot
				while ((time() < $iUnixTimeLimit) && $bExecuteQuery) {
					$iCountCurrentQuery = 0;
					$oSet = new DBObjectSet(DBSearch::FromOQL($sOQL), array('date' => true), array('date' => $sDateLimit), null, $iMaxBufferSize);
					while ((time() < $iUnixTimeLimit) && ($oNotif = $oSet->Fetch())) {
						$oNotif->DBDelete();
						$iCountDeleted++;
						$iCountCurrentQuery++;
					}
					if($iCountCurrentQuery<$iMaxBufferSize){
						$bExecuteQuery = false;
					}
				}
			}
		}
		$sMessage = sprintf("%d notification(s) deleted", $iCountDeleted );
		return $sMessage;
	}

	/**
	 * @inheritDoc
	 */
	public function GetNextOccurrence($sCurrentTime = 'now')
	{
		$bEnabled = $this->getOConfig()->GetModuleSetting($this->GetModuleName(),	static::MODULE_SETTING_ENABLED,	static::DEFAULT_MODULE_SETTING_ENABLED);
		//if background process is disabled
		if (!$bEnabled)
		{
			return new DateTime('3000-01-01');
		}

		if (!preg_match('/[0-2][0-9]:[0-5][0-9]/', $this->sEndTime)) {
			throw new Exception($this->GetModuleName().": wrong format for setting 'time' (found '$this->sEndTime')");
		}
		$dEndToday = new DateTime();
		list($sHours, $sMinutes) = explode(':', $this->sEndTime);
		$dEndToday->setTime((int)$sHours, (int)$sMinutes);
		$iEndTimeToday = $dEndToday->getTimestamp();
		$this->Trace('End time:'. $this->sEndTime) ;
		$this->Trace('Next occurence:'.$iEndTimeToday  );
		$this->Trace('timelimit:'.$this->sTimeLimit  );
		$this->Trace('time actuel:'.time()  );

		//IF FINISH next time is tomorrow
		if (time() < $this->sTimeLimit || time() > $iEndTimeToday) {
			$this->Trace('Next day'  );
			// 1st - Interpret the list of days as ordered numbers (monday = 1)
			$aDays = $this->InterpretWeekDays();

			// 2nd - Find the next active week day
			//
			$sStartTime = MetaModel::GetConfig()->GetModuleSetting($this->GetModuleName(), static::MODULE_SETTING_TIME,  $this->GetDefaultModuleSettingTime());
			if (!preg_match('/[0-2][0-9]:[0-5][0-9]/', $sStartTime)) {
				throw new Exception($this->GetModuleName().": wrong format for setting 'time' (found '$sStartTime')");
			}
			$oNow = new DateTime();
			$iNextPos = false;
			for ($iDay = $oNow->format('N'); $iDay <= 7; $iDay++) {
				$iNextPos = array_search($iDay, $aDays);
				if ($iNextPos !== false) {
					if (($iDay > $oNow->format('N')) || ($oNow->format('H:i') < $sStartTime)) {
						break;
					}
					$iNextPos = false; // necessary on sundays
				}
			}

			// 3rd - Compute the result
			//
			if ($iNextPos === false) {
				// Jump to the first day within the next week
				$iFirstDayOfWeek = $aDays[0];
				$iDayMove = $oNow->format('N') - $iFirstDayOfWeek;
				$oRet = clone $oNow;
				$oRet->modify('-'.$iDayMove.' days');
				$oRet->modify('+1 weeks');
			} else {
				$iNextDayOfWeek = $aDays[$iNextPos];
				$iMove = $iNextDayOfWeek - $oNow->format('N');
				$oRet = clone $oNow;
				$oRet->modify('+'.$iMove.' days');
			}
			list($sHours, $sMinutes) = explode(':', $sStartTime);
			$oRet->setTime((int)$sHours, (int)$sMinutes);
			return $oRet;
		} else {
			//TRY ANOTHER TIME next time is 5 min later
			$this->Trace('Later'  );

			$oPlannedStart = new DateTime();
			$oPlannedStart->modify('+ 30 seconds');

			return $oPlannedStart;
		}
	}

	/**
	 * Prints a $sMessage in the cron output.
	 *
	 * @param string $sMessage
	 */
	protected function Trace($sMessage)
	{
		echo $sMessage."\n";
	}
}



class PersonalDataAnonymizer extends PurgeEmailNotification
{

	protected function GetDefaultModuleSettingTime(){
		return '01:00';
	}

	protected function GetDefaultModuleSettingEndTime(){
		return '05:00';
	}

	/**
	 * @inheritDoc
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 * @throws \MySQLException
	 * @throws \OQLException
	 */
	public function Process($iUnixTimeLimit)
	{
		$this->sTimeLimit = $iUnixTimeLimit;
		$iMaxBufferSize =   MetaModel::GetModuleSetting('combodo-anonymizer', 'max_buffer_size', 1000);

		$oResult = CMDBSource::Query("SELECT DISTINCT idToAnonymize FROM priv_batch_anonymization");
		$aIdPersonAlreadyInProgress = [] ;
		if ($oResult->num_rows>0) {
			while ($oRaw = $oResult->fetch_assoc()) {
				$aIdPersonAlreadyInProgress[] = $oRaw['idToAnonymize'];
			}
		}
		$bAnonymizeObsoletePersons = MetaModel::GetModuleSetting($this->GetModuleName(), 'anonymize_obsolete_persons', false);
		$iCountAnonymized = 0;
		if ($bAnonymizeObsoletePersons)
		{
			$iRetentionDays = MetaModel::GetModuleSetting($this->GetModuleName(), 'obsolete_persons_retention', -1);
			if ($iRetentionDays > 0)
			{
				$sOQL = "SELECT Person WHERE obsolescence_flag = 1 AND anonymized = 0 AND obsolescence_date < :date";
				if (sizeof($aIdPersonAlreadyInProgress)>0){
					$sOQL .= " AND id NOT IN (".implode(",", $aIdPersonAlreadyInProgress).")";
				}
				$this->Trace('RetentionDays'.$iRetentionDays);
				$this->Trace($sOQL);
				$oDateLimit = new DateTime();
				$oDateLimit->modify("-$iRetentionDays days");
				$sDateLimit = $oDateLimit->format(AttributeDateTime::GetSQLFormat());

				$this->Trace('|- Parameters:');
				$this->Trace('|  |- OQL scope: '.$sOQL);
				$this->Trace('|  |- sDate Limit: '.$sDateLimit);

				$bExecuteQuery = true;
				while ((time() < $iUnixTimeLimit) && $bExecuteQuery) {
					$iCountCurrentQuery = 0;
					$oSet = new DBObjectSet(DBSearch::FromOQL($sOQL), array('obsolescence_date' => true), array('date' => $sDateLimit), null, $iMaxBufferSize);
					while ((time() < $iUnixTimeLimit) && ($oPerson = $oSet->Fetch())) {
						$oPerson->Anonymize();
						$iCountAnonymized++;
						$iCountCurrentQuery++;
					}
					if ($iCountCurrentQuery < $iMaxBufferSize) {
						$bExecuteQuery = false;
					}
				}
			}
		}

		$iStepAnonymized = 0;
		$sOQL = "SELECT BatchAnonymization";
		$bExecuteQuery = true;

		$this->Trace('|- Parameters:');
		$this->Trace('|  |- OQL scope: '.$sOQL);
		$iNbPersonAnonymized = 0;

		while ((time() < $iUnixTimeLimit) && $bExecuteQuery) {
			$oSet = new DBObjectSet(DBSearch::FromOQL($sOQL), array(), array(), null, $iMaxBufferSize);
			$sIdCurrentPerson = '';
			while ((time() < $iUnixTimeLimit) && ($oStepForAnonymize = $oSet->Fetch())) {
				if ($sIdCurrentPerson != $oStepForAnonymize->Get('idToAnonymize')){
					if ($sIdCurrentPerson != '') {
						$iNbPersonAnonymized++;
					}
					$sIdCurrentPerson = $oStepForAnonymize->Get('idToAnonymize');
				}
				$oStepForAnonymize->executeStep($iUnixTimeLimit);
				$iStepAnonymized++;
			}

			if (time() < $iUnixTimeLimit && $sIdCurrentPerson != ''){
				$iNbPersonAnonymized++;
			}
			$this->Trace('iStepAnonymized: '.$iStepAnonymized);
			if ($iStepAnonymized < $iMaxBufferSize) {
				$bExecuteQuery = false;
			}
		}
		$sMessage = sprintf("Anonymization started for %d person(s). %d person(s) completly anonymized.%d step(s) executed", $iCountAnonymized,  $iNbPersonAnonymized, $iStepAnonymized );
		return $sMessage;
	}
	}

