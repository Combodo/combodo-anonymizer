<?php
/**
 * Copyright (C) 2013-2020 Combodo SARL
 *
 * This file is part of iTop.
 *
 * iTop is free software; you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * iTop is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 */


abstract class _BatchAnonymization extends DBObject
{

	/**
	 * @param $iTimeLimit
	 *
	 * @return bool|void
	 * @throws \ArchivedObjectException
	 * @throws \CoreException
	 */
	public function ExecuteStep($iTimeLimit)
	{
		switch ($this->Get('function')) {
			case 'PurgeHistoryByBatch':
				return $this->PurgeHistoryByBatch($iTimeLimit);
				break;
			case 'CleanupCaseLogsByBatch':
				return $this->CleanupCaseLogsByBatch($iTimeLimit);
				break;
			case 'CleanupOnMentionByBatch':
				return $this->CleanupOnMentionByBatch($iTimeLimit);
				break;
			case 'CleanupEmailByBatch':
				return $this->CleanupEmailByBatch($iTimeLimit);
				break;
			default:
				echo '!!!! ERROR FUNCTION '.$this->Get('function').' NOT FOUND. Please check the code !';

				return true;
		}
	}

	/**
	 * @param $sSqlSearch
	 * @param $aSqlUpdate array to update elements found by $sSqlSearch, don't specify the where close
	 * @param $sKey primary key of updated table
	 * @param $iTimeLimit limit as evaluated by time()
	 * Search objects to update and execute update by lot of  max_chunk_size elements
	 * return true if all objects where updated, false if the function don't have the time to finish
	 *
	 * @return bool
	 * @throws \CoreException
	 * @throws \MySQLException
	 * @throws \MySQLHasGoneAwayException
	 */
	protected static function ExecuteQueryByLot($sSqlSearch, $aSqlUpdate, $sKey, $iTimeLimit)
	{
		$iMaxChunkSize = MetaModel::GetConfig()->GetModuleSetting('combodo-anonymizer', 'max_chunk_size', 1000);
		$aObjects = [];
		$bExecuteQuery = true;
		while ($bExecuteQuery) {
			$oResult = CMDBSource::Query($sSqlSearch." LIMIT ".$iMaxChunkSize);
			//echo("\n\n Search anonymization: ".$sSqlSearch);
			//foreach ($aSqlUpdate as $sSqlUpdate) {
			//	echo("\n Update: ".$sSqlUpdate);
			//}
			$aObjects = [];
			if ($oResult->num_rows > 0) {
				while ($oRaw = $oResult->fetch_assoc()) {
					$aObjects[] = $oRaw[$sKey];
				}
				foreach ($aSqlUpdate as $sSqlUpdate) {
					$sSQL = $sSqlUpdate." WHERE `$sKey` IN (".implode(', ', $aObjects).");";
				//echo("\n AnonymizationUpdate: ".$sSQL);
					CMDBSource::Query($sSQL);
				}
			}
			if (count($aObjects) < $iMaxChunkSize || (time() >= $iTimeLimit)) {
				$bExecuteQuery = false;
			}
		}

		return (count($aObjects) < $iMaxChunkSize);
	}

