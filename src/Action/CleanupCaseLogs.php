<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\Anonymizer\Action;

use Combodo\iTop\Anonymizer\Helper\AnonymizerHelper;
use Combodo\iTop\Anonymizer\Service\CleanupService;

class CleanupCaseLogs extends AbstractAnonymizationAction
{
	const USER_CLASS = 'User';

	public function Init()
	{
		$aParams['iChunkSize'] = MetaModel::GetConfig()->GetModuleParameter(AnonymizerHelper::MODULE_NAME, 'max_chunk_size', 1000);
		$aCleanupCaseLog = (array)MetaModel::GetConfig()->GetModuleParameter(AnonymizerHelper::MODULE_NAME, 'caselog_content');

		$aListOfAction = [];

		$sId = $this->oTask->Get('id_to_anonymize');
		$oSearch = new DBObjectSearch(self::USER_CLASS);
		$oSearch->AddCondition('contactid', $sId);
		$oSearch->AllowAllData();
		$oSet = new DBObjectSet($oSearch);
		$oSet->OptimizeColumnLoad(array(self::USER_CLASS => array('finalclass')));
		$aIdWithClass = $oSet->GetColumnAsArray('finalclass');
		$aIdUser = array_keys($aIdWithClass);

		$sClass = $this->oTask->Get('class_to_anonymize');
		$sId = $this->oTask->Get('id_to_anonymize');

		$oObject = \MetaModel::GetObject($sClass, $sId);
		$sFriendlynameToAnonymize = $oObject->GetName();
		$sEmailToAnonymize = $oObject->Get('email');

		$sFriendlynameAnonymized = $oObject->GetName();
		$sEmailAnonymized = $oObject->Get('email');
		// 1) Build the expression to search (and replace)
		$sPattern = ' : %1$s (%2$d) ============';

		//foreach ($sFriendlynameToAnonymize as $sFriendlyName) {

		$sReplaceInIdx = str_repeat('*', strlen($sFriendlynameToAnonymize));
		$sStartReplaceInIdx = "REPLACE(";
		$sEndReplaceInIdx = ", ".CMDBSource::Quote($sFriendlynameToAnonymize).", ".CMDBSource::Quote($sReplaceInIdx).")";

		if (in_array('friendlyname', $aCleanupCaseLog)) {
			$sReplace = str_repeat('*', strlen($sFriendlynameToAnonymize));;
			$sStartReplace = "REPLACE(";
			$sEndReplaceInCaseLog = ", ".CMDBSource::Quote($sFriendlynameToAnonymize).", ".CMDBSource::Quote($sReplace).")";
			$sEndReplaceInTxt = ", ".CMDBSource::Quote($sFriendlynameToAnonymize).", ".CMDBSource::Quote($sFriendlynameAnonymized).")";
		} else {
			$sStartReplace = '';
			$sEndReplaceInCaseLog = '';
			$sEndReplaceInTxt = "";
			foreach ($aIdUser as $sIdUser) {
				$sSearch = sprintf($sPattern, $sFriendlynameToAnonymize, $sIdUser);
				$sReplace = sprintf($sPattern, str_repeat('*', strlen($sFriendlynameToAnonymize)), $sIdUser);

				$sStartReplace = "REPLACE(".$sStartReplace;
				$sEndReplaceInCaseLog = $sEndReplaceInCaseLog.", ".CMDBSource::Quote($sSearch).", ".CMDBSource::Quote($sReplace).")";
				$sEndReplaceInTxt = ", ".CMDBSource::Quote($sFriendlynameToAnonymize).", ".CMDBSource::Quote($sFriendlynameAnonymized).")";
			}
		}
		//}

		if (in_array('email', $aCleanupCaseLog)) {
			foreach ($sEmailToAnonymize as $sEmail) {
				$sReplace = str_repeat('*', strlen($sEmail));

				$sStartReplace = "REPLACE(".$sStartReplace;
				$sEndReplaceInCaseLog = $sEndReplaceInCaseLog.", ".CMDBSource::Quote($sEmail).", ".CMDBSource::Quote($sReplace).")";
				$sEndReplaceInTxt = $sEndReplaceInTxt.", ".CMDBSource::Quote($sEmail).", ".CMDBSource::Quote($sEmailAnonymized).")";
			}
		}

		// 2) Find all classes containing case logs
		foreach (MetaModel::GetClasses() as $sClass) {
			foreach (MetaModel::ListAttributeDefs($sClass) as $sAttCode => $oAttDef) {
				$sTable = MetaModel::DBGetTable($sClass);
				$sKey = MetaModel::DBGetKey($sClass);
				if ((MetaModel::GetAttributeOrigin($sClass, $sAttCode) == $sClass) && $oAttDef instanceof AttributeCaseLog) {
					$aSQLColumns = $oAttDef->GetSQLColumns();
					$sColumn1 = array_keys($aSQLColumns)[0]; // We assume that the first column is the text
					$sColumnIdx = array_keys($aSQLColumns)[1]; // We assume that the second column is the index

					$aConditions = [];
					//foreach ($aDataToAnonymize['friendlyname'] as $sFriendlyName) {
					foreach ($aIdUser as $sIdUser) {
						$aConditions[] = " `$sColumn1` LIKE ".CMDBSource::Quote('%'.sprintf($sPattern, $sFriendlynameToAnonymize, $sIdUser).'%');
					}
					//}
					$sCondition = implode(' OR ', $aConditions);
					$sSqlSearch = "SELECT  `$sKey` FROM `$sTable` WHERE $sCondition";

					$aColumnsToUpdate = [];
					$aClasses = array_merge([$sClass], MetaModel::GetSubclasses($sClass));
					foreach ($aClasses as $sClass) {
						foreach (MetaModel::ListAttributeDefs($sClass) as $sAttCode => $oAttDef) {
							$sTable = MetaModel::DBGetTable($sClass, $sAttCode);
							if ($oAttDef instanceof AttributeCaseLog) {
								$aSQLColumns = $oAttDef->GetSQLColumns();
								$sColumn1 = array_keys($aSQLColumns)[0]; // We assume that the first column is the text
								$sColumnIdx = array_keys($aSQLColumns)[1]; // We assume that the second column is the index
								$aColumnsToUpdate[$sTable][] = " `$sColumn1` = ".$sStartReplace."`$sColumn1`".$sEndReplaceInCaseLog;
								$aColumnsToUpdate[$sTable][] = " `$sColumnIdx` = ".$sStartReplaceInIdx."`$sColumnIdx`".$sEndReplaceInIdx." ";
							} elseif ($oAttDef instanceof AttributeText) {
								$aSQLColumns = $oAttDef->GetSQLColumns();
								$sColumn = array_keys($aSQLColumns)[0]; //
								$aColumnsToUpdate[$sTable][] = " `$sColumn` = ".$sStartReplace."`$sColumn`".$sEndReplaceInTxt;
							}
						}
					}
					$aSqlUpdate = [];
					foreach ($aColumnsToUpdate as $sTable => $aRequestReplace) {
						$sSqlUpdate = "UPDATE `$sTable` ".
							"SET ".implode(' , ', $aRequestReplace);
						$aSqlUpdate[] = $sSqlUpdate;
					}
					$aAction = [];
					$aAction['current_id'] = 0;
					$aAction['sql_search'] = $sSqlSearch;
					$aAction['sql_updates'] = $aSqlUpdate;
					$aAction['key'] = $sKey;
					$aListOfAction[] = $aAction;
				}
			}
		}
		$aParams['actions'] = $aListOfAction;
		$this->oTask->Set('action_params', json_encode($aParams));
		$this->oTask->DBWrite();
	}


