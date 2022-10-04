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
use Combodo\iTop\Anonymizer\Helper\AnonymizerHelper;

class PurgeEmailNotification extends AbstractWeeklyScheduledProcess
{

	const MODULE_SETTING_DEBUG = 'debug';
	const MODULE_SETTING_MAX_PER_REQUEST = 'max_chunk_size';
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
		return AnonymizerHelper::MODULE_NAME;
	}

	protected function GetDefaultModuleSettingTime(){
		return '01:00';
	}

	protected function GetDefaultModuleSettingEndTime(){
		return '05:00';
	}

	/**
	 * PurgeEmailNotification constructor.
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
		$iMaxChunkSize =   MetaModel::GetModuleSetting($this->GetModuleName(), 'max_chunk_size', 1000);
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
					$oSet = new DBObjectSet(DBSearch::FromOQL($sOQL), array('date' => true), array('date' => $sDateLimit), null, $iMaxChunkSize);
					while ((time() < $iUnixTimeLimit) && ($oNotif = $oSet->Fetch())) {
						$oNotif->DBDelete();
						$iCountDeleted++;
						$iCountCurrentQuery++;
					}
					if($iCountCurrentQuery<$iMaxChunkSize){
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
			//TRY ANOTHER TIME next time is 2 seconds  later
			$this->Trace('Later'  );

			$oPlannedStart = new DateTime();
			$oPlannedStart->modify('+ 2 seconds');

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

