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


abstract class AbstractBatchAnonymizationTask extends DBObject
{

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

		return $bFinish;
	}
}