<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\Anonymizer\Action;

use AttributeCaseLog;
use CMDBSource;
use Combodo\iTop\Anonymizer\Helper\AnonymizerHelper;
use Combodo\iTop\Anonymizer\Helper\AnonymizerLog;
use Combodo\iTop\Anonymizer\Service\CleanupService;
use DBObjectSet;
use MetaModel;
use MySQLHasGoneAwayException;

class CleanupOnMention extends AbstractAnonymizationAction
{
	const USER_CLASS = 'User';

	public function Init()
	{
		$aParams['iChunkSize'] = MetaModel::GetConfig()->GetModuleParameter(AnonymizerHelper::MODULE_NAME, 'max_chunk_size', 1000);
		$sCleanupOnmention = MetaModel::GetConfig()->GetModuleParameter(AnonymizerHelper::MODULE_NAME, 'onmention');
		$aCleanupCaseLog = (array)MetaModel::GetConfig()->GetModuleParameter(AnonymizerHelper::MODULE_NAME, 'caselog_content');

		$aMentionsAllowedClasses = MetaModel::GetConfig()->Get('mentions.allowed_classes');
		if (sizeof($aMentionsAllowedClasses) == 0) {
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

		$aRequests = [];

		if ($sCleanupOnmention == 'trigger-only') {
			$oScopeQuery = "SELECT TriggerOnObjectMention";
			$oSet = new DBObjectSet(DBSearch::FromOQL($oScopeQuery));
			while ($oTrigger = $oSet->Fetch()) {
				$sParentClass = $oTrigger->Get('target_class');

				$sEndReplaceInCaseLog = "";
				$sEndReplaceInTxt = "";
				$sReplace = str_repeat('*', strlen($sOrigFriendlyname));

				$sStartReplace = "REPLACE(";
				$sEndReplaceInCaseLog = $sEndReplaceInCaseLog.", ".CMDBSource::Quote($sOrigFriendlyname).", ".CMDBSource::Quote($sReplace).")";
				$sEndReplaceInTxt = $sEndReplaceInTxt.", ".CMDBSource::Quote($sOrigFriendlyname).", ".CMDBSource::Quote($sTargetFriendlyname).")";
				if ($sOrigEmail!='' && in_array('email', $aCleanupCaseLog)) {
					$sReplace = str_repeat('*', strlen($sOrigEmail));

					$sStartReplace = "REPLACE(".$sStartReplace;
					$sEndReplaceInCaseLog = $sEndReplaceInCaseLog.", ".CMDBSource::Quote($sOrigEmail).", ".CMDBSource::Quote($sReplace).")";
					$sEndReplaceInTxt = $sEndReplaceInTxt.", ".CMDBSource::Quote($sOrigEmail).", ".CMDBSource::Quote($sTargetEmail).")";
				}

				$aClasses = array_merge([$sParentClass], MetaModel::GetSubclasses($sParentClass));
				$aAlreadyDone = [];
				foreach ($aClasses as $sClass) {
					foreach (MetaModel::ListAttributeDefs($sClass) as $sAttCode => $oAttDef) {
						$sTable = MetaModel::DBGetTable($sClass, $sAttCode);
						$sKey = MetaModel::DBGetKey($sClass);
						if (!in_array($sTable.'->'.$sAttCode, $aAlreadyDone)) {
							$aAlreadyDone[] = $sTable.'->'.$sAttCode;
							if ((MetaModel::GetAttributeOrigin($sClass, $sAttCode) == $sClass)) {
								if ($oAttDef instanceof AttributeCaseLog) {
									$aSQLColumns = $oAttDef->GetSQLColumns();
									$sColumn1 = array_keys($aSQLColumns)[0]; // We assume that the first column is the text
									//don't change number of characters
									foreach ($aMentionsAllowedClasses as $sMentionChar => $sMentionClass) {
										if (MetaModel::IsParentClass('Contact', $sMentionClass)) {
											$sSearch = "class=".$sMentionClass." & amp;id = ".$this->Get('id_to_anonymize')."\">@";
											$sSqlSearch = "SELECT `$sKey` from `$sTable` WHERE `$sColumn1` LIKE ".CMDBSource::Quote('%'.$sSearch.'%');

											$aColumnsToUpdate = [];
											$aClasses = array_merge([$sClass], MetaModel::GetSubclasses($sClass));
											foreach ($aClasses as $sClass) {
												foreach (MetaModel::ListAttributeDefs($sClass) as $sAttCode => $oAttDef) {
													$sTable = MetaModel::DBGetTable($sClass, $sAttCode);
													if ($oAttDef instanceof AttributeCaseLog) {
														$aSQLColumns = $oAttDef->GetSQLColumns();
														$sColumn1 = array_keys($aSQLColumns)[0]; // We assume that the first column is the text
														$aColumnsToUpdate[$sTable][] = " `$sColumn1` = ".$sStartReplace."`$sColumn1`".$sEndReplaceInCaseLog;
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
							}
						}
					}

				}
			}
		} elseif ($sCleanupOnmention == 'all') {
			//TODO maybe in the futur
		}

		$aParams['aRequests'] = $aRequests;
		$this->oTask->Set('action_params', json_encode($aParams));
		$this->oTask->DBWrite();
	}


	public function Retry()
	{
		$aParams = json_decode($this->oTask->Get('action_params'), true);
		$iChunkSize = $aParams['iChunkSize'];
		if($iChunkSize == 1){
			AnonymizerLog::Debug('Stop retry action CleanupOnMention with params '.json_encode($aParams));
			$this->oTask->Set('action_params', '');
			$this->oTask->DBWrite();
		}
		$aParams['iChunkSize'] = (int) $iChunkSize/2 + 1;

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
		if ($oTask->Get('action_params') == '') {
			return true;
		}
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
				} catch (MySQLHasGoneAwayException $e){
					//in this case retry is possible
					AnonymizerLog::Error('Error MySQLHasGoneAwayException during CleanupCaseLogs try again later');
					return false;
				} catch (\Exception $e){
					AnonymizerLog::Error('Error during CleanupCaseLogs with params '.$this->oTask->Get('action_params').' with message :'.$e->getMessage());
					AnonymizerLog::Error('Go to next update');
					$aParams['aChangesProgress'][$sName]= -1;
				}
				// Save progression
				$this->oTask->Set('action_params', json_encode($aParams));
				$this->oTask->DBWrite();
			}
			if (!$bCompleted) {
				// Timeout
				return false;
			}
		}

		return true;
	}
}