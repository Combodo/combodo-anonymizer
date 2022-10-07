<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\Anonymizer\Action;

use Combodo\iTop\Anonymizer\Helper\AnonymizerHelper;
use Combodo\iTop\Anonymizer\Helper\AnonymizerLog;
use Combodo\iTop\Anonymizer\Service\CleanupService;
use MetaModel;

/**
 * Remove history entries of the selected object
 */
class PurgePersonHistory extends AbstractAnonymizationAction
{
	public function Init()
	{
		$aParams['iChunkSize'] = MetaModel::GetConfig()->GetModuleParameter(AnonymizerHelper::MODULE_NAME, 'max_chunk_size', 1000);

		$this->oTask->Set('action_params', json_encode($aParams));
		$this->oTask->DBWrite();
	}

	public function Retry()
	{
		$aParams = json_decode($this->oTask->Get('action_params'), true);
		$iChunkSize = $aParams['iChunkSize'];
		if ($iChunkSize == 1) {
			AnonymizerLog::Debug('Stop retry action PurgePersonHistory with params '.json_encode($aParams));
			$this->oTask->Set('action_params', '');
			$this->oTask->DBWrite();

			return;
		}
		$aParams['iChunkSize'] = (int)$iChunkSize / 2 + 1;

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
		$sParams = $this->oTask->Get('action_params');
		if ($sParams == '') {
			return true;
		}
		$aParams = json_decode($sParams, true);

		$sClass = $this->oTask->Get('class_to_anonymize');
		$sId = $this->oTask->Get('id_to_anonymize');

		$oService = new CleanupService($sClass, $sId, $this->iEndExecutionTime);

		return $oService->PurgeHistory($aParams['iChunkSize']);
	}
}