	public function Retry()
	{
		$aParams = json_decode($this->oTask->Get('action_params'), true);
		$aParams['iChunkSize'] /= 2 + 1;

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

		return ExecuteQueries($this->oTask);
	}

	public function ExecuteQueries($oTask)
	{
		$sClass = $this->oTask->Get('class_to_anonymize');
		$sId = $this->oTask->Get('id_to_anonymize');

		$oService = new CleanupService($sClass, $sId, $this->iEndExecutionTime);
		$aParams = json_decode($oTask->Get('action_params'), true);
		$aListOfActions = $aParams['actions'];
		// Progress until the current user
		$aAction = $aListOfActions[0];

		while ($aAction['sId'] > 0) {
			//executeQuery
			$sId = $oService->ExecuteActionWithQueriesByChunk($aAction['sql_search'], $aAction['sql_updates'], $aAction['key'], $aAction['current_id'], $aParams['max_chunk_size']);
			//action is completed
			if ($sId == -1) {
				array_shift($aListOfActions);
				if (count($aListOfActions) == 0) {
					return;
				}
				$aAction = $aListOfActions[0];
			} else {
				$aAction['current_id'] = $sId;
			}
			// Save progression
			$aListOfActions[0] = $aAction;
			$aParams['actions'] = $aListOfActions;
			$oTask->Set('action_params', json_encode($aParams));
			$oTask->DBWrite();
		}

		return true;
	}
}