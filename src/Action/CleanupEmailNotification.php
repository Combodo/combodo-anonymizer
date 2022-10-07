<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\Anonymizer\Action;

use CMDBSource;
use Combodo\iTop\Anonymizer\Helper\AnonymizerHelper;
use Combodo\iTop\Anonymizer\Helper\AnonymizerLog;
use Combodo\iTop\Anonymizer\Service\CleanupService;
use MetaModel;
use MySQLHasGoneAwayException;

class CleanupEmailNotification extends AbstractAnonymizationAction
{
	const USER_CLASS = 'User';

	public function Init()
	{
		$aParams['iChunkSize'] = MetaModel::GetConfig()->GetModuleParameter(AnonymizerHelper::MODULE_NAME, 'max_chunk_size', 1000);
		$aCleanupEmail = MetaModel::GetConfig()->GetModuleParameter(AnonymizerHelper::MODULE_NAME, 'notification_content');

		if (sizeof($aCleanupEmail) == 0) {
			//nothing to do. We can skip the current action
			$this->oTask->Set('action_params', '');
			$this->oTask->DBWrite();

			return;
		}

		$aContext = json_decode($this->oTask->Get('anonymization_context'), true);
		$sOrigFriendlyname = $aContext['origin']['friendlyname'];
		$sTargetFriendlyname = $aContext['anonymized']['friendlyname'];

		$sOrigEmail = $aContext['origin']['email'];
		$sTargetEmail = $aContext['anonymized']['email'];

		$sStartReplace = "";
		$sEndReplace = "";
		$sStartReplaceEmail = "";
		$sEndReplaceEmail = "";

		if (in_array('friendlyname', $aCleanupEmail)) {
			//	foreach ($sOrigFriendlyname as $sFriendlyName) {
			$sStartReplace = "REPLACE(";
			$sEndReplace = $sEndReplace.", ".CMDBSource::Quote($sOrigFriendlyname).", ".CMDBSource::Quote($sTargetFriendlyname).")";
			//	}
		}
//		foreach ($sOrigEmail as $sEmail) {
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
//		}

		// Now change email adress
		$sNotificationTable = MetaModel::DBGetTable('EventNotificationEmail');
		$sKey = MetaModel::DBGetKey('EventNotificationEmail');

		$sSqlSearch = "SELECT `$sKey` from `$sNotificationTable` WHERE ".implode(' OR ', $aConditions);
		$sSqlUpdate = "UPDATE `$sNotificationTable` SET".
			"  `from` =  ".$sStartReplaceEmail."`from`".$sEndReplaceEmail.",".
			"  `to` = ".$sStartReplaceEmail."`to`".$sEndReplaceEmail.",".
			"  `cc` = ".$sStartReplaceEmail."`cc`".$sEndReplaceEmail.",".
			"  `bcc` = ".$sStartReplaceEmail."`bcc`".$sEndReplaceEmail.",".
			"  `subject` = ".$sStartReplace."`subject`".$sEndReplace.",".
			"  `body` = ".$sStartReplace."`body`".$sEndReplace." ";

		$aRequest = [];
		$aRequest['select'] = $sSqlSearch;
		$aRequest['updates'] = [$sSqlUpdate];
		$aRequest['key'] = $sKey;

		$aParams['aRequest'] = $aRequest;
		$this->oTask->Set('action_params', json_encode($aParams));
		$this->oTask->DBWrite();
	}


	public function Retry()
	{
		$aParams = json_decode($this->oTask->Get('action_params'), true);
		$iChunkSize = $aParams['iChunkSize'];
		if ($iChunkSize == 1) {
			AnonymizerLog::Debug('Stop retry action CleanupEmailNotification with params '.json_encode($aParams));
			$this->oTask->Set('action_params', '');
			$this->oTask->DBWrite();
		}
		$aParams['iChunkSize'] = (int)$iChunkSize / 2 + 1;

		$this->oTask->Set('action_params', json_encode($aParams));
		$this->oTask->DBWrite();
	}

	/**
	 * @return bool
	 * @throws \ArchivedObjectException
	 * @throws \CoreCannotSaveObjectException
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 */
	public function Execute(): bool
	{
		try {
			return $this->ExecuteQueries($this->oTask);
		}
		catch (MySQLHasGoneAwayException $e) {
			//in this case retry is possible
			AnonymizerLog::Error('Error MySQLHasGoneAwayException during CleanupEmailNotification try again later');

			return false;
		}
		catch (\Exception $e) {
			AnonymizerLog::Error('Error during CleanupEmailNotification with params '.$this->oTask->Get('action_params').' with message :'.$e->getMessage());

			return true;
		}
	}

	public function ExecuteQueries($oTask)
	{
		if ($oTask->Get('action_params') == '') {
			return true;
		}
		$sClass = $this->oTask->Get('class_to_anonymize');
		$sId = $this->oTask->Get('id_to_anonymize');

		$oService = new CleanupService($sClass, $sId, $this->iEndExecutionTime);
		$aParams = json_decode($oTask->Get('action_params'), true);
		$aRequest = $aParams['aRequest'];

		$iProgress = $aParams['aChangesProgress'] ?? 0;
		$bCompleted = ($iProgress == -1);
		while (!$bCompleted && time() < $this->iEndExecutionTime) {
			$bCompleted = $oService->ExecuteActionWithQueriesByChunk($aRequest['select'], $aRequest['updates'], $aRequest['key'], $iProgress, $aParams['iChunkSize']);
			// Save progression
			$aParams['aChangesProgress'] = $iProgress;
			$this->oTask->Set('action_params', json_encode($aParams));
			$this->oTask->DBWrite();
		}

		return $bCompleted;
	}
}