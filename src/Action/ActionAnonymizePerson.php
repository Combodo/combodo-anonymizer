<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

use Combodo\iTop\Anonymizer\Helper\AnonymizerLog;
use Combodo\iTop\Anonymizer\Service\CleanupService;

/**
 * Set new values in Person to anonymize its data
 */
class ActionAnonymizePerson extends AnonymizationTaskAction
{
	/**
	 * @throws \CoreException
	 */
	public static function Init()
	{
		$aParams = array
		(
			'category'            => '',
			'key_type'            => 'autoincrement',
			'name_attcode'        => 'name',
			'state_attcode'       => '',
			'reconc_keys'         => array('name'),
			'db_table'            => 'priv_anonymization_task_action_anonymize_person',
			'db_key_field'        => 'id',
			'db_finalclass_field' => '',
			'display_template'    => '',
		);
		MetaModel::Init_Params($aParams);
		MetaModel::Init_InheritAttributes();

		// Display lists
		MetaModel::Init_SetZListItems('list', array('name', 'rank')); // Attributes to be displayed for a list
		// Search criteria
		MetaModel::Init_SetZListItems('standard_search', array('name')); // Criteria of the std search form
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