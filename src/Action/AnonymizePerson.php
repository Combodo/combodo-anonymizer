<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\Anonymizer\Action;

use Combodo\iTop\Anonymizer\Service\CleanupService;
use MetaModel;

class AnonymizePerson extends AbstractAnonymizationAction
{

	public function Init()
	{
		$sClass = $this->oTask->Get('class_to_anonymize');
		$sId = $this->oTask->Get('id_to_anonymize');

		$oObject = MetaModel::GetObject($sClass, $sId);

		$aContext = [
			'origin' => [
				'friendlyname' => $oObject->Get('friendlyname'),
			],
		];

		$this->oTask->Set('anonymization_context', json_encode($aContext));
		$this->oTask->DBWrite();
	}

	/**
	 * @inheritDoc
	 */
	public function Execute(): bool
	{
		$sClass = $this->oTask->Get('class_to_anonymize');
		$sId = $this->oTask->Get('id_to_anonymize');
		$oCleanupService = new CleanupService($sClass, $sId, $this->iEndExecutionTime);
		/** @var \Person $oPerson */
		$oPerson = MetaModel::GetObject($sClass, $sId);
		$oCleanupService->AnonymizePerson($oPerson);
		$oPerson->DBWrite();
		$oPerson->Reload();

		$aContext = json_decode($this->oTask->Get('anonymization_context'), true);
		$aContext['anonymized']['friendlyname'] = $oPerson->Get('friendlyname');
		$this->oTask->Set('anonymization_context', json_encode($aContext));
		$this->oTask->DBWrite();

		return true;
	}
}