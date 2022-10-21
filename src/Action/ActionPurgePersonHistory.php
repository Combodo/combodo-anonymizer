<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */


use Combodo\iTop\Anonymizer\Helper\AnonymizerHelper;
use Combodo\iTop\Anonymizer\Helper\AnonymizerLog;
use Combodo\iTop\Anonymizer\Service\CleanupService;

/**
 * Remove history entries of the selected object
 */
class ActionPurgePersonHistory extends AnonymizationTaskAction
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
			'db_table'            => 'priv_anonym_action_purge_person_history',
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
	 * @return void
	 * @throws \CoreCannotSaveObjectException
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 */
	public function InitActionParams(): bool
	{
		$aParams['iChunkSize'] = MetaModel::GetConfig()->GetModuleParameter(AnonymizerHelper::MODULE_NAME, 'max_chunk_size', 1000);

		$this->Set('action_params', json_encode($aParams));
		$this->DBWrite();

		return true;
	}

	/**	 *
	 * modify iChunkSize (divide by 2) before continuing to clean the data of the anonymized person
	 *
	 * @return void
	 * @throws \ArchivedObjectException
	 * @throws \CoreCannotSaveObjectException
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 */
	public function ChangeActionParamsOnError(): bool
	{
		$aParams = json_decode($this->Get('action_params'), true);
		$iChunkSize = $aParams['iChunkSize'];
		if ($iChunkSize <= 1) {
			AnonymizerLog::Debug('Stop retry action ActionPurgePersonHistory with params '.json_encode($aParams));
			$this->Set('action_params', '');
			$this->DBWrite();

			return false;
		}
		$aParams['iChunkSize'] = (int)$iChunkSize / 2;
		$this->Set('action_params', json_encode($aParams));
		$this->DBWrite();

		return true;
	}

	/**
	 * Delete history entries, no need to keep track of the progress.
	 *
	 * @param $iEndExecutionTime
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
	public function ExecuteAction($iEndExecutionTime): bool
	{
		$oTask = $this->GetTask();

		$sParams = $this->Get('action_params');
		if ($sParams == '') {
			return true;
		}
		$aParams = json_decode($sParams, true);

		$sClass = Person::class;
		$sId = $oTask->Get('person_id');

		$oService = new CleanupService($sClass, $sId, $iEndExecutionTime);

		return $oService->PurgeHistory($aParams['iChunkSize']);
	}
}