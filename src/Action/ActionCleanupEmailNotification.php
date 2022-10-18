<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

use Combodo\iTop\Anonymizer\Helper\AnonymizerHelper;
use Combodo\iTop\Anonymizer\Helper\AnonymizerLog;
use Combodo\iTop\BackgroundTaskEx\Service\DatabaseService;

/**
 * search for email send to anonymized person
 * anonymize friendly name and email in all fields (subject and body) of these objects
 */
class ActionCleanupEmailNotification extends AnonymizationTaskAction
{
	/**
	 * @throws \CoreException
	 */
	public static function Init()
	{
		$aParams = array
		(
			'category'            => '',
			'key_type'            => 'autoincrement',
			'name_attcode'        => 'name',
			'state_attcode'       => '',
			'reconc_keys'         => array('name'),
			'db_table'            => 'priv_anon_action_cleanup_email_notification',
			'db_key_field'        => 'id',
			'db_finalclass_field' => '',
			'display_template'    => '',
		);
		MetaModel::Init_Params($aParams);
		MetaModel::Init_InheritAttributes();

		// Display lists
		MetaModel::Init_SetZListItems('list', array('name', 'rank')); // Attributes to be displayed for a list
		// Search criteria
		MetaModel::Init_SetZListItems('standard_search', array('name')); // Criteria of the std search form
	}

	/**
	 * build queries search and update to run later in Execute function
	 * search all email notifications with anonymized person as excipient
	 * replace data of anonymized person in subject and body of found objects
	 *
	 *
	 * @return void
	 * @throws \ArchivedObjectException
	 * @throws \CoreCannotSaveObjectException
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 */
	public function InitActionParams()
	{
		$oTask = $this->GetTask();
		$oDatabaseService = new DatabaseService();

		$aParams['iChunkSize'] = MetaModel::GetConfig()->GetModuleParameter(AnonymizerHelper::MODULE_NAME, 'max_chunk_size', 1000);
		$aCleanupEmail = MetaModel::GetConfig()->GetModuleParameter(AnonymizerHelper::MODULE_NAME, 'notification_content');

		if (sizeof($aCleanupEmail) == 0) {
			//nothing to do. We can skip the current action
			$this->Set('action_params', '');
			$this->DBWrite();

			return;
		}

		$aContext = json_decode($oTask->Get('anonymization_context'), true);
		$sOrigFriendlyname = $aContext['origin']['friendlyname'];
		$sTargetFriendlyname = $aContext['anonymized']['friendlyname'];

		$sOrigEmail = $aContext['origin']['email'];
		$sTargetEmail = $aContext['anonymized']['email'];

		if ($sOrigEmail != '') {
			$sStartReplace = "";
			$sEndReplace = "";
			$sStartReplaceEmail = "";
			$sEndReplaceEmail = "";

			if (in_array('friendlyname', $aCleanupEmail)) {
				$sStartReplace = "REPLACE(";
				$sEndReplace = $sEndReplace.", ".CMDBSource::Quote($sOrigFriendlyname).", ".CMDBSource::Quote($sTargetFriendlyname).")";
			}
			$aConditions[] = "`from` like '".$sOrigEmail."'";
			$aConditions[] = "`to` like '%".$sOrigEmail."%'";
			$aConditions[] = "`cc` like '%".$sOrigEmail."%'";
			$aConditions[] = "`bcc` like '%".$sOrigEmail."%'";

			if (in_array('email', $aCleanupEmail)) {
				$sStartReplace = "REPLACE(".$sStartReplace;
				$sEndReplace = $sEndReplace.", ".CMDBSource::Quote($sOrigEmail).", ".CMDBSource::Quote($sTargetEmail).")";
			}
			$sStartReplaceEmail = "REPLACE(".$sStartReplaceEmail;
			$sEndReplaceEmail = $sEndReplaceEmail.", ".CMDBSource::Quote($sOrigEmail).", ".CMDBSource::Quote($sTargetEmail).")";

			// Now change email address
			$sNotificationTable = MetaModel::DBGetTable('EventNotificationEmail');
			$sKey = MetaModel::DBGetKey('EventNotificationEmail');

			$sSqlSearch = "SELECT `$sKey` from `$sNotificationTable` WHERE ".implode(' OR ', $aConditions);
			$sSqlUpdate = "UPDATE `$sNotificationTable` /*JOIN*/ SET".
				"  `from` =  ".$sStartReplaceEmail."`from`".$sEndReplaceEmail.",".
				"  `to` = ".$sStartReplaceEmail."`to`".$sEndReplaceEmail.",".
				"  `cc` = ".$sStartReplaceEmail."`cc`".$sEndReplaceEmail.",".
				"  `bcc` = ".$sStartReplaceEmail."`bcc`".$sEndReplaceEmail.",".
				"  `subject` = ".$sStartReplace."`subject`".$sEndReplace.",".
				"  `body` = ".$sStartReplace."`body`".$sEndReplace." ";

			$aRequest = [];
			$aRequest['search_query'] = $sSqlSearch;
			$aRequest['search_max_id'] = $oDatabaseService->QueryMaxKey($sKey, $sNotificationTable);
			$aRequest['apply_queries'] = [$sNotificationTable => $sSqlUpdate];
			$aRequest['key'] = $sKey;
			$aRequest['search_key'] = $sKey;

			$aParams['aRequest'] = $aRequest;
			$this->Set('action_params', json_encode($aParams));
			$this->DBWrite();
		}
	}


