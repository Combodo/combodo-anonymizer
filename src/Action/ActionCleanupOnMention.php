<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */


use Combodo\iTop\Anonymizer\Helper\AnonymizerHelper;
use Combodo\iTop\Anonymizer\Helper\AnonymizerLog;
use Combodo\iTop\ComplexBackgroundTask\Service\DatabaseService;


/**
 * search anonymized person in objects with trigger on "onMention" .
 * anonymize friendly name and email in all text fields of these objects
 */
class ActionCleanupOnMention extends AnonymizationTaskAction
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
			'db_table'            => 'priv_anonymization_task_action_cleanup_on_mention',
			'db_key_field'        => 'id',
			'db_finalclass_field' => '',
			'display_template'    => '',
		);
		MetaModel::Init_Params($aParams);
		MetaModel::Init_InheritAttributes();

		// Display lists
		MetaModel::Init_SetZListItems('details', array('name', 'rank')); // Attributes to be displayed for the complete details
		MetaModel::Init_SetZListItems('list', array('name', 'rank')); // Attributes to be displayed for a list
		// Search criteria
		MetaModel::Init_SetZListItems('standard_search', array('name')); // Criteria of the std search form
	}

	/**
	 * build queries search and update to run later in Execute function
	 * search all objects with anonymized person notification. The search is executed on classes with a trigger on "onMention"
	 * replace data of anonymized person in all text and string attributes of found objects
	 *
	 * @return void
	 * @throws \ArchivedObjectException
	 * @throws \CoreCannotSaveObjectException
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 * @throws \MySQLException
	 */
	public function InitActionParams()
	{
		$oTask = $this->GetTask();

		$aParams['iChunkSize'] = MetaModel::GetConfig()->GetModuleParameter(AnonymizerHelper::MODULE_NAME, 'max_chunk_size', 1000);
		$sCleanupOnMention = MetaModel::GetConfig()->GetModuleParameter(AnonymizerHelper::MODULE_NAME, 'on_mention');
		$aCleanupCaseLog = (array)MetaModel::GetConfig()->GetModuleParameter(AnonymizerHelper::MODULE_NAME, 'caselog_content');

		//mention exists only since iTop 3.0
		if (!MetaModel::GetConfig()->IsProperty('mentions.allowed_classes')) {
			//nothing to do. We can skip the current action
			$this->Set('action_params', '');
			$this->DBWrite();

			return;
		}
		$aMentionsAllowedClasses = (array)MetaModel::GetConfig()->Get('mentions.allowed_classes');
		if (sizeof($aMentionsAllowedClasses) == 0) {
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

		$aRequests = [];

		if ($sCleanupOnMention == 'trigger-only') {
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
				if ($sOrigEmail != '' && in_array('email', $aCleanupCaseLog)) {
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
									$sColumn = array_keys($aSQLColumns)[0]; // We assume that the first column is the text
									//don't change number of characters
									foreach ($aMentionsAllowedClasses as $sMentionChar => $sMentionClass) {
										if (MetaModel::IsParentClass('Contact', $sMentionClass)) {
											$sSearch = "class=".$sMentionClass." & amp;id = ".$this->Get('id_to_anonymize')."\">@";
											$sSqlSearch = "SELECT `$sKey` from `$sTable` WHERE `$sColumn` LIKE ".CMDBSource::Quote('%'.$sSearch.'%');

											$aColumnsToUpdate = [];
											$aClasses = array_merge([$sClass], MetaModel::GetSubclasses($sClass));
											foreach ($aClasses as $sClass) {
												foreach (MetaModel::ListAttributeDefs($sClass) as $sAttCode => $oAttDef) {
													$sTable = MetaModel::DBGetTable($sClass, $sAttCode);
													if ($oAttDef instanceof AttributeCaseLog) {
														$aSQLColumns = $oAttDef->GetSQLColumns();
														$sColumn = array_keys($aSQLColumns)[0]; // We assume that the first column is the text
														$aColumnsToUpdate[$sTable][$sColumn] = " `$sColumn` = ".$sStartReplace."`$sColumn`".$sEndReplaceInCaseLog;
													} elseif ($oAttDef instanceof AttributeText || ($oAttDef instanceof AttributeString && !($oAttDef instanceof AttributeFinalClass))) {
														$aSQLColumns = $oAttDef->GetSQLColumns();
														$sColumn = array_keys($aSQLColumns)[0]; //
														$aColumnsToUpdate[$sTable][$sColumn] = " `$sColumn` = ".$sStartReplace."`$sColumn`".$sEndReplaceInTxt;
													}
												}
											}
											$aSqlUpdate = [];
											foreach ($aColumnsToUpdate as $sTable => $aRequestReplace) {
												$sSqlUpdate = "UPDATE `$sTable` /*JOIN*/ ".
													"SET ".implode(' , ', $aRequestReplace);
												$aSqlUpdate[$sTable] = $sSqlUpdate;
											}

											$aAction = [];
											$aAction['search_query'] = $sSqlSearch;
											$aAction['apply_queries'] = $aSqlUpdate;
											$aAction['key'] = $sKey;
											$aAction['search_key'] = $sKey;
											$aRequests[] = $aAction;
										}
									}
								}
							}
						}
					}

				}
			}
			//} elseif ($sCleanupOnMention == 'all') {
			//TODO maybe in the futur
		}

		$aParams['aRequests'] = $aRequests;
		$this->Set('action_params', json_encode($aParams));
		$this->DBWrite();
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
			AnonymizerLog::Debug('Stop retry action ActionCleanupOnMention with params '.json_encode($aParams));
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
					$bCompleted = true;
				}
				// Save progression
				$this->Set('action_params', json_encode($aParams));
				$this->DBWrite();
			}
			if (!$bCompleted) {
				// Timeout
				return false;
			}
		}

		return true;
	}
}