<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */


use Combodo\iTop\Anonymizer\Helper\AnonymizerHelper;
use Combodo\iTop\Anonymizer\Helper\AnonymizerLog;
use Combodo\iTop\BackgroundTaskEx\Service\DatabaseService;

/**
 * search for objects with caselogs created by a user of the anonymized person.
 * anonymize friendly name and email in all text fields of these objects
 */
class ActionCleanupCaseLogs extends AnonymizationTaskAction
{
	const USER_CLASS = 'User';

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
			'db_table'            => 'priv_anonymization_task_action_cleanup_caselogs',
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
	 * search all objects with caselogs with anonymized user as writer
	 * replace data of anonymized user with  *** in caselogs in order to maintain index of caselog
	 * replace data of anonymized person in all text and string attributes of found objects
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

		$aParams['iChunkSize'] = MetaModel::GetConfig()->GetModuleParameter(AnonymizerHelper::MODULE_NAME, 'max_chunk_size', 1000);
		$aCleanupCaseLog = (array)MetaModel::GetConfig()->GetModuleParameter(AnonymizerHelper::MODULE_NAME, 'caselog_content');

		$aRequests = [];

		$aContext = json_decode($oTask->Get('anonymization_context'), true);
		$sId = $oTask->Get('id_to_anonymize');

		$oSearch = new DBObjectSearch(self::USER_CLASS);
		$oSearch->AddCondition('contactid', $sId);
		$oSearch->AllowAllData();
		$oSet = new DBObjectSet($oSearch);
		$oSet->OptimizeColumnLoad(array(self::USER_CLASS => array('finalclass')));
		$aIdWithClass = $oSet->GetColumnAsArray('finalclass');
		$aIdUser = array_keys($aIdWithClass);

		if (count($aIdUser) == 0) {
			//nothing to do
			return;
		}

		$sOrigFriendlyname = $aContext['origin']['friendlyname'];
		$sTargetFriendlyname = $aContext['anonymized']['friendlyname'];

		$sOrigEmail = $aContext['origin']['email'];
		$sTargetEmail = $aContext['anonymized']['email'];
		// 1) Build the expression to search (and replace)
		$sPattern = ' : %1$s (%2$d) ============';

		$sReplaceInIdx = str_repeat('*', strlen($sOrigFriendlyname));
		$sStartReplaceInIdx = "REPLACE(";
		$sEndReplaceInIdx = ", ".CMDBSource::Quote($sOrigFriendlyname).", ".CMDBSource::Quote($sReplaceInIdx).")";

		if (in_array('friendlyname', $aCleanupCaseLog)) {
			$sReplace = str_repeat('*', strlen($sOrigFriendlyname));;
			$sStartReplace = "REPLACE(";
			$sEndReplaceInCaseLog = ", ".CMDBSource::Quote($sOrigFriendlyname).", ".CMDBSource::Quote($sReplace).")";
			$sEndReplaceInTxt = ", ".CMDBSource::Quote($sOrigFriendlyname).", ".CMDBSource::Quote($sTargetFriendlyname).")";
		} else {
			$sStartReplace = '';
			$sEndReplaceInCaseLog = '';
			$sEndReplaceInTxt = "";
			foreach ($aIdUser as $sIdUser) {
				$sSearch = sprintf($sPattern, $sOrigFriendlyname, $sIdUser);
				$sReplace = sprintf($sPattern, str_repeat('*', strlen($sOrigFriendlyname)), $sIdUser);

				$sStartReplace = "REPLACE(".$sStartReplace;
				$sEndReplaceInCaseLog = $sEndReplaceInCaseLog.", ".CMDBSource::Quote($sSearch).", ".CMDBSource::Quote($sReplace).")";
				$sEndReplaceInTxt = ", ".CMDBSource::Quote($sOrigFriendlyname).", ".CMDBSource::Quote($sTargetFriendlyname).")";
			}
		}

		if ($sOrigEmail != '' && in_array('email', $aCleanupCaseLog)) {
			$sReplace = str_repeat('*', strlen($sOrigEmail));

			$sStartReplace = "REPLACE(".$sStartReplace;
			$sEndReplaceInCaseLog = $sEndReplaceInCaseLog.", ".CMDBSource::Quote($sOrigEmail).", ".CMDBSource::Quote($sReplace).")";
			$sEndReplaceInTxt = $sEndReplaceInTxt.", ".CMDBSource::Quote($sOrigEmail).", ".CMDBSource::Quote($sTargetEmail).")";
		}

