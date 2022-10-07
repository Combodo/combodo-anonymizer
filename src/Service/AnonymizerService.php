<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\Anonymizer\Service;

use CMDBSource;
use Combodo\iTop\Anonymizer\Helper\AnonymizerHelper;
use Combodo\iTop\Anonymizer\Helper\AnonymizerLog;
use Combodo\iTop\ComplexBackgroundTask\Service\ComplexBackgroundTaskService;
use DBObjectSearch;
use DBObjectSet;
use DBSearch;
use Exception;
use MetaModel;
use UserRights;

class AnonymizerService
{
	const BATCH_ANONYMIZATION_TASK = 'BatchAnonymizationTask';
	/** @var int */
	private $iProcessEndTime;
	/** @var int */
	private $iMaxChunkSize;
	/** @var array */
	private $aAnonymizedFields;
	/** @var array */
	private $aActions;

	public function __construct()
	{
		$this->iProcessEndTime = time() + MetaModel::GetConfig()->GetModuleParameter(AnonymizerHelper::MODULE_NAME, 'max_interactive_anonymization_time_in_s', 30);
		$this->iMaxChunkSize = MetaModel::GetConfig()->GetModuleParameter(AnonymizerHelper::MODULE_NAME, 'max_chunk_size', 1000);
		$this->aActions = MetaModel::GetConfig()->GetModuleParameter(AnonymizerHelper::MODULE_NAME, 'actions', []);
		$this->aAnonymizedFields = MetaModel::GetConfig()->GetModuleParameter(AnonymizerHelper::MODULE_NAME, 'anonymized_fields', []);
	}


	/**
	 * @param $sClass
	 * @param $sId
	 * @param bool $bInteractive
	 *
	 * @return void
	 * @throws \ArchivedObjectException
	 * @throws \CoreCannotSaveObjectException
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 * @throws \CoreWarning
	 * @throws \DeleteException
	 * @throws \MySQLException
	 * @throws \MySQLHasGoneAwayException
	 * @throws \OQLException
	 */
	public function AnonymizeOneObject($sClass, $sId, $bInteractive = false)
	{
		$this->AddAnonymizationToProcessList($sClass, $sId);

		if ($bInteractive) {
			// run one time background process.
			$this->ProcessAnonymization();
		}
	}

	/**
	 * @param $sFilter
	 * @param bool $bInteractive
	 *
	 * @throws \ArchivedObjectException
	 * @throws \CoreCannotSaveObjectException
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 * @throws \CoreWarning
	 * @throws \DeleteException
	 * @throws \MySQLException
	 * @throws \MySQLHasGoneAwayException
	 * @throws \OQLException
	 */
	public function AnonymizeObjectList($sFilter, $bInteractive = false)
	{
		$oSearch = DBSearch::unserialize($sFilter);
		$oSet = new DBObjectSet($oSearch);

		$iCount = 1;
		CMDBSource::Query('START TRANSACTION');
		try {
			while ($oObject = $oSet->Fetch()) {
				$this->AddAnonymizationToProcessList(get_class($oObject), $oObject->GetKey());
				$iCount++;
				if ($iCount > $this->iMaxChunkSize) {
					$iCount = 1;
					CMDBSource::Query('COMMIT');
					CMDBSource::Query('START TRANSACTION');
				}
			}
			CMDBSource::Query('COMMIT');

			if ($bInteractive) {
				//run one time background process.
				$this->ProcessAnonymization();
			}
		}
		catch (Exception $e) {
			CMDBSource::Query('ROLLBACK');
			AnonymizerLog::Error("Anonymization using $sFilter failed: ".$e->getMessage());
			throw $e;
		}
	}

	/**
	 * @param $sClass
	 * @param $sId
	 *
	 * @return void
	 * @throws \ArchivedObjectException
	 * @throws \CoreCannotSaveObjectException
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 * @throws \CoreWarning
	 * @throws \MySQLException
	 * @throws \OQLException
	 */
	protected function AddAnonymizationToProcessList($sClass, $sId)
	{
		if (!$this->IsAllowedToAnonymize($sClass, $sId)) {
			AnonymizerLog::Error("Trying to anonymize administrator user with contact id $sId");

			return;
		}
		$oTask = MetaModel::NewObject(self::BATCH_ANONYMIZATION_TASK);
		$oTask->Set('name', 'Anonymizer');
		$oTask->Set('class_to_anonymize', $sClass);
		$oTask->Set('id_to_anonymize', $sId);
		$oTask->DBInsert();
	}

	/**
	 * @throws \CoreException
	 * @throws \DeleteException
	 * @throws \CoreCannotSaveObjectException
	 * @throws \MySQLException
	 * @throws \MySQLHasGoneAwayException
	 * @throws \CoreUnexpectedValue
	 * @throws \OQLException
	 * @throws \ArchivedObjectException
	 */
	public function ProcessAnonymization()
	{
		$oComplexService = new ComplexBackgroundTaskService();
		$oComplexService->SetActions($this->aActions);
		$oComplexService->SetProcessEndTime($this->iProcessEndTime);

		$oComplexService->ProcessTasks(self::BATCH_ANONYMIZATION_TASK);
	}

	public function IsAllowedToAnonymize($sClass, $sId)
	{
		if (!UserRights::IsAdministrator() && $sClass == 'Person') {
			// Cannot anonymize a person having an admin User
			foreach ($this->GetUserIdListFromContact($sId) as $sUserId) {
				if (UserRights::IsAdministrator(MetaModel::GetObject('User', $sUserId))) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * @param $sContactId
	 *
	 * @return int[]|string[]
	 * @throws \CoreException
	 */
	public function GetUserIdListFromContact($sContactId)
	{
		$oSearch = new DBObjectSearch('User');
		$oSearch->AddCondition('contactid', $sContactId);
		$oSearch->AllowAllData();
		$oSet = new DBObjectSet($oSearch);
		$oSet->OptimizeColumnLoad(['User' => ['finalclass']]);
		$aIdToClass = $oSet->GetColumnAsArray('finalclass');

		return array_keys($aIdToClass);
	}

	public function GetAnonymizedFields($sId)
	{
		$aFields = [];
		$sTemplate = $this->aAnonymizedFields['name'] ?? 'xxxx';
		$aFields['name'] = vsprintf($sTemplate, $sId);

		$sTemplate = $this->aAnonymizedFields['first_name'] ?? 'xxxx';
		$aFields['first_name'] = vsprintf($sTemplate, $sId);

		$sTemplate = $this->aAnonymizedFields['email'] ?? 'xxxx@xxxx.xxx';
		$aFields['email'] = str_replace(' ', '', vsprintf($sTemplate, [$aFields['first_name'], $aFields['name'], $sId]));

		return $aFields;
	}

	protected function IsTimeoutReached()
	{
		return (time() > $this->iProcessEndTime);
	}

	/**
	 * @param int $iProcessEndTime
	 */
	public function SetProcessEndTime(int $iProcessEndTime)
	{
		$this->iProcessEndTime = $iProcessEndTime;
	}

	/**
	 * @param int|mixed|null $iMaxChunkSize
	 */
	public function SetMaxChunkSize($iMaxChunkSize)
	{
		$this->iMaxChunkSize = $iMaxChunkSize;
	}

	/**
	 * @param array|mixed|null $aActions
	 */
	public function SetActions($aActions)
	{
		$this->aActions = $aActions;
	}

	/**
	 * @param array|mixed|null $aAnonymizedFields
	 */
	public function SetAnonymizedFields($aAnonymizedFields)
	{
		$this->aAnonymizedFields = $aAnonymizedFields;
	}

}