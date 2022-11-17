<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */


use Combodo\iTop\Anonymizer\Helper\AnonymizerHelper;
use Combodo\iTop\Anonymizer\Helper\AnonymizerLog;
use Combodo\iTop\BackgroundTaskEx\Service\DatabaseService;

/**
 * search for objects with case logs created by a user of the anonymized person.
 * anonymize friendly name and email in all text fields of these objects
 */
class ActionCleanupCaseLogs extends AnonymizationTaskAction
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
			'db_table'            => 'priv_anonym_action_cleanup_caselogs',
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
	public function InitActionParams(): bool
	{
		$oTask = $this->GetTask();

		$aCleanupCaseLog = (array)MetaModel::GetConfig()->GetModuleSetting(AnonymizerHelper::MODULE_NAME, 'caselog_content');
		$aRequests = [];

		$aContext = json_decode($oTask->Get('anonymization_context'), true);
		$sId = $oTask->Get('person_id');

		$oSearch = new DBObjectSearch(User::class);
		$oSearch->AddCondition('contactid', $sId);
		$oSearch->AllowAllData();
		$oSet = new DBObjectSet($oSearch);
		$oSet->OptimizeColumnLoad(array(User::class => array('finalclass')));
		$aIdWithClass = $oSet->GetColumnAsArray('finalclass');
		$aIdUser = array_keys($aIdWithClass);

		$iChangeOpId = $aContext['origin']['changeop_id'] ?? 0;
		$sOrigFriendlyname = $aContext['origin']['user_friendlyname'];
		$sTargetFriendlyname = $aContext['anonymized']['friendlyname'];

		$sOrigEmail = $aContext['origin']['email'];
		$sTargetEmail = $aContext['anonymized']['email'];

		// 1) Build the expression to search (and replace)
		$sPattern = ' : %1$s (%2$d) ============';

		$sReplaceInIdx = str_repeat('*', strlen($sOrigFriendlyname));
		$sStartReplaceInIdx = "REPLACE(";
		$sEndReplaceInIdx = ", ".CMDBSource::Quote($sOrigFriendlyname).", ".CMDBSource::Quote($sReplaceInIdx).")";

		$sStartReplace = '';
		$sEndReplaceInCaseLog = '';
		$sEndReplaceInTxt = '';
		if ($sOrigFriendlyname !== '') {
			if (in_array('friendlyname', $aCleanupCaseLog)) {
				$sReplace = str_repeat('*', strlen($sOrigFriendlyname));;
				$sStartReplace = "REPLACE(";
				$sEndReplaceInCaseLog = ", ".CMDBSource::Quote($sOrigFriendlyname).", ".CMDBSource::Quote($sReplace).")";
				$sEndReplaceInTxt = ", ".CMDBSource::Quote($sOrigFriendlyname).", ".CMDBSource::Quote($sTargetFriendlyname).")";
			} else {
				foreach ($aIdUser as $sIdUser) {
					$sSearch = sprintf($sPattern, $sOrigFriendlyname, $sIdUser);
					$sReplace = sprintf($sPattern, str_repeat('*', strlen($sOrigFriendlyname)), $sIdUser);

					$sStartReplace = "REPLACE(".$sStartReplace;
					$sEndReplaceInCaseLog = $sEndReplaceInCaseLog.", ".CMDBSource::Quote($sSearch).", ".CMDBSource::Quote($sReplace).")";
				}
				$sEndReplaceInTxt = ', '.CMDBSource::Quote($sOrigFriendlyname).', '.CMDBSource::Quote($sTargetFriendlyname).')';
			}
		}

		if ($sOrigEmail !== '' && in_array('email', $aCleanupCaseLog)) {
			$sReplace = str_repeat('*', strlen($sOrigEmail));

			$sStartReplace = "REPLACE(".$sStartReplace;
			$sEndReplaceInCaseLog = $sEndReplaceInCaseLog.", ".CMDBSource::Quote($sOrigEmail).", ".CMDBSource::Quote($sReplace).")";
			$sEndReplaceInTxt = $sEndReplaceInTxt.", ".CMDBSource::Quote($sOrigEmail).", ".CMDBSource::Quote($sTargetEmail).")";
		}

		$oDatabaseService = new DatabaseService();

		// 2) Find all classes containing case logs
		foreach (MetaModel::GetClasses() as $sClass) {
			if (MetaModel::IsLeafClass($sClass)) {
				$sLeafTable = MetaModel::DBGetTable($sClass);
				$sKey = MetaModel::DBGetKey($sClass);
				$bHasCaseLog = false;
				foreach (MetaModel::ListAttributeDefs($sClass) as $oAttDef) {
					if ($oAttDef instanceof AttributeCaseLog) {
						$bHasCaseLog = true;
						break;
					}
				}

				if ($bHasCaseLog) {
					// Search case logs by the changes
					$sSqlSearch = $this->GetCaseLogChangeQuery($sClass, $sSearchKey, $sOrigFriendlyname, $iChangeOpId);
					$aColumnsToUpdate = $this->GetColumnsToUpdate($sClass, $sStartReplace, $sEndReplaceInCaseLog, $sEndReplaceInTxt, $sStartReplaceInIdx, $sEndReplaceInIdx);
					$aSqlUpdate = [];
					foreach ($aColumnsToUpdate as $sTable => $aRequestReplace) {
						$sSqlUpdate = "UPDATE `$sTable` /*JOIN*/ ".
							"SET ".implode(' , ', $aRequestReplace);
						$aSqlUpdate[$sTable] = $sSqlUpdate;
					}
					$aAction = [];
					$aAction['class'] = $sClass;
					$aAction['search_query'] = $sSqlSearch;
					$aAction['search_max_id'] = $oDatabaseService->QueryMaxKey($sKey, $sLeafTable);
					$aAction['apply_queries'] = $aSqlUpdate;
					$aAction['search_key'] = $sSearchKey;
					$aAction['key'] = $sKey;
					$aRequests[$sClass] = $aAction;
				}
			}
		}
		$aParams = [
			'aRequests'  => $aRequests,
			'iChunkSize' => MetaModel::GetConfig()->GetModuleSetting(AnonymizerHelper::MODULE_NAME, 'init_chunk_size', 1000),
		];
		$this->Set('action_params', json_encode($aParams));
		$this->DBWrite();

		return true;
	}

	private function GetCaseLogChangeQuery($sClass, &$sKey, $sOrigFriendlyname, $iChangeOpId)
	{
		$sChangeTable = MetaModel::DBGetTable('CMDBChange');
		$sChangeKey = MetaModel::DBGetKey('CMDBChange');
		$sChangeOpTable = MetaModel::DBGetTable('CMDBChangeOp');
		$sChangeOpId = MetaModel::DBGetKey('CMDBChangeOp');
		$oAttDef = MetaModel::GetAttributeDef('CMDBChangeOp', 'change');
		$aColumns = array_keys($oAttDef->GetSQLColumns());
		$sChangeOpChangeId = reset($aColumns);
		$oAttDef = MetaModel::GetAttributeDef('CMDBChangeOp', 'finalclass');
		$aColumns = array_keys($oAttDef->GetSQLColumns());
		$sChangeOpFinalClass = reset($aColumns);
		$oAttDef = MetaModel::GetAttributeDef('CMDBChangeOp', 'objkey');
		$aColumns = array_keys($oAttDef->GetSQLColumns());
		$sObjKey = reset($aColumns);
		$oAttDef = MetaModel::GetAttributeDef('CMDBChangeOp', 'objclass');
		$aColumns = array_keys($oAttDef->GetSQLColumns());
		$sObjClass = reset($aColumns);
		$oAttDef = MetaModel::GetAttributeDef('CMDBChange', 'userinfo');
		$aColumns = array_keys($oAttDef->GetSQLColumns());
		$sChangeUserInfo = reset($aColumns);
		$sKey = "$sObjKey";
		$sOrigFriendlyname = CMDBSource::Quote($sOrigFriendlyname);

		$sSQL = <<<SQL
SELECT DISTINCT `CMDBChangeOp`.`$sObjKey`
    FROM `$sChangeOpTable` AS `CMDBChangeOp`
    INNER JOIN `$sChangeTable` AS `CMDBChange` ON `CMDBChangeOp`.`$sChangeOpChangeId` = `CMDBChange`.`$sChangeKey`
	WHERE `CMDBChangeOp`.`$sChangeOpFinalClass` = 'CMDBChangeOpSetAttributeCaseLog'
	  AND `CMDBChangeOp`.`$sObjClass` = '$sClass'
	  AND `CMDBChange`.`$sChangeUserInfo`  = $sOrigFriendlyname
	  AND `CMDBChangeOp`.`$sChangeOpId` >= $iChangeOpId
SQL;

		return $sSQL;
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
				} elseif ($oAttDef instanceof AttributeString && !($oAttDef instanceof AttributeFinalClass)) {
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
		if ($iChunkSize <= 1) {
			AnonymizerLog::Debug('Stop retry action ActionCleanupCaseLogs with params '.json_encode($aParams));
			$this->Set('action_params', '');
			$this->DBWrite();

			return false;
		}
		$aParams['iChunkSize'] = (int)$iChunkSize / 2;

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
			// reset chunk size for every request
			$aParams['iChunkSize'] = MetaModel::GetConfig()->GetModuleSetting(AnonymizerHelper::MODULE_NAME, 'init_chunk_size', 1000);
			$iProgress = $aParams['aChangesProgress'][$sName] ?? 0;
			$bCompleted = ($iProgress == -1);
			AnonymizerLog::Debug("=> Request: $sName Progress: $iProgress");
			while (!$bCompleted && time() < $iEndExecutionTime) {
				try {
					$fStart = microtime(true);
					$bCompleted = $oDatabaseService->ExecuteQueriesByChunk($aRequest, $iProgress, $aParams['iChunkSize']);
					$fDuration = microtime(true) - $fStart;
					if ($fDuration < AnonymizerHelper::ADAPTATIVE_MIN_TIME) {
						$aParams['iChunkSize'] *= 2;
						if ($aParams['iChunkSize'] > AnonymizerHelper::ADAPTATIVE_MAX_CHUNK_SIZE) {
							$aParams['iChunkSize'] = AnonymizerHelper::ADAPTATIVE_MAX_CHUNK_SIZE;
						}
					} elseif ($fDuration > AnonymizerHelper::ADAPTATIVE_MAX_TIME && $aParams['iChunkSize'] > 1) {
						$aParams['iChunkSize'] /= 2;
					}
					$aParams['aChangesProgress'][$sName] = $iProgress;
				}
				catch (MySQLHasGoneAwayException $e) {
					//in this case retry is possible
					AnonymizerLog::Error('Error MySQLHasGoneAwayException during ActionCleanupCaseLogs try again later');

					// No way to continue, wait for another cron round
					return false;
				}
				catch (Exception $e) {
					AnonymizerLog::Error('Error during ActionCleanupCaseLogs with message :'.$e->getMessage());
					$aParams['aChangesProgress'][$sName] = -1;
					$bCompleted = true;
				}
				// Save progression
				$this->Set('action_params', json_encode($aParams));
				$this->DBWrite();
			}
			if (!$bCompleted) {
				// Timeout
				AnonymizerLog::Debug("Timeout Request: $sName with progression: $iProgress");

				return false;
			}
		}

		return true;
	}
}