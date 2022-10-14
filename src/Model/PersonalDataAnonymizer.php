<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

use Combodo\iTop\Anonymizer\Helper\AnonymizerHelper;
use Combodo\iTop\ComplexBackgroundTask\Service\ComplexBackgroundTaskService;

class PersonalDataAnonymizer extends AbstractTimeRangeWeeklyScheduledProcess
{

	/**
	 * @inheritDoc
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 * @throws \MySQLException
	 * @throws \OQLException
	 */
	public function Process($iUnixTimeLimit)
	{
		$DBSearch = new DBObjectSearch('AnonymizationTask');
		$oSet = new CMDBObjectSet($DBSearch);
		$iCount = $oSet->Count();
		if ($iCount == 0) {
			return 'Nothing to do';
		}
		$sMessage = sprintf("Anonymization started for %d person(s)", $iCount);

		$aBackGroundTaskService = new ComplexBackgroundTaskService();
		$aBackGroundTaskService->SetProcessEndTime($iUnixTimeLimit);
		$aBackGroundTaskService->ProcessTasks('AnonymizationTask', $sMessage);

		$oSet = new CMDBObjectSet($DBSearch);
		$iCount = $oSet->Count();
		if ($iCount == 0) {
			$sMessage .= sprintf("\nAnonymization finished.");
		} else {
			$sMessage .= sprintf("\nAnonymization not finished. %d person(s) left to anonymize.", $iCount);
		}

		return $sMessage;
	}

	protected function GetModuleName()
	{
		return AnonymizerHelper::MODULE_NAME;
	}
}