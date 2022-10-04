<?php

use Combodo\iTop\Anonymizer\Helper\AnonymizerHelper;


/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

class PersonalDataAnonymizer extends AbstractTimeRangeWeeklyScheduledProcess
{

	protected function GetDefaultModuleSettingTime()
	{
		return '01:00';
	}

	protected function GetDefaultModuleSettingEndTime()
	{
		return '05:00';
	}

	/**
	 * @inheritDoc
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 * @throws \MySQLException
	 * @throws \OQLException
	 */
	public function Process($iUnixTimeLimit)
	{
		$this->sTimeLimit = $iUnixTimeLimit;
		$iMaxChunkSize = MetaModel::GetModuleSetting($this->GetModuleName(), 'max_chunk_size', 1000);
		$sBatchAnonymisation = MetaModel::DBGetTable('BatchAnonymization');
		$oResult = CMDBSource::Query("SELECT DISTINCT id_to_anonymize FROM $sBatchAnonymisation");
		$aIdPersonAlreadyInProgress = [];
		if ($oResult->num_rows > 0) {
			while ($oRaw = $oResult->fetch_assoc()) {
				$aIdPersonAlreadyInProgress[] = $oRaw['id_to_anonymize'];
			}
		}
		$bAnonymizeObsoletePersons = MetaModel::GetModuleSetting($this->GetModuleName(), 'anonymize_obsolete_persons', false);
		$iCountAnonymized = 0;
		if ($bAnonymizeObsoletePersons) {
			$iRetentionDays = MetaModel::GetModuleSetting($this->GetModuleName(), 'obsolete_persons_retention', -1);
			if ($iRetentionDays > 0) {
				$sOQL = "SELECT Person WHERE obsolescence_flag = 1 AND anonymized = 0 AND obsolescence_date < :date";
				if (sizeof($aIdPersonAlreadyInProgress) > 0) {
					$sOQL .= " AND id NOT IN (".implode(",", $aIdPersonAlreadyInProgress).")";
				}
				$this->Trace('RetentionDays'.$iRetentionDays);
				$this->Trace($sOQL);
				$oDateLimit = new DateTime();
				$oDateLimit->modify("-$iRetentionDays days");
				$sDateLimit = $oDateLimit->format(AttributeDateTime::GetSQLFormat());

				$this->Trace('|- Parameters:');
				$this->Trace('|  |- OQL scope: '.$sOQL);
				$this->Trace('|  |- sDate Limit: '.$sDateLimit);

				$bExecuteQuery = true;
				while ((time() < $iUnixTimeLimit) && $bExecuteQuery) {
					$iCountCurrentQuery = 0;
					$oSet = new DBObjectSet(DBSearch::FromOQL($sOQL), array('obsolescence_date' => true), array('date' => $sDateLimit), null, $iMaxChunkSize);
					while ((time() < $iUnixTimeLimit) && ($oPerson = $oSet->Fetch())) {
						$oPerson->Anonymize();
						$iCountAnonymized++;
						$iCountCurrentQuery++;
					}
					if ($iCountCurrentQuery < $iMaxChunkSize) {
						$bExecuteQuery = false;
					}
				}
			}
		}

		$iStepAnonymized = 0;
		$sOQL = "SELECT BatchAnonymization";
		$bExecuteQuery = true;

		$this->Trace('|- Parameters:');
		$this->Trace('|  |- OQL scope: '.$sOQL);
		$iNbPersonAnonymized = 0;

		while ((time() < $iUnixTimeLimit) && $bExecuteQuery) {
			$oSet = new DBObjectSet(DBSearch::FromOQL($sOQL), array(), array(), null, $iMaxChunkSize);
			$sIdCurrentPerson = '';
			$iLocalCounter = 0;
			$bResult = false;
			$iNotFinish = 0;
			while ((time() < $iUnixTimeLimit) && ($oStepForAnonymize = $oSet->Fetch())) {
				if ($sIdCurrentPerson != $oStepForAnonymize->Get('id_to_anonymize')) {
					if ($sIdCurrentPerson != '') {
						$iNbPersonAnonymized++;
					}
					$sIdCurrentPerson = $oStepForAnonymize->Get('id_to_anonymize');
					$this->Trace('|  |  |Anonymized idPerson: '.$sIdCurrentPerson);
				}
				$this->Trace('|  |  |  | function: '.$oStepForAnonymize->Get('function'));
				$bResult = $oStepForAnonymize->ExecuteStep($iUnixTimeLimit);
				if (!$bResult) {
					$iNotFinish++;
				}
				$iStepAnonymized++;
				$iLocalCounter++;
			}

			if (time() < $iUnixTimeLimit && $sIdCurrentPerson != '' && $bResult) {
				$iNbPersonAnonymized++;
			}
			if ($iLocalCounter < $iMaxChunkSize) {
				$bExecuteQuery = false;
			}
		}
		$sMessage = sprintf("Anonymization started for %d person(s). %d person(s) completly anonymized.%d step(s) executed.%d step(s) not finish or in error", $iCountAnonymized, $iNbPersonAnonymized, $iStepAnonymized, $iNotFinish);

		return $sMessage;
	}

	protected function GetModuleName()
	{
		return AnonymizerHelper::MODULE_NAME;
	}
}