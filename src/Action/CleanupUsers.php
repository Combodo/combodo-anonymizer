<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\Anonymizer\Action;

use Combodo\iTop\Anonymizer\Helper\AnonymizerHelper;
use Combodo\iTop\Anonymizer\Service\CleanupService;
use DBObjectSearch;
use DBObjectSet;
use MetaModel;

class CleanupUsers extends AbstractAnonymizationAction
{
	const USER_CLASS = 'User';

	public function Init()
	{
		$aParams['iChunkSize'] = MetaModel::GetConfig()->GetModuleParameter(AnonymizerHelper::MODULE_NAME, 'max_chunk_size', 1000);

		$sId = $this->oTask->Get('id_to_anonymize');
		$oSearch = new DBObjectSearch(self::USER_CLASS);
		$oSearch->AddCondition('contactid', $sId);
		$oSearch->AllowAllData();
		$oSet = new DBObjectSet($oSearch);
		$oSet->OptimizeColumnLoad(array(self::USER_CLASS => array('finalclass')));
		$aIdToClass = $oSet->GetColumnAsArray('finalclass');
		$aParams['aUserIds'] = array_keys($aIdToClass);
		$aParams['sCurrentUserId'] = reset($aParams['aUserIds']);

		$this->oTask->Set('action_params', json_encode($aParams));
		$this->oTask->DBWrite();
	}

	public function Retry()
	{
		$aParams = json_decode($this->oTask->Get('action_params'), true);
		$aParams['iChunkSize'] /= 2 + 1;

		$this->oTask->Set('action_params', json_encode($aParams));
		$this->oTask->DBWrite();
	}

	/**
	 * Delete history entries, no need to keep track of the progress.
	 *
	 * @return bool
	 * @throws \ArchivedObjectException
	 * @throws \CoreCannotSaveObjectException
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 * @throws \CoreWarning
	 * @throws \MySQLException
	 * @throws \OQLException
	 */
	public function Execute(): bool
	{
		$aParams = json_decode($this->oTask->Get('action_params'), true);

		// Progress until the current user
		$iUserId = false;
		foreach ($aParams['aUserIds'] as $iUserId) {
			if ($iUserId === $aParams['sCurrentUserId']) {
				break;
			}
		}

		while ($iUserId !== false) {
			/** @var \User $oUser */
			$oUser = MetaModel::GetObject(self::USER_CLASS, $iUserId);
			$oService = new CleanupService(get_class($oUser), $iUserId, $this->iEndExecutionTime);
			// Disable User, reset login and password
			$oService->CleanupUser($oUser);
			if (!$oService->PurgeHistory($aParams['iChunkSize'])) {
				return false;
			}
			$iUserId = next($aParams['aUserIds']);

			// Save progression
			$aParams['sCurrentUserId'] = $iUserId;
			$this->oTask->Set('action_params', json_encode($aParams));
			$this->oTask->DBWrite();
		}

		return true;
	}
}