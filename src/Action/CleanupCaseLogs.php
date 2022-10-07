<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\Anonymizer\Action;

use AttributeCaseLog;
use AttributeText;
use CMDBSource;
use Combodo\iTop\Anonymizer\Helper\AnonymizerHelper;
use Combodo\iTop\Anonymizer\Helper\AnonymizerLog;
use Combodo\iTop\Anonymizer\Service\CleanupService;
use DBObjectSearch;
use DBObjectSet;
use Exception;
use MetaModel;
use MySQLHasGoneAwayException;

class CleanupCaseLogs extends AbstractAnonymizationAction
{
	const USER_CLASS = 'User';

	public function Init()
	{
		$aParams['iChunkSize'] = MetaModel::GetConfig()->GetModuleParameter(AnonymizerHelper::MODULE_NAME, 'max_chunk_size', 1000);
		$aCleanupCaseLog = (array)MetaModel::GetConfig()->GetModuleParameter(AnonymizerHelper::MODULE_NAME, 'caselog_content');

		$aRequests = [];

		$aContext = json_decode($this->oTask->Get('anonymization_context'), true);
		$sId = $this->oTask->Get('id_to_anonymize');

		$oSearch = new DBObjectSearch(self::USER_CLASS);
		$oSearch->AddCondition('contactid', $sId);
		$oSearch->AllowAllData();
		$oSet = new DBObjectSet($oSearch);
		$oSet->OptimizeColumnLoad(array(self::USER_CLASS => array('finalclass')));
		$aIdWithClass = $oSet->GetColumnAsArray('finalclass');
		$aIdUser = array_keys($aIdWithClass);

		$sOrigFriendlyname = $aContext['origin']['friendlyname'];
		$sTargetFriendlyname = $aContext['anonymized']['friendlyname'];

		$sOrigEmail = $aContext['origin']['email'];
		$sTargetEmail = $aContext['anonymized']['email'];
		// 1) Build the expression to search (and replace)
		$sPattern = ' : %1$s (%2$d) ============';

		//foreach ($sFriendlynameToAnonymize as $sFriendlyName) {

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
		//}

		if (in_array('email', $aCleanupCaseLog)) {
			foreach ($sOrigEmail as $sEmail) {
				$sReplace = str_repeat('*', strlen($sEmail));

				$sStartReplace = "REPLACE(".$sStartReplace;
				$sEndReplaceInCaseLog = $sEndReplaceInCaseLog.", ".CMDBSource::Quote($sEmail).", ".CMDBSource::Quote($sReplace).")";
				$sEndReplaceInTxt = $sEndReplaceInTxt.", ".CMDBSource::Quote($sEmail).", ".CMDBSource::Quote($sTargetEmail).")";
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
						$aConditions[] = " `$sColumn1` LIKE ".CMDBSource::Quote('%'.sprintf($sPattern, $sOrigFriendlyname, $sIdUser).'%');
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
					$aAction['select'] = $sSqlSearch;
					$aAction['updates'] = $aSqlUpdate;
					$aAction['key'] = $sKey;
					$aRequests[] = $aAction;
				}
			}
		}
		$aParams['aRequests'] = $aRequests;
		$this->oTask->Set('action_params', json_encode($aParams));
		$this->oTask->DBWrite();
	}


	public function Retry()
	{
		$aParams = json_decode($this->oTask->Get('action_params'), true);
		$iChunkSize = $aParams['iChunkSize'];
		if ($iChunkSize == 1) {
			AnonymizerLog::Debug('Stop retry action CleanupCaseLogs with params '.json_encode($aParams));
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
		return $this->ExecuteQueries($this->oTask);
	}

	public function ExecuteQueries($oTask)
	{
		$sClass = $this->oTask->Get('class_to_anonymize');
		$sId = $this->oTask->Get('id_to_anonymize');

		$oService = new CleanupService($sClass, $sId, $this->iEndExecutionTime);
		$aParams = json_decode($oTask->Get('action_params'), true);
		$aRequests = $aParams['aRequests'];

		foreach ($aRequests as $sName => $aRequest) {
			$iProgress = $aParams['aChangesProgress'][$sName] ?? 0;
			$bCompleted = ($iProgress == -1);
			while (!$bCompleted && time() < $this->iEndExecutionTime) {
				try {
					$bCompleted = $oService->ExecuteActionWithQueriesByChunk($aRequest['select'], $aRequest['updates'], $aRequest['key'], $iProgress, $aParams['iChunkSize']);
					$aParams['aChangesProgress'][$sName] = $iProgress;
				}
				catch (MySQLHasGoneAwayException $e) {
					//in this case retry is possible
					AnonymizerLog::Error('Error MySQLHasGoneAwayException during CleanupCaseLogs try again later');

					return false;
				}
				catch (Exception $e) {
					AnonymizerLog::Error('Error during CleanupCaseLogs with params '.$this->oTask->Get('action_params').' with message :'.$e->getMessage());
					AnonymizerLog::Error('Go to next update');
					$aParams['aChangesProgress'][$sName] = -1;
				}
				// Save progression
				$this->oTask->Set('action_params', json_encode($aParams));
				$this->oTask->DBWrite();
			}
			if (!$bCompleted) {
				// Timeout
				AnonymizerLog::Debug('timeout');

				return false;
			}
		}
		AnonymizerLog::Debug('return true');

		return true;
	}
}