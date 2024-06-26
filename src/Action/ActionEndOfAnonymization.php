<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SAS
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

use Combodo\iTop\Anonymizer\Helper\AnonymizerLog;


/**
 * reset all non-mandatory fields of the anonymized person
 */
class ActionEndOfAnonymization extends AnonymizationTaskAction
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
			'db_table'            => 'priv_anonym_action_end_anonymization',
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

	public function ChangeActionParamsOnError(): bool
	{
		// Cannot continue with the action
		$oTask = $this->GetTask();

		$sClass = Person::class;
		$sId = $oTask->Get('person_id');

		AnonymizerLog::Error("Anonymization ActionEndOfAnonymization of $sClass::$sId Failed");
		return false;
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
		$sClass = Person::class;
		$sId = $oTask->Get('person_id');

		$oObject = MetaModel::GetObject($sClass, $sId);
		$oObject->Set('anonymized', true);
		$oObject->AllowWrite();
		try {
			$oObject->DBWrite();
		} catch (Exception $e) {
			AnonymizerLog::Error($e->getMessage());
		}
		$oObject->Reload();
		AnonymizerLog::Info("<<< Anonymization of $sClass::$sId ended  ");

		return true;
	}
}