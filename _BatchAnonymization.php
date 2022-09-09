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
	 * @param $sSqlUpdate
	 * @param $sKey
	 * @param $iTimeLimit
	 * Search objects to update and execute update by lot of  max_buffer_size elements
	 * return true if all objects where updated, false if the function don't have the time to finish
	 *
	 * @return bool
	 * @throws \CoreException
	 * @throws \MySQLException
	 * @throws \MySQLHasGoneAwayException
	 */
	protected static function ExecuteQueryByLot($sSqlSearch, $sSqlUpdate, $sKey, $iTimeLimit)
	{
		$iMaxBufferSize = MetaModel::GetConfig()->GetModuleSetting('combodo-anonymizer', 'max_buffer_size', 1000);
		$aObjects = [];
		$bExecuteQuery = true;
		while ($bExecuteQuery) {
			$oResult = CMDBSource::Query($sSqlSearch." LIMIT ".$iMaxBufferSize);
			//echo("\n Search anonymization: ".$sSqlSearch);
			$aObjects = [];
			if ($oResult->num_rows > 0) {
				while ($oRaw = $oResult->fetch_assoc()) {
					$aObjects[] = $oRaw[$sKey];
				}
				$sSQL = $sSqlUpdate."WHERE `$sKey` IN (".implode(', ', $aObjects).");";
				//echo("\n AnonymizationUpdate: ".$sSQL);
				CMDBSource::Query($sSQL);
			}
			if (count($aObjects) < $iMaxBufferSize || (time() >= $iTimeLimit)) {
				$bExecuteQuery = false;
			}
		}

		return (count($aObjects) < $iMaxBufferSize);
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
		$sKey = MetaModel::DBGetTable('CMDBChange');

		if (version_compare(ITOP_DESIGN_LATEST_VERSION, '3.0') < 0 || is_null($this->Get('id_user_to_anonymize'))) {
			$sSqlSearch = "SELECT id from `$sChangeTable` WHERE userinfo=".CMDBSource::Quote($this->Get('friendlyname_to_anonymize'));
			$sSqlUpdate = "UPDATE `$sChangeTable` SET userinfo=".CMDBSource::Quote($this->Get('anonymized_friendlyname'));
			$bFinish = $this->ExecuteQueryByLot($sSqlSearch, $sSqlUpdate, $sKey, $iTimeLimit);

			if ($bFinish) {
				$sSqlSearch = "SELECT id from `$sChangeTable` WHERE userinfo=".CMDBSource::Quote($this->Get('friendlyname_to_anonymize').' (CSV)');
				$sSqlUpdate = "UPDATE `$sChangeTable` SET userinfo=".CMDBSource::Quote($this->Get('anonymized_friendlyname').' (CSV)');
				$bFinish = $this->ExecuteQueryByLot($sSqlSearch, $sSqlUpdate, $sKey, $iTimeLimit);
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

			foreach (explode(',', $this->Get('id_user_to_anonymize')) as $sIdUser) {
				$oFilter = new DBObjectSearch('CMDBChangeOp');
				$oFilter->AddCondition('objclass', 'User');
				$oFilter->AddCondition('objkey', $sIdUser, '=');
				MetaModel::PurgeData($oFilter);
			}

			$sSqlSearch = "SELECT id from `$sChangeTable` WHERE user_id in (".$this->Get('id_user_to_anonymize').')';
			$sSqlUpdate = "UPDATE `$sChangeTable` SET userinfo=".CMDBSource::Quote($this->Get('anonymized_friendlyname'));
			$bFinish = $this->ExecuteQueryByLot($sSqlSearch, $sSqlUpdate, $sKey, $iTimeLimit);

			if ($bFinish) {
				//remove data created before 3.0
				$sSqlSearch = "SELECT id from `$sChangeTable` WHERE userinfo=".CMDBSource::Quote($this->Get('friendlyname_to_anonymize')).' AND user_id IS NULL';
				$sSqlUpdate = "UPDATE `$sChangeTable` SET userinfo=".CMDBSource::Quote($this->Get('anonymized_friendlyname'));
				$bFinish = $this->ExecuteQueryByLot($sSqlSearch, $sSqlUpdate, $sKey, $iTimeLimit);
			}
			if ($bFinish) {
				$sSqlSearch = "SELECT id from `$sChangeTable` WHERE userinfo=".CMDBSource::Quote($this->Get('friendlyname_to_anonymize').' (CSV)').' AND user_id IS NULL';
				$sSqlUpdate = "UPDATE `$sChangeTable` SET userinfo=".CMDBSource::Quote($this->Get('anonymized_friendlyname').' (CSV)');
				$bFinish = $this->ExecuteQueryByLot($sSqlSearch, $sSqlUpdate, $sKey, $iTimeLimit);
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
			$sEraser = str_repeat('*', strlen($this->Get('friendlyname_to_anonymize'))); // replace the person's name by a string of stars... of the same length to preserver the case log's index

			$sSearchIdx = $this->Get('friendlyname_to_anonymize');
			$sReplaceIdx = str_repeat('*', strlen($this->Get('friendlyname_to_anonymize')));

			if (in_array('friendlyname', $aCleanupCaseLog)) {
				$sSearch1 = $this->Get('friendlyname_to_anonymize');
				$sReplace1 = $sEraser;
				$sStartReplace = "REPLACE(";
				$sEndReplace = ", ".CMDBSource::Quote($sSearch1).", ".CMDBSource::Quote($sReplace1).")";
			} else {
				$sStartReplace = '';
				$sEndReplace = '';
				foreach ($aIdUser as $sIdUser) {
					$sSearch1 = sprintf($sPattern, $this->Get('friendlyname_to_anonymize'), $sIdUser);
					$sReplace1 = sprintf($sPattern, $sEraser, $sIdUser);

					$sStartReplace = "REPLACE(".$sStartReplace;
					$sEndReplace = $sEndReplace.", ".CMDBSource::Quote($sSearch1).", ".CMDBSource::Quote($sReplace1).")";
				}
			}

			if (in_array('email', $aCleanupCaseLog)) {
				$sSearch2 = $this->Get('email_to_anonymize');
				$sReplace2 = str_repeat('*', strlen($sSearch2));

				$sStartReplace = "REPLACE(".$sStartReplace;
				$sEndReplace = $sEndReplace.", ".CMDBSource::Quote($sSearch2).", ".CMDBSource::Quote($sReplace2).")";
			}
			$bFinish = false;
			// 2) Find all classes containing case logs
			foreach (MetaModel::GetClasses() as $sClass) {
				foreach (MetaModel::ListAttributeDefs($sClass) as $sAttCode => $oAttDef) {
					$sTable = MetaModel::DBGetTable($sClass);
					$sKey = MetaModel::DBGetTable($sClass);
					if ((MetaModel::GetAttributeOrigin($sClass, $sAttCode) == $sClass) && $oAttDef instanceof AttributeCaseLog) {
						$aSQLColumns = $oAttDef->GetSQLColumns();
						$sColumn1 = array_keys($aSQLColumns)[0]; // We assume that the first column is the text
						$sColumnIdx = array_keys($aSQLColumns)[1]; // We assume that the second column is the index

						$aConditions = [];
						foreach ($aIdUser as $sIdUser) {
							$aConditions[] = " `$sColumn1` LIKE ".CMDBSource::Quote('%'.sprintf($sPattern, $this->Get('friendlyname_to_anonymize'), $sIdUser).'%');
						}
						$sCondition = implode(' OR ', $aConditions);
						$sSqlSearch = "SELECT  id FROM `$sTable` WHERE $sCondition";

						$sSqlUpdate = "UPDATE `$sTable` SET `$sColumn1` = ".$sStartReplace."`$sColumn1`".$sEndReplace.",".
							" `$sColumnIdx` = REPLACE(`$sColumnIdx`, ".CMDBSource::Quote($sSearchIdx).", ".CMDBSource::Quote($sReplaceIdx).") ";
						$bFinish = $this->ExecuteQueryByLot($sSqlSearch, $sSqlUpdate, $sKey, $iTimeLimit);
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
		$sCleanupOnmention = (array)MetaModel::GetConfig()->GetModuleSetting('combodo-anonymizer', 'onmention');
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
			foreach (MetaModel::GetClasses() as $sClass) {
				if ($bFinish) {
					$bFinish = $this->CleanupOnMentionInAClass($iTimeLimit, $sClass);
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

		$sSearch1 = $this->Get('friendlyname_to_anonymize');
		$sReplaceInCaseLog1 = str_repeat('*', strlen($this->Get('friendlyname_to_anonymize')));

		$sStartReplace = "REPLACE(";
		$sEndReplaceInCaseLog = ", ".CMDBSource::Quote($sSearch1).", ".CMDBSource::Quote($sReplaceInCaseLog1).")";

		if (in_array('email', $aCleanupCaseLog)) {
			$sSearch2 = $this->Get('email_to_anonymize');
			$sReplaceInCaseLog2 = str_repeat('*', strlen($sSearch2));

			$sStartReplace = "REPLACE(".$sStartReplace;
			$sEndReplaceInCaseLog = $sEndReplaceInCaseLog.", ".CMDBSource::Quote($sSearch2).", ".CMDBSource::Quote($sReplaceInCaseLog2).")";
		}

		$aClasses = array_merge([$sParentClass], MetaModel::GetSubclasses($sParentClass));
		$aAlreadyDone = [];
		foreach ($aClasses as $sClass) {
			foreach (MetaModel::ListAttributeDefs($sClass) as $sAttCode => $oAttDef) {
				$sTable = MetaModel::DBGetTable($sClass, $sAttCode);
				$sKey = MetaModel::DBGetTable($sClass);
				if (!in_array($sTable.'->'.$sAttCode, $aAlreadyDone)) {
					$aAlreadyDone[] = $sTable.'->'.$sAttCode;
					if ((MetaModel::GetAttributeOrigin($sClass, $sAttCode) == $sClass)) {
						if ($oAttDef instanceof AttributeCaseLog) {
							$aSQLColumns = $oAttDef->GetSQLColumns();
							$sColumn1 = array_keys($aSQLColumns)[0]; // We assume that the first column is the text
							//don't change number of characters
							foreach ($aMentionsAllowedClasses as $sMentionChar => $sMentionClass) {
								if (MetaModel::IsParentClass('Contact', $sMentionClass)) {
									$sSearch = "class=".$sMentionClass."&amp;id=".$this->Get('id_to_anonymize')."\">@";
									$sSqlSearch = "SELECT id from `$sTable` WHERE `$sColumn1` LIKE ".CMDBSource::Quote('%'.$sSearch.'%');
									$sSqlUpdate = "UPDATE `$sTable` SET `$sColumn1` = ".$sStartReplace."`$sColumn1`".$sEndReplaceInCaseLog;
									$bFinish = $this->ExecuteQueryByLot($sSqlSearch, $sSqlUpdate, $sKey, $iTimeLimit);
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
		$sEmailAnonymized = 'anonymous.contact'.$this->Get('id_to_anonymize').'@anony.mized';
		$aCleanupEmail = (array)MetaModel::GetConfig()->GetModuleSetting('combodo-anonymizer', 'notification_content');
		if (sizeof($aCleanupEmail) == 0) {
			$bFinish = true;
		} else {
			$sStartReplace = "";
			$sEndReplace = "";

			if (in_array('friendlyname', $aCleanupEmail)) {
				$sSearch1 = $this->Get('friendlyname_to_anonymize');
				$sReplace1 = $this->Get('anonymized_friendlyname');

				$sStartReplace = "REPLACE(";
				$sEndReplace = ", ".CMDBSource::Quote($sSearch1).", ".CMDBSource::Quote($sReplace1).")";
			}

			if (in_array('email', $aCleanupEmail)) {
				$sSearch2 = $this->Get('email_to_anonymize');
				$sReplace2 = 'anonymous.contact'.$this->Get('id_to_anonymize').'@anony.mized';

				$sStartReplace = "REPLACE(".$sStartReplace;
				$sEndReplace = $sEndReplace.", ".CMDBSource::Quote($sSearch2).", ".CMDBSource::Quote($sReplace2).")";
			}

			// Now change email adress
			$sNotificationTable = MetaModel::DBGetTable('EventNotificationEmail');
			$sKey = MetaModel::DBGetTable('EventNotificationEmail');

			$sSqlSearch = "SELECT id from `$sNotificationTable` WHERE `from` like '".$this->Get('email_to_anonymize')."'";
			$sSqlUpdate = "UPDATE `$sNotificationTable` SET  `from` = REPLACE(`from`, '".$this->Get('email_to_anonymize')."', '".$sEmailAnonymized."'),".
				"  `subject` = ".$sStartReplace."`subject`".$sEndReplace.",".
				"  `body` = ".$sStartReplace."`body`".$sEndReplace." ";
			$bFinish = $this->ExecuteQueryByLot($sSqlSearch, $sSqlUpdate, $sKey, $iTimeLimit);

			if ($bFinish) {
				$sSqlSearch = "SELECT id from `$sNotificationTable` WHERE `to` like '%".$this->Get('email_to_anonymize')."%'";
				$sSqlUpdate = "UPDATE `$sNotificationTable` SET  `to` = REPLACE(`to`, '".$this->Get('email_to_anonymize')."', '".$sEmailAnonymized."'),".
					"  `subject` = ".$sStartReplace."`subject`".$sEndReplace.",".
					"  `body` = ".$sStartReplace."`body`".$sEndReplace."";
				$bFinish = $this->ExecuteQueryByLot($sSqlSearch, $sSqlUpdate, $sKey, $iTimeLimit);
			}

			if ($bFinish) {
				$sSqlSearch = "SELECT id from `$sNotificationTable` WHERE `cc` like '%".$this->Get('email_to_anonymize')."%'";
				$sSqlUpdate = "UPDATE `$sNotificationTable` SET  `cc` = REPLACE(`cc`, '".$this->Get('email_to_anonymize')."', '".$sEmailAnonymized."'),".
					"  `subject` = ".$sStartReplace."`subject`".$sEndReplace.",".
					"  `body` = ".$sStartReplace."`body`".$sEndReplace." ";
				$bFinish = $this->ExecuteQueryByLot($sSqlSearch, $sSqlUpdate, $sKey, $iTimeLimit);
			}

			if ($bFinish) {
				$sSqlSearch = "SELECT id from `$sNotificationTable` WHERE `bcc` like '%".$this->Get('email_to_anonymize')."%'";
				$sSqlUpdate = "UPDATE `$sNotificationTable` SET  `bcc` = REPLACE(`bcc`, '".$this->Get('email_to_anonymize')."', '".$sEmailAnonymized."'),".
					"  `subject` = ".$sStartReplace."`subject`".$sEndReplace.",".
					"  `body` = ".$sStartReplace."`body`".$sEndReplace." ";
				$bFinish = $this->ExecuteQueryByLot($sSqlSearch, $sSqlUpdate, $sKey, $iTimeLimit);
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