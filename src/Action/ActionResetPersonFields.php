<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */


use Combodo\iTop\Anonymizer\Helper\AnonymizerLog;
use Combodo\iTop\Anonymizer\Service\CleanupService;

/**
 * reset all non-mandatory fields of the anonymized person
 */
class ActionResetPersonFields extends AnonymizationTaskAction
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
			'db_table'            => 'priv_anonymization_task_action_reset_person_fields',
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

	public function InitActionParams()
	{
		$oTask = $this->GetTask();
		$sClass = $oTask->Get('class_to_anonymize');
		$sId = $oTask->Get('id_to_anonymize');

		$oObject = MetaModel::GetObject($sClass, $sId);

		AnonymizerLog::Debug('email'.$oObject->Get('email'));
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
			$aContext['origin']['changeop_id'] = $oChangeCreate->GetKey();
			//$aContext['origin']['obsolescence_date'] = $oObject->Get('obsolescence_date') ?? new DateTime();
		}

		$oTask->Set('anonymization_context', json_encode($aContext));
		$oTask->DBWrite();
	}

	/**
	 * @param $iEndExecutionTime
	 *
	 * @return bool
	 * @throws \ArchivedObjectException
	 * @throws \CoreCannotSaveObjectException
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 */
	public function ExecuteAction($iEndExecutionTime): bool
	{
		$oTask = $this->GetTask();

		$sClass = $oTask->Get('class_to_anonymize');
		$sId = $oTask->Get('id_to_anonymize');

		$oService = new CleanupService($sClass, $sId, $iEndExecutionTime);

		return $oService->ResetObjectFields();
	}
}