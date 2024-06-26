<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SAS
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\Anonymizer\Service;

use AnonymizationTask;
use CMDBSource;
use Combodo\iTop\Anonymizer\Helper\AnonymizerHelper;
use Combodo\iTop\Anonymizer\Helper\AnonymizerLog;
use Combodo\iTop\BackgroundTaskEx\Service\BackgroundTaskExService;
use DBObjectSearch;
use DBObjectSet;
use DBSearch;
use Exception;
use MetaModel;
use UserRights;

class AnonymizerService
{
	/** @var int */
	private $iProcessEndTime;
	/** @var int */
	private $iMaxChunkSize;
	/** @var array */
	private $aAnonymizedFields;
	/** @var bool */
	private $bBackgroundAnonymizationEnabled;
	/** @var int */
	private $iRetentionDays;

	public function __construct()
	{
		AnonymizerLog::Enable(AnonymizerLog::DEBUG_FILE);
		$this->iProcessEndTime = time() + MetaModel::GetConfig()->GetModuleSetting(AnonymizerHelper::MODULE_NAME, 'max_interactive_anonymization_time_in_s', 30);
		$this->iMaxChunkSize = MetaModel::GetConfig()->GetModuleSetting(AnonymizerHelper::MODULE_NAME, 'init_chunk_size', 1000);
		$this->aAnonymizedFields = MetaModel::GetConfig()->GetModuleSetting(AnonymizerHelper::MODULE_NAME, 'anonymized_fields', []);
		$bAnonymizeObsoletePersons = MetaModel::GetConfig()->GetModuleSetting(AnonymizerHelper::MODULE_NAME, 'anonymize_obsolete_persons', false);
		$this->bBackgroundAnonymizationEnabled = ($bAnonymizeObsoletePersons === true || $bAnonymizeObsoletePersons === 'true');
		$this->iRetentionDays = MetaModel::GetConfig()->GetModuleSetting(AnonymizerHelper::MODULE_NAME, 'obsolete_persons_retention', 365);
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
		$this->AddAnonymizationToProcessList($sClass, $sId, $bInteractive);

		if ($bInteractive) {
			// run one time background process.
			$this->ProcessAnonymization($sMessage);
		}
	}

	/**
	 * @param \DBSearch $oSearch
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
	public function AnonymizeObjectList(DBSearch $oSearch, bool $bInteractive = false)
	{
		$oSet = new DBObjectSet($oSearch);

		$iCount = 1;
		CMDBSource::Query('START TRANSACTION');
		try {
			while ($oObject = $oSet->Fetch()) {
				$this->AddAnonymizationToProcessList(get_class($oObject), $oObject->GetKey(), $bInteractive);
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
				$this->ProcessAnonymization($sMessage);
			}
		}
		catch (Exception $e) {
			CMDBSource::Query('ROLLBACK');
			$sFilter = $oSearch->ToOQL(true);
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
	protected function AddAnonymizationToProcessList($sClass, $sId, $bInteractive = false)
	{
		if (!$this->IsAllowedToAnonymize($sClass, $sId)) {
			AnonymizerLog::Error("Trying to anonymize administrator user with contact id $sId");

			return;
		}
		$oTask = MetaModel::NewObject(AnonymizationTask::class);
		$oTask->Set('name', 'Anonymizer');
		$oTask->Set('person_id', $sId);
		if ($bInteractive) {
			$oTask->Set('type', 'interactive');
		}
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
	public function ProcessAnonymization(&$sMessage)
	{
		$oBackgroundTaskExService = new BackgroundTaskExService(AnonymizerLog::DEBUG_FILE);
		$oBackgroundTaskExService->SetProcessEndTime($this->iProcessEndTime);

		$oBackgroundTaskExService->ProcessTasks(AnonymizationTask::class, $sMessage);
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
	public function ProcessBackgroundAnonymization(&$sMessage): bool
	{
		$oSet = new DBObjectSet(new DBObjectSearch(AnonymizationTask::class));
		if ($oSet->Count() == 0) {
			// Gather cleanup rules
			$this->GatherAnonymizationTasks();
		}
		$oBackgroundTaskExService = new BackgroundTaskExService(AnonymizerLog::DEBUG_FILE);
		$oBackgroundTaskExService->SetProcessEndTime($this->iProcessEndTime);

		return $oBackgroundTaskExService->ProcessTasks(AnonymizationTask::class, $sMessage);
	}

	private function GatherAnonymizationTasks()
	{
		if (!$this->bBackgroundAnonymizationEnabled) {
			return;
		}

		$this->AnonymizeObjectList(DBObjectSearch::FromOQL("SELECT Person WHERE anonymized = 0 AND obsolescence_flag = 1 AND obsolescence_date < DATE_SUB(NOW(), INTERVAL $this->iRetentionDays DAY)"));
	}

	public function IsAllowedToAnonymize($sClass, $sId)
	{
		if (!UserRights::IsAdministrator() && $sClass == 'Person') {
			// Cannot anonymize a person having an admin User
			foreach ($this->GetUserIdListFromContact($sId) as $sUserId) {
				if (UserRights::IsAdministrator(MetaModel::GetObject('User', $sUserId, false, true))) {
					return false;
				}
			}
		}
		$oCurrentUser = \UserRights::GetUserObject();
		$iCurrentContactId = $oCurrentUser->Get('contactid');
		if ($sClass == 'Person' && $iCurrentContactId == $sId) {
			// avoid anonymize myself
			return false;
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
		$aFields['name'] = vsprintf($sTemplate, [$sId]);

		$sTemplate = $this->aAnonymizedFields['first_name'] ?? 'xxxx';
		$aFields['first_name'] = vsprintf($sTemplate, [$sId]);

		$sTemplate = $this->aAnonymizedFields['email'] ?? 'xxxx@xxxx.xxx';
		$aFields['email'] = str_replace(' ', '', vsprintf($sTemplate, [$sId]));

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
	 * @param array|mixed|null $aAnonymizedFields
	 */
	public function SetAnonymizedFields($aAnonymizedFields)
	{
		$this->aAnonymizedFields = $aAnonymizedFields;
	}

	/**
	 * @param bool $bBackgroundAnonymizationEnabled
	 */
	public function SetBackgroundAnonymizationEnabled(bool $bBackgroundAnonymizationEnabled)
	{
		$this->bBackgroundAnonymizationEnabled = $bBackgroundAnonymizationEnabled;
	}

	/**
	 * @param int $iRetentionDays
	 */
	public function SetRetentionDays(int $iRetentionDays)
	{
		$this->iRetentionDays = $iRetentionDays;
	}
}