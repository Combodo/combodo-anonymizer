<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\Anonymizer\Action;

use BatchAnonymizationTaskAction;
use CMDBSource;
use Combodo\iTop\Anonymizer\Helper\AnonymizerHelper;
use Combodo\iTop\Anonymizer\Helper\AnonymizerLog;
use Combodo\iTop\Anonymizer\Service\CleanupService;
use Exception;
use MetaModel;
use MySQLHasGoneAwayException;

class CleanupEmailNotification extends BatchAnonymizationTaskAction
{
	/**
	 * @return void
	 * @throws \ArchivedObjectException
	 * @throws \CoreCannotSaveObjectException
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 */
	public function InitActionParams()
	{
		$oTask = $this->GetTask();

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
			$this->Set('action_params', json_encode($aParams));
			$this->DBWrite();
		}
	}


	public function ChangeActionParamsOnError()
	{
		$aParams = json_decode($this->Get('action_params'), true);
		$iChunkSize = $aParams['iChunkSize'];
		if ($iChunkSize == 1) {
			AnonymizerLog::Debug('Stop retry action CleanupEmailNotification with params '.json_encode($aParams));
			$this->Set('action_params', '');
			$this->DBWrite();
		}
		$aParams['iChunkSize'] = (int)$iChunkSize / 2 + 1;

		$this->Set('action_params', json_encode($aParams));
		$this->DBWrite();
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
			AnonymizerLog::Error('Error MySQLHasGoneAwayException during CleanupEmailNotification try again later');

			return false;
		}
		catch (Exception $e) {
			AnonymizerLog::Error('Error during CleanupEmailNotification with params '.$this->Get('action_params').' with message :'.$e->getMessage());

			return true;
		}
	}

	/**
	 * @param $iEndExecutionTime
	 *
	 * @return bool
	 * @throws \ArchivedObjectException
	 * @throws \Combodo\iTop\Anonymizer\Helper\AnonymizerException
	 * @throws \CoreCannotSaveObjectException
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 * @throws \MySQLException
	 * @throws \MySQLHasGoneAwayException
	 */
	public function ExecuteQueries($iEndExecutionTime): bool
	{
		$oTask = $this->GetTask();
		if ($this->Get('action_params') == '') {
			return true;
		}
		$sClass = $oTask->Get('class_to_anonymize');
		$sId = $oTask->Get('id_to_anonymize');

		$oService = new CleanupService($sClass, $sId, $iEndExecutionTime);
		$aParams = json_decode($this->Get('action_params'), true);
		$aRequest = $aParams['aRequest'];

		$iProgress = $aParams['aChangesProgress'] ?? 0;
		$bCompleted = ($iProgress == -1);
		while (!$bCompleted && time() < $iEndExecutionTime) {
			$bCompleted = $oService->ExecuteActionWithQueriesByChunk($aRequest['select'], $aRequest['updates'], $aRequest['key'], $iProgress, $aParams['iChunkSize']);
			// Save progression
			$aParams['aChangesProgress'] = $iProgress;
			$this->Set('action_params', json_encode($aParams));
			$this->DBWrite();
		}

		return $bCompleted;
	}
}