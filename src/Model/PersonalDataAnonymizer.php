<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

use Combodo\iTop\Anonymizer\Helper\AnonymizerHelper;
use Combodo\iTop\Anonymizer\Service\AnonymizerService;

class PersonalDataAnonymizer extends AbstractTimeRangeWeeklyScheduledProcess
{
	const MODULE_SETTING_MAX_EXECUTION_TIME = 'max_execution_time';

	public function GetNextOccurrence($sCurrentTime = 'now')
	{
		// remember the starting point from the last execution
		// $sCurrentTime = DBProperty::GetProperty(self::NEXT_OCCURRENCE, $sCurrentTime);
		return parent::GetNextOccurrence($sCurrentTime);
	}

	/**
	 * @inheritDoc
	 *
	 * @param $iUnixTimeLimit
	 *
	 * @return string
	 * @throws \ArchivedObjectException
	 * @throws \CoreCannotSaveObjectException
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 * @throws \DeleteException
	 * @throws \MySQLException
	 * @throws \MySQLHasGoneAwayException
	 * @throws \OQLException
	 * @throws \ProcessInvalidConfigException
	 */
	public function Process($iUnixTimeLimit)
	{
		$iSelfLimit = time() + MetaModel::GetConfig()->GetModuleSetting(AnonymizerHelper::MODULE_NAME, static::MODULE_SETTING_MAX_EXECUTION_TIME, 30);
		if ($iSelfLimit < $iUnixTimeLimit) {
			$iUnixTimeLimit = $iSelfLimit;
		}
		$oService = new AnonymizerService();
		$oService->SetProcessEndTime($iUnixTimeLimit);
		$sMessage = '';
		$oService->ProcessBackgroundAnonymization($sMessage);

		return $sMessage;
	}

	protected function GetModuleName()
	{
		return AnonymizerHelper::MODULE_NAME;
	}
}