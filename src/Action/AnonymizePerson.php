<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\Anonymizer\Action;

use BatchAnonymizationTaskAction;
use Combodo\iTop\Anonymizer\Helper\AnonymizerLog;
use Combodo\iTop\Anonymizer\Service\CleanupService;
use DBObjectSet;
use DBSearch;
use MetaModel;

class AnonymizePerson extends BatchAnonymizationTaskAction
{

	public function InitActionParams()
	{
		$oTask = $this->GetTask();
		$sClass = $oTask->Get('class_to_anonymize');
		$sId = $oTask->Get('id_to_anonymize');

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

		$oTask->Set('anonymization_context', json_encode($aContext));
		$oTask->DBWrite();
	}

	/**
	 * @inheritDoc
	 */
	public function ExecuteAction($iEndExecutionTime): bool
	{
		$oTask = $this->GetTask();
		$sClass = $oTask->Get('class_to_anonymize');
		$sId = $oTask->Get('id_to_anonymize');
		$oCleanupService = new CleanupService($sClass, $sId, $iEndExecutionTime);
		/** @var \Person $oPerson */
		$oPerson = MetaModel::GetObject($sClass, $sId);
		$oCleanupService->AnonymizePerson($oPerson);
		$oPerson->DBWrite();
		$oPerson->Reload();

		$aContext = json_decode($oTask->Get('anonymization_context'), true);
		$aContext['anonymized'] = [
			'friendlyname' => $oPerson->Get('friendlyname'),
			'email'        => $oPerson->Get('email'),
		];
		AnonymizerLog::Debug('Anonymization context: '.var_export($aContext, true));

		$oTask->Set('anonymization_context', json_encode($aContext));
		$oTask->DBWrite();

		return true;
	}
}