	/**
	 * @param $iTimeLimit
	 *
	 * @throws \ArchivedObjectException
	 * @throws \CoreCannotSaveObjectException
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 * @throws \CoreWarning
	 * @throws \DeleteException
	 * @throws \MissingQueryArgument
	 * @throws \MySQLException
	 * @throws \MySQLHasGoneAwayException
	 * @throws \OQLException
	 */
	protected function PurgeHistoryByBatch($iTimeLimit)
	{
		$oPerson = MetaModel::GetObject('Person', $this->Get('id_to_anonymize'), true);
		// Cleanup all non mandatory values //end of job
		foreach (MetaModel::ListAttributeDefs('Person') as $sAttCode => $oAttDef) {
			if (!$oAttDef->IsWritable()) {
				continue;
			}

			if ($oAttDef instanceof AttributeLinkedSetIndirect) {
				$oValue = DBObjectSet::FromScratch($oAttDef->GetLinkedClass());
				$oPerson->Set($sAttCode, $oValue);
			}
		}
		$oPerson->DBWrite();

		// Delete any existing change tracking about the current object
		$oFilter = new DBObjectSearch('CMDBChangeOp');
		$oFilter->AddCondition('objclass', $this->Get('class_to_anonymize'), '=');
		$oFilter->AddCondition('objkey', $this->Get('id_to_anonymize'), '=');
		MetaModel::PurgeData($oFilter);

		$oMyChangeOp = MetaModel::NewObject("CMDBChangeOpPlugin");
		$oMyChangeOp->Set("objclass", $this->Get('class_to_anonymize'));
		$oMyChangeOp->Set("objkey", $this->Get('id_to_anonymize'));
		$oMyChangeOp->Set("description", 'Anonymization');
		$iId = $oMyChangeOp->DBInsertNoReload();

		// Now remove the name of the contact from all the changes she/he made
		$sChangeTable = MetaModel::DBGetTable('CMDBChange');
		$sKey = MetaModel::DBGetKey('CMDBChange');

		foreach (explode(',', $this->Get('id_user_to_anonymize')) as $sIdUser) {
			$oFilter = new DBObjectSearch('CMDBChangeOp');
			$oFilter->AddCondition('objclass', 'User');
			$oFilter->AddCondition('objkey', $sIdUser, '=');
			MetaModel::PurgeData($oFilter);
		}

		$aDataToAnonymize = json_decode($this->Get('needles'), true);
		$aAnonymizedData = json_decode($this->Get('anonymized_data'), true);
		if (version_compare(ITOP_DESIGN_LATEST_VERSION, '3.0') < 0 || strlen($this->Get('id_user_to_anonymize')) == 0) {
			$bFinish = true;
			foreach ($aDataToAnonymize['friendlyname'] as $sFriendlyName) {
				if ($bFinish) {
					$sSqlSearch = "SELECT `$sKey` from `$sChangeTable` WHERE userinfo=".CMDBSource::Quote($sFriendlyName);
					$sSqlUpdate = "UPDATE `$sChangeTable` SET userinfo=".CMDBSource::Quote($aAnonymizedData['friendlyname']);
					$bFinish = $this->ExecuteQueryByLot($sSqlSearch, [$sSqlUpdate], $sKey, $iTimeLimit);
				}

				if ($bFinish) {
					$sSqlSearch = "SELECT `$sKey` from `$sChangeTable` WHERE userinfo=".CMDBSource::Quote($sFriendlyName.' (CSV)');
					$sSqlUpdate = "UPDATE `$sChangeTable` SET userinfo=".CMDBSource::Quote($aAnonymizedData['friendlyname'].' (CSV)');
					$bFinish = $this->ExecuteQueryByLot($sSqlSearch, [$sSqlUpdate], $sKey, $iTimeLimit);
				}
			}
			if ($bFinish) {
				$this->DBDelete();
				$oScopeQuery = "SELECT BatchAnonymization WHERE id_to_anonymize = :id_to_anonymize ";
				$oSet = new DBObjectSet(DBSearch::FromOQL($oScopeQuery, ['id_to_anonymize' => $this->Get('id_to_anonymize')]));
				if ($oSet->Count() === 0) {
					//end of anonymization mark person as anonymized
					$oPerson = MetaModel::GetObject('Person', $this->Get('id_to_anonymize'), true, true);
					$oPerson->Set('anonymized', true); // Mark the Person as anonymized
					$oPerson->DBWrite();
				}
			}
		} else {
			$sSqlSearch = "SELECT `$sKey` from `$sChangeTable` WHERE user_id in (".$this->Get('id_user_to_anonymize').')';
			$sSqlUpdate = "UPDATE `$sChangeTable` SET userinfo=".CMDBSource::Quote($aAnonymizedData['friendlyname']);
			$bFinish = $this->ExecuteQueryByLot($sSqlSearch, [$sSqlUpdate], $sKey, $iTimeLimit);

			foreach ($aDataToAnonymize['friendlyname'] as $sFriendlyName) {
				if ($bFinish) {
					//remove data created before 3.0
					$sSqlSearch = "SELECT `$sKey` from `$sChangeTable` WHERE userinfo=".CMDBSource::Quote($sFriendlyName).' AND user_id IS NULL';
					$sSqlUpdate = "UPDATE `$sChangeTable` SET userinfo=".CMDBSource::Quote($aAnonymizedData['friendlyname']);
					$bFinish = $this->ExecuteQueryByLot($sSqlSearch, [$sSqlUpdate], $sKey, $iTimeLimit);
				}
				if ($bFinish) {
					$sSqlSearch = "SELECT `$sKey` from `$sChangeTable` WHERE userinfo=".CMDBSource::Quote($sFriendlyName.' (CSV)').' AND user_id IS NULL';
					$sSqlUpdate = "UPDATE `$sChangeTable` SET userinfo=".CMDBSource::Quote($aAnonymizedData['friendlyname'].' (CSV)');
					$bFinish = $this->ExecuteQueryByLot($sSqlSearch, [$sSqlUpdate], $sKey, $iTimeLimit);
				}
			}

			if ($bFinish) {
				$this->DBDelete();
				$oScopeQuery = "SELECT BatchAnonymization WHERE id_to_anonymize = :id_to_anonymize ";
				$oSet = new DBObjectSet(DBSearch::FromOQL($oScopeQuery, ['id_to_anonymize' => $this->Get('id_to_anonymize')]));
				if ($oSet->Count() === 0) {
					//end of anonymization mark person as anonymized
					$oPerson = MetaModel::GetObject('Person', $this->Get('id_to_anonymize'), true, true);
					$oPerson->Set('anonymized', true); // Mark the Person as anonymized
					$oPerson->DBWrite();
				}
			}
		}
	}