	/**
	 * modify iChunkSize (divide by 2) before continuing to clean the data of the anonymized person
	 *
	 * @return void
	 * @throws \ArchivedObjectException
	 * @throws \CoreCannotSaveObjectException
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 */
	public function ChangeActionParamsOnError(): bool
	{
		$aParams = json_decode($this->Get('action_params'), true);
		$iChunkSize = $aParams['iChunkSize'];
		if ($iChunkSize == 1) {
			AnonymizerLog::Debug('Stop retry action ActionCleanupEmailNotification with params '.json_encode($aParams));
			$this->Set('action_params', '');
			$this->DBWrite();
			return false;
		}
		$aParams['iChunkSize'] = (int)$iChunkSize / 2 + 1;

		$this->Set('action_params', json_encode($aParams));
		$this->DBWrite();
		return true;
	}

	/**
	 * @param $iEndExecutionTime
	 *
	 * @return bool
	 * @throws \ArchivedObjectException
	 * @throws \CoreException
	 */
	public function ExecuteAction($iEndExecutionTime): bool
	{
		try {
			return $this->ExecuteQueries($iEndExecutionTime);
		}
		catch (MySQLHasGoneAwayException $e) {
			//in this case retry is possible
			AnonymizerLog::Error('Error MySQLHasGoneAwayException during ActionCleanupEmailNotification try again later');

			return false;
		}
		catch (Exception $e) {
			AnonymizerLog::Error('Error during ActionCleanupEmailNotification with params '.$this->Get('action_params').' with message :'.$e->getMessage());

			return true;
		}
	}

	/**
	 * @param $iEndExecutionTime
	 *
	 * @return bool
	 * @throws \ArchivedObjectException
	 * @throws \CoreCannotSaveObjectException
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 * @throws \MySQLException
	 * @throws \MySQLHasGoneAwayException
	 */
	public function ExecuteQueries($iEndExecutionTime): bool
	{
		if ($this->Get('action_params') == '') {
			return true;
		}

		$oDatabaseService = new DatabaseService();
		$aParams = json_decode($this->Get('action_params'), true);
		$aRequest = $aParams['aRequest'];

		$iProgress = $aParams['aChangesProgress'] ?? 0;
		$bCompleted = ($iProgress == -1);
		while (!$bCompleted && time() < $iEndExecutionTime) {
			$bCompleted = $oDatabaseService->ExecuteQueriesByChunk($aRequest, $iProgress, $aParams['iChunkSize']);
			// Save progression
			$aParams['aChangesProgress'] = $iProgress;
			$this->Set('action_params', json_encode($aParams));
			$this->DBWrite();
		}

		return $bCompleted;
	}
}