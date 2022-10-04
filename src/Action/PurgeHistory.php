<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\Anonymizer\Action;

use Combodo\iTop\Anonymizer\Helper\AnonymizerHelper;
use Combodo\iTop\Anonymizer\Service\CleanupService;
use DBObject;
use MetaModel;

/**
 * Remove history entries of the selected object
 */
class PurgeHistory implements iAnonymizationAction
{
	private $oTask;
	private $iEndExecutionTime;

	public function __construct(DBObject $oTask, $iEndExecutionTime)
	{
		$this->oTask = $oTask;
		$this->iEndExecutionTime = $iEndExecutionTime;
	}

	public function Init()
	{
		$aParams['iChunkSize'] = MetaModel::GetConfig()->GetModuleParameter(AnonymizerHelper::MODULE_NAME, 'max_chunk_size', 1000);

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

	public function Execute(): bool
	{
		$aParams = json_decode($this->oTask->Get('action_params'), true);

		$sClass = $this->oTask->Get('class_to_anonymize');
		$sId = $this->oTask->Get('id_to_anonymize');

		$oService = new CleanupService($sClass, $sId, $this->iEndExecutionTime);
		$oService->PurgeHistory($aParams['iChunkSize']);

		return true;
	}
}