	/**
	 * @param $iTimeLimit
	 *
	 * @throws \ArchivedObjectException
	 * @throws \CoreCannotSaveObjectException
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 * @throws \DeleteException
	 * @throws \MissingQueryArgument
	 * @throws \MySQLException
	 * @throws \MySQLHasGoneAwayException
	 * @throws \OQLException
	 */
	protected function CleanupCaseLogsByBatch($iTimeLimit)
	{
		$aCleanupCaseLog = (array)MetaModel::GetConfig()->GetModuleSetting('combodo-anonymizer', 'caselog_content');
		$aIdUser = explode(',', $this->Get('id_user_to_anonymize'));
		if (sizeof($aIdUser) == 0) {
			$bFinish = true;
		} else {
			// 1) Build the expression to search (and replace)
			$sPattern = ' : %1$s (%2$d) ============';
			$aDataToAnonymize = json_decode($this->Get('needles'), true);
			$aDataAnonymized = json_decode($this->Get('anonymized_data'), true);

			foreach ($aDataToAnonymize['friendlyname'] as $sFriendlyName) {

				$sReplaceInIdx = str_repeat('*', strlen($sFriendlyName));
				$sStartReplaceInIdx = "REPLACE(";
				$sEndReplaceInIdx = ", ".CMDBSource::Quote($sFriendlyName).", ".CMDBSource::Quote($sReplaceInIdx).")";

				if (in_array('friendlyname', $aCleanupCaseLog)) {
					$sReplace = str_repeat('*', strlen($sFriendlyName));;
					$sStartReplace = "REPLACE(";
					$sEndReplaceInCaseLog = ", ".CMDBSource::Quote($sFriendlyName).", ".CMDBSource::Quote($sReplace).")";
					$sEndReplaceInTxt = ", ".CMDBSource::Quote($sFriendlyName).", ".CMDBSource::Quote($aDataAnonymized['friendlyname']).")";
				} else {
					$sStartReplace = '';
					$sEndReplaceInCaseLog = '';
					$sEndReplaceInTxt = "";
					foreach ($aIdUser as $sIdUser) {
						$sSearch = sprintf($sPattern, $sFriendlyName, $sIdUser);
						$sReplace = sprintf($sPattern, str_repeat('*', strlen($sFriendlyName)), $sIdUser);

						$sStartReplace = "REPLACE(".$sStartReplace;
						$sEndReplaceInCaseLog = $sEndReplaceInCaseLog.", ".CMDBSource::Quote($sSearch).", ".CMDBSource::Quote($sReplace).")";
						$sEndReplaceInTxt = ", ".CMDBSource::Quote($sFriendlyName).", ".CMDBSource::Quote($aDataAnonymized['friendlyname']).")";
					}
				}
			}

			if (in_array('email', $aCleanupCaseLog)) {
				foreach ($aDataToAnonymize['email'] as $sEmail) {
					$sReplace = str_repeat('*', strlen($sEmail));

					$sStartReplace = "REPLACE(".$sStartReplace;
					$sEndReplaceInCaseLog = $sEndReplaceInCaseLog.", ".CMDBSource::Quote($sEmail).", ".CMDBSource::Quote($sReplace).")";
					$sEndReplaceInTxt = $sEndReplaceInTxt.", ".CMDBSource::Quote($sEmail).", ".CMDBSource::Quote($aDataAnonymized['email']).")";
				}
			}
			$bFinish = false;
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
						foreach ($aDataToAnonymize['friendlyname'] as $sFriendlyName) {
							foreach ($aIdUser as $sIdUser) {
								$aConditions[] = " `$sColumn1` LIKE ".CMDBSource::Quote('%'.sprintf($sPattern, $sFriendlyName, $sIdUser).'%');
							}
						}
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

						$bFinish = $this->ExecuteQueryByLot($sSqlSearch, $aSqlUpdate, $sKey, $iTimeLimit);
						if (!$bFinish) {
							//end of time
							return;
						}
					}
				}
			}
		}
		if ($bFinish) {
			$this->DBDelete();
			$oScopeQuery = "SELECT BatchAnonymization WHERE id_to_anonymize = :id_to_anonymize ";
			$oSet = new DBObjectSet(DBSearch::FromOQL($oScopeQuery, ['id_to_anonymize' => $this->Get('id_to_anonymize')]));
			if ($oSet->Count() === 0) {
				//end of anonymization mark person as anonymized
				$oPerson = MetaModel::GetObject('Person', $this->Get('id_to_anonymize'), true, true);
				$oPerson->Set('anonymized', true); // Mark the Person as anonymized
				$oPerson->DBWrite();
			}
		}
	}

	/**
	 * @param $iTimeLimit
	 *
	 * @throws \ArchivedObjectException
	 * @throws \CoreCannotSaveObjectException
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 * @throws \DeleteException
	 * @throws \MissingQueryArgument
	 * @throws \MySQLException
	 * @throws \MySQLHasGoneAwayException
	 * @throws \OQLException
	 */
	protected function CleanupOnMentionByBatch($iTimeLimit)
	{
		$sCleanupOnmention = MetaModel::GetConfig()->GetModuleSetting('combodo-anonymizer', 'onmention');
		$bFinish = true;
		if ($sCleanupOnmention == 'trigger-only') {
			$oScopeQuery = "SELECT TriggerOnObjectMention";
			$oSet = new DBObjectSet(DBSearch::FromOQL($oScopeQuery));
			while ($oTrigger = $oSet->Fetch()) {
				if ($bFinish) {
					$bFinish = $this->CleanupOnMentionInAClass($iTimeLimit, $oTrigger->Get('target_class'));
				}
			}
		} elseif ($sCleanupOnmention == 'all') {
			/* Maybe in the futur
			foreach (MetaModel::GetClasses() as $sClass) {
				if ($bFinish) {
					$bFinish = $this->CleanupOnMentionInAClass($iTimeLimit, $sClass);
				}
			}*/
		}
		if ($bFinish) {
			$this->DBDelete();
			$oScopeQuery = "SELECT BatchAnonymization WHERE id_to_anonymize = :id_to_anonymize ";
			$oSet = new DBObjectSet(DBSearch::FromOQL($oScopeQuery, ['id_to_anonymize' => $this->Get('id_to_anonymize')]));
			if ($oSet->Count() === 0) {
				//end of anonymization mark person as anonymized
				$oPerson = MetaModel::GetObject('Person', $this->Get('id_to_anonymize'), true, true);
				$oPerson->Set('anonymized', true); // Mark the Person as anonymized
				$oPerson->DBWrite();
			}
		}
	}

	/**
	 * @param $iTimeLimit
	 * @param $sParentClass
	 *
	 * @return bool|null
	 * @throws \ArchivedObjectException
	 * @throws \CoreException
	 * @throws \MySQLException
	 * @throws \MySQLHasGoneAwayException
	 */
	protected function CleanupOnMentionInAClass($iTimeLimit, $sParentClass)
	{
		$bFinish = false;
		$aMentionsAllowedClasses = MetaModel::GetConfig()->Get('mentions.allowed_classes');
		if (sizeof($aMentionsAllowedClasses) == 0) {
			return true;
		}
		$aCleanupCaseLog = (array)MetaModel::GetConfig()->GetModuleSetting('combodo-anonymizer', 'caselog_content');

		$aDataToAnonymize = json_decode($this->Get('needles'), true);
		$aDataAnonymized = json_decode($this->Get('anonymized_data'), true);
		$sEndReplaceInCaseLog = "";
		$sEndReplaceInTxt = "";
		foreach ($aDataToAnonymize['friendlyname'] as $sFriendlyName) {
			$sReplace = str_repeat('*', strlen($sFriendlyName));

			$sStartReplace = "REPLACE(";
			$sEndReplaceInCaseLog = $sEndReplaceInCaseLog.", ".CMDBSource::Quote($sFriendlyName).", ".CMDBSource::Quote($sReplace).")";
			$sEndReplaceInTxt = $sEndReplaceInTxt.", ".CMDBSource::Quote($sFriendlyName).", ".CMDBSource::Quote($aDataAnonymized['friendlyname']).")";
		}
		if (in_array('email', $aCleanupCaseLog)) {
			foreach ($aDataToAnonymize['email'] as $sEmail) {
				$sReplace = str_repeat('*', strlen($sEmail));

				$sStartReplace = "REPLACE(".$sStartReplace;
				$sEndReplaceInCaseLog = $sEndReplaceInCaseLog.", ".CMDBSource::Quote($sEmail).", ".CMDBSource::Quote($sReplace).")";
				$sEndReplaceInTxt = $sEndReplaceInTxt.", ".CMDBSource::Quote($sEmail).", ".CMDBSource::Quote($aDataAnonymized['email']).")";
			}
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

									$bFinish = $this->ExecuteQueryByLot($sSqlSearch, $aSqlUpdate, $sKey, $iTimeLimit);
									if (!$bFinish) {
										//end of time
										return $bFinish;
									}
								}
							}
						}
					}
				}
			}
		}

		return $bFinish;
	}

	/**
	 * @param $iTimeLimit
	 *
	 * @throws \ArchivedObjectException
	 * @throws \CoreCannotSaveObjectException
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 * @throws \DeleteException
	 * @throws \MissingQueryArgument
	 * @throws \MySQLException
	 * @throws \MySQLHasGoneAwayException
	 * @throws \OQLException
	 */
	protected function CleanupEmailByBatch($iTimeLimit)
	{
		$aCleanupEmail = (array)MetaModel::GetConfig()->GetModuleSetting('combodo-anonymizer', 'notification_content');
		if (sizeof($aCleanupEmail) == 0) {
			$bFinish = true;
		} else {
			$sStartReplace = "";
			$sEndReplace = "";
			$sStartReplaceEmail = "";
			$sEndReplaceEmail = "";
			$aCondition = [];
			$aDataToAnonymize = json_decode($this->Get('needles'), true);
			$aAnonymizedData = json_decode($this->Get('anonymized_data'), true);


			if (in_array('friendlyname', $aCleanupEmail)) {
				foreach ($aDataToAnonymize['friendlyname'] as $sFriendlyName) {
					$sReplaceFriendlyname = $aAnonymizedData['friendlyname'];

					$sStartReplace = "REPLACE(";
					$sEndReplace = $sEndReplace.", ".CMDBSource::Quote($sFriendlyName).", ".CMDBSource::Quote($sReplaceFriendlyname).")";
				}
			}
			foreach ($aDataToAnonymize['email'] as $sEmail) {
				$aConditions[] = "`from` like '".$sEmail."'";
				$aConditions[] = "`to` like '%".$sEmail."%'";
				$aConditions[] = "`cc` like '%".$sEmail."%'";
				$aConditions[] = "`bcc` like '%".$sEmail."%'";
				$sReplace2 = $aAnonymizedData['friendlyname'];
				if (in_array('email', $aCleanupEmail)) {
					$sStartReplace = "REPLACE(".$sStartReplace;
					$sEndReplace = $sEndReplace.", ".CMDBSource::Quote($sEmail).", ".CMDBSource::Quote($aAnonymizedData['email']).")";
				}
				$sStartReplaceEmail = "REPLACE(".$sStartReplaceEmail;
				$sEndReplaceEmail = $sEndReplaceEmail.", ".CMDBSource::Quote($sEmail).", ".CMDBSource::Quote($aAnonymizedData['email']).")";
			}

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
			$bFinish = $this->ExecuteQueryByLot($sSqlSearch, [$sSqlUpdate], $sKey, $iTimeLimit);
		}

		if ($bFinish) {
			$this->DBDelete();
			$oScopeQuery = "SELECT BatchAnonymization WHERE id_to_anonymize = :id_to_anonymize ";
			$oSet = new DBObjectSet(DBSearch::FromOQL($oScopeQuery, ['id_to_anonymize' => $this->Get('id_to_anonymize')]));
			if ($oSet->Count() === 0) {
				//end of anonymization mark person as anonymized
				$oPerson = MetaModel::GetObject('Person', $this->Get('id_to_anonymize'), true, true);
				$oPerson->Set('anonymized', true); // Mark the Person as anonymized
				$oPerson->DBWrite();
			}
		}
	}
}