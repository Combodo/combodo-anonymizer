<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\Anonymizer\Action;

use Combodo\iTop\Anonymizer\Helper\AnonymizerLog;
use Combodo\iTop\Anonymizer\Service\CleanupService;
use Combodo\iTop\ComplexBackgroundTask\Action\AbstractAction;
use DBObjectSet;
use DBSearch;
use MetaModel;

class AnonymizePerson extends AbstractAction
{

	public function Init()
	{
		$sClass = $this->oTask->Get('class_to_anonymize');
		$sId = $this->oTask->Get('id_to_anonymize');

		$oObject = MetaModel::GetObject($sClass, $sId);

		$aContext = [
			'origin' => [
				'friendlyname' => $oObject->Get('friendlyname'),
				'email'        => $oObject->Get('email'),
			],
		];

		$oSet = new DBObjectSet(
			DBSearch::FromOQL("SELECT CMDBChangeOpCreate WHERE objclass=:class AND objkey=:id"),
			[],
			['class' => $sClass, 'id' => $sId]
		);

		$oChangeCreate = $oSet->Fetch();
		if ($oChangeCreate) {
			$aContext['origin']['date_create'] = $oChangeCreate->Get('date');
		}

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
		$aContext['anonymized'] = [
			'friendlyname' => $oPerson->Get('friendlyname'),
			'email'        => $oPerson->Get('email'),
		];
		AnonymizerLog::Debug('Anonymization context: '.var_export($aContext, true));

		$this->oTask->Set('anonymization_context', json_encode($aContext));
		$this->oTask->DBWrite();

		return true;
	}
}