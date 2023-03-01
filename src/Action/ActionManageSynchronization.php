<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

use Combodo\iTop\Anonymizer\Helper\AnonymizerHelper;
use Combodo\iTop\Anonymizer\Helper\AnonymizerLog;
use Combodo\iTop\Anonymizer\Service\CleanupService;

/**
 * Set new values in Person to anonymize its data
 */
class ActionManageSynchronization extends AnonymizationTaskAction
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
			'db_table'            => 'priv_anonym_action_manage_synchro',
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

		AnonymizerLog::Error("Anonymization ActionManageSynchronization of $sClass::$sId Failed");
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function ExecuteAction($iEndExecutionTime): bool
	{
		$sSynchroPolicy = MetaModel::GetModuleSetting(AnonymizerHelper::MODULE_NAME,'synchronisation_policy', '');
		switch($sSynchroPolicy) {
			case 'delete':
			case 'forget':
				$oTask = $this->GetTask();
				$sClass = Person::class;
				$sId = $oTask->Get('person_id');
				$sOql = 'SELECT SynchroReplica WHERE dest_class=\''.$sClass.'\' AND dest_id='.$sId;
				$oSearch = DBObjectSearch::FromOQL($sOql);
				$oSet = new DBObjectSet($oSearch);
				try {
					while ($oObj = $oSet->Fetch()) {
						if ($sSynchroPolicy=='delete') {
							$oObj->DBDelete();
						} else {
							$oObj->Set('dest_id',0);
							$oObj->DBWrite();
						}
					}
				}
				catch (Exception $e) {
					AnonymizerLog::Error('ActionManageSynchronization: '.$e->getMessage());
				}
			break;

			default:
				//do nothing
		}
		$aContext = json_decode($oTask->Get('anonymization_context'), true);
		AnonymizerLog::Debug('Anonymization context: '.var_export($aContext, true));

		$oTask->Set('anonymization_context', json_encode($aContext));
		try {
			$oTask->DBWrite();
		} catch (Exception $e) {
			AnonymizerLog::Error('ActionManageSynchronization: '.$e->getMessage());
		}
		return true;
	}
}