		// 2) Find all classes containing case logs
		foreach (MetaModel::GetClasses() as $sClass) {
			foreach (MetaModel::ListAttributeDefs($sClass) as $sAttCode => $oAttDef) {
				$sTable = MetaModel::DBGetTable($sClass);
				$sKey = MetaModel::DBGetKey($sClass);
				if ((MetaModel::GetAttributeOrigin($sClass, $sAttCode) == $sClass) && $oAttDef instanceof AttributeCaseLog) {
					$aSQLColumns = $oAttDef->GetSQLColumns();
					$sColumn = array_keys($aSQLColumns)[0]; // We assume that the first column is the text

					$aConditions = [];
					foreach ($aIdUser as $sIdUser) {
						$aConditions[] = " `$sColumn` LIKE ".CMDBSource::Quote('%'.sprintf($sPattern, $sOrigFriendlyname, $sIdUser).'%');
					}
					$sCondition = implode(' OR ', $aConditions);
					$sSqlSearch = "SELECT  `$sKey` FROM `$sTable` WHERE $sCondition";

					$aColumnsToUpdate = $this->GetColumnsToUpdate($sClass,  $sStartReplace, $sEndReplaceInCaseLog, $sEndReplaceInTxt, $sStartReplaceInIdx, $sEndReplaceInIdx);

					$aSqlUpdate = [];
					foreach ($aColumnsToUpdate as $sTable => $aRequestReplace) {
						$sSqlUpdate = "UPDATE `$sTable` /*JOIN*/ ".
							"SET ".implode(' , ', $aRequestReplace);
						$aSqlUpdate[$sTable] = $sSqlUpdate;
					}
					$aAction = [];
					$aAction['search_query'] = $sSqlSearch;
					$aAction['apply_queries'] = $aSqlUpdate;
					$aAction['search_key'] = $sKey;
					$aAction['key'] = $sKey;
					$aRequests[$sClass.'-'.$sAttCode] = $aAction;
				}
			}
		}
		$aParams['aRequests'] = $aRequests;
		$this->Set('action_params', json_encode($aParams));
		$this->DBWrite();
	}

	private function GetColumnsToUpdate($sClass, $sStartReplace, $sEndReplaceInCaseLog, $sEndReplaceInTxt, $sStartReplaceInIdx, $sEndReplaceInIdx)
	{
		$aColumnsToUpdate = [];
		$aClasses = array_merge([$sClass], MetaModel::GetSubclasses($sClass));
		foreach ($aClasses as $sClass) {
			foreach (MetaModel::ListAttributeDefs($sClass) as $sAttCode => $oAttDef) {
				$sTable = MetaModel::DBGetTable($sClass, $sAttCode);
				if ($oAttDef instanceof AttributeCaseLog) {
					$aSQLColumns = $oAttDef->GetSQLColumns();
					$sColumn = array_keys($aSQLColumns)[0]; // We assume that the first column is the text
					$sColumnIdx = array_keys($aSQLColumns)[1]; // We assume that the second column is the index
					$aColumnsToUpdate[$sTable][$sColumn] = " `$sColumn` = ".$sStartReplace."`$sColumn`".$sEndReplaceInCaseLog;
					$aColumnsToUpdate[$sTable][$sColumnIdx] = " `$sColumnIdx` = ".$sStartReplaceInIdx."`$sColumnIdx`".$sEndReplaceInIdx.' ';
				} elseif ($oAttDef instanceof AttributeText || ($oAttDef instanceof AttributeString && !($oAttDef instanceof AttributeFinalClass))) {
					$aSQLColumns = $oAttDef->GetSQLColumns();
					$sColumn = array_keys($aSQLColumns)[0]; //
					$aColumnsToUpdate[$sTable][$sColumn] = " `$sColumn` = ".$sStartReplace."`$sColumn`".$sEndReplaceInTxt;
				}
			}
		}
		return $aColumnsToUpdate;
	}

	/**
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
			AnonymizerLog::Debug('Stop retry action ActionCleanupCaseLogs with params '.json_encode($aParams));
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
	 * @throws \CoreCannotSaveObjectException
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 */
	public function ExecuteAction($iEndExecutionTime): bool
	{
		if ($this->Get('action_params') == '') {
			return true;
		}

		$oDatabaseService = new DatabaseService();
		$aParams = json_decode($this->Get('action_params'), true);
		$aRequests = $aParams['aRequests'];

		foreach ($aRequests as $sName => $aRequest) {
			$iProgress = $aParams['aChangesProgress'][$sName] ?? 0;
			$bCompleted = ($iProgress == -1);
			AnonymizerLog::Debug("=> Request: $sName Progress: $iProgress");
			while (!$bCompleted && time() < $iEndExecutionTime) {
				try {
					$bCompleted = $oDatabaseService->ExecuteQueriesByChunk($aRequest, $iProgress, $aParams['iChunkSize']);
					$aParams['aChangesProgress'][$sName] = $iProgress;
				}
				catch (MySQLHasGoneAwayException $e) {
					//in this case retry is possible
					AnonymizerLog::Error('Error MySQLHasGoneAwayException during ActionCleanupCaseLogs try again later');

					return false;
				}
				catch (Exception $e) {
					AnonymizerLog::Error('Error during ActionCleanupCaseLogs with message :'.$e->getMessage());
					$aParams['aChangesProgress'][$sName] = -1;
				}
				// Save progression
				AnonymizerLog::Debug("Save progression: ".json_encode($aParams));
				$this->Set('action_params', json_encode($aParams));
				$this->DBWrite();
			}
			if (!$bCompleted) {
				// Timeout
				AnonymizerLog::Debug("Timeout with progression: $iProgress");

				return false;
			}
		}

		return true;
	}
}