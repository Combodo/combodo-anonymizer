<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */


use Combodo\iTop\Anonymizer\Helper\AnonymizerHelper;
use Combodo\iTop\Anonymizer\Helper\AnonymizerLog;
use Combodo\iTop\BackgroundTaskEx\Service\DatabaseService;


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
			'db_table'            => 'priv_anonym_action_cleanup_on_mention',
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
	public function InitActionParams(): bool
	{
		$oTask = $this->GetTask();
		$oDatabaseService = new DatabaseService();

		$aParams['iChunkSize'] = MetaModel::GetConfig()->GetModuleSetting(AnonymizerHelper::MODULE_NAME, 'max_chunk_size', 1000);
		$sCleanupOnMention = MetaModel::GetConfig()->GetModuleSetting(AnonymizerHelper::MODULE_NAME, 'on_mention');
		$aCleanupCaseLog = (array)MetaModel::GetConfig()->GetModuleSetting(AnonymizerHelper::MODULE_NAME, 'caselog_content');

		//mention exists only since iTop 3.0
		if (!MetaModel::GetConfig()->IsProperty('mentions.allowed_classes')) {
			//nothing to do. We can skip the current action
			AnonymizerLog::Debug("Config mentions.allowed_classes is empty");

			return false;
		}
		$aMentionsAllowedClasses = (array)MetaModel::GetConfig()->Get('mentions.allowed_classes') ?? [];
		foreach ($aMentionsAllowedClasses as $sChar => $sClass) {
			if ($sClass != 'Person') {
				unset($aMentionsAllowedClasses[$sChar]);
			}
		}
		if (count($aMentionsAllowedClasses) == 0) {
			//nothing to do. We can skip the current action
			AnonymizerLog::Debug('Config mentions.allowed_classes does not contains Person');

			return false;
		}

		$aContext = json_decode($oTask->Get('anonymization_context'), true);
		$sOrigFriendlyname = $aContext['origin']['friendlyname'];
		$sTargetFriendlyname = $aContext['anonymized']['friendlyname'];

		if ($sOrigFriendlyname === '' ) {
			//nothing to do. We can skip the current action
			AnonymizerLog::Debug('Friendlyname is empty');
			return false;
		}

		$sOrigEmail = $aContext['origin']['email'];
		$sTargetEmail = $aContext['anonymized']['email'];

		$aMentionSearches = [];
		foreach ($aMentionsAllowedClasses as $sMentionClass) {
			$aMentionSearches[] = 'class='.$sMentionClass.'&amp;id='.$oTask->Get('person_id')."\">@";
		}

		$aRequests = [];

		if ($sCleanupOnMention == 'trigger-only') {
			$oScopeQuery = "SELECT TriggerOnObjectMention";
			$oSet = new DBObjectSet(DBSearch::FromOQL($oScopeQuery));
			while ($oTrigger = $oSet->Fetch()) {
				$sTargetClass = $oTrigger->Get('target_class');

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

				$aClasses = MetaModel::EnumChildClasses($sTargetClass, ENUM_CHILD_CLASSES_ALL);
				foreach ($aClasses as $sClass) {
					$sTable = MetaModel::DBGetTable($sClass);
					$sKey = MetaModel::DBGetKey($sClass);
					$aConditions = [];

					foreach (MetaModel::ListAttributeDefs($sClass) as $sAttCode => $oAttDef) {
						if (MetaModel::IsAttributeOrigin($sClass, $sAttCode)) {
							if ($oAttDef instanceof AttributeCaseLog) {
								$aSQLColumns = $oAttDef->GetSQLColumns();
								$sColumn = array_keys($aSQLColumns)[0]; // We assume that the first column is the text
								foreach ($aMentionSearches as $sMentionSearch) {
									$aConditions[] = "`$sColumn` LIKE ".CMDBSource::Quote('%'.$sMentionSearch.'%');
								}
							}
						}
					}

					if (count($aConditions) > 0) {
						$sSqlSearch = "SELECT `$sKey` from `$sTable` WHERE ".implode(' OR ', $aConditions);

						$aColumnsToUpdate = $this->GetColumnsToUpdate($sClass, $sStartReplace, $sEndReplaceInCaseLog, $sEndReplaceInTxt);

						$aSqlUpdate = [];
						foreach ($aColumnsToUpdate as $sTable => $aRequestReplace) {
							$sSqlUpdate = "UPDATE `$sTable` /*JOIN*/ ".
								'SET '.implode(' , ', $aRequestReplace);
							$aSqlUpdate[$sTable] = $sSqlUpdate;
						}

						$aAction = [];
						$aAction['class'] = $sClass;
						$aAction['search_query'] = $sSqlSearch;
						$aAction['search_max_id'] = $oDatabaseService->QueryMaxKey($sKey, $sTable);
						$aAction['apply_queries'] = $aSqlUpdate;
						$aAction['key'] = $sKey;
						$aAction['search_key'] = $sKey;
						$aRequests[$sClass] = $aAction;
					}
				}
			}
			//} elseif ($sCleanupOnMention == 'all') {
			//TODO maybe in the futur
		}

		$aParams['aRequests'] = $aRequests;
		$this->Set('action_params', json_encode($aParams));
		$this->DBWrite();

		return true;
	}

	private function GetColumnsToUpdate($sTargetClass, $sStartReplace, $sEndReplaceInCaseLog, $sEndReplaceInTxt)
	{
		$aColumnsToUpdate = [];
		$aClasses = MetaModel::EnumChildClasses($sTargetClass, ENUM_CHILD_CLASSES_ALL);
		foreach ($aClasses as $sClass) {
			foreach (MetaModel::ListAttributeDefs($sClass) as $sAttCode => $oAttDef) {
				$sTable = MetaModel::DBGetTable($sClass, $sAttCode);
				if ($oAttDef instanceof AttributeCaseLog) {
					$aSQLColumns = $oAttDef->GetSQLColumns();
					$sColumn = array_keys($aSQLColumns)[0]; // We assume that the first column is the text
					$aColumnsToUpdate[$sTable][$sColumn] = " `$sColumn` = ".$sStartReplace."`$sColumn`".$sEndReplaceInCaseLog;
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
		if ($iChunkSize <= 1) {
			AnonymizerLog::Debug('Stop retry action ActionCleanupOnMention with params '.json_encode($aParams));
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