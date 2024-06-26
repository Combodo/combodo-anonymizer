<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SAS
 * @license     http://opensource.org/licenses/AGPL-3.0
 */


use Combodo\iTop\Anonymizer\Helper\AnonymizerLog;
use Combodo\iTop\Anonymizer\Service\AnonymizerService;
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
			'db_table'            => 'priv_anonym_action_reset_person_fields',
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

	public function InitActionParams(): bool
	{
		$oTask = $this->GetTask();
		$sClass = Person::class;
		$sId = $oTask->Get('person_id');

		$oObject = MetaModel::GetObject($sClass, $sId);
		$sUserFriendlyname = trim($oObject->Get('friendlyname'));
		$oAnonymizerService = new AnonymizerService();
		$aUsersId = $oAnonymizerService->GetUserIdListFromContact($sId);
		foreach ($aUsersId as $iUserId) {
			/** @var \User $oUser */
			$oUser = MetaModel::GetObject('User', $iUserId, false, true);
			if ($oUser) {
				$sUserFriendlyname = $oUser->GetFriendlyName();
				break;
			}
		}

		$aContext = [
			'origin' => [
				'friendlyname'      => trim($oObject->Get('friendlyname')),
				'email'             => trim($oObject->Get('email')),
				'user_friendlyname' => $sUserFriendlyname,
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

		return true;
	}

	public function ChangeActionParamsOnError(): bool
	{
		// Cannot continue with the action
		$oTask = $this->GetTask();

		$sClass = Person::class;
		$sId = $oTask->Get('person_id');

		AnonymizerLog::Error("Anonymization ActionResetPersonFields of $sClass::$sId Failed");
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
		AnonymizerLog::Info(">>> Anonymization of $sClass::$sId started");

		$oService = new CleanupService($sClass, $sId, $iEndExecutionTime);

		return $oService->ResetObjectFields();
	}
}