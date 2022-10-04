<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\Anonymizer\Service;

use CMDBSource;
use Combodo\iTop\Anonymizer\Helper\AnonymizerHelper;
use Combodo\iTop\Anonymizer\Helper\AnonymizerLog;
use Combodo\iTop\Anonymizer\Task\iAnonymizationTask;
use DBObject;
use DBObjectSet;
use DBSearch;
use Exception;
use MetaModel;

class AnonymizerService
{
	const BATCH_ANONYMIZATION_TASK = 'BatchAnonymizationTask';
	/** @var int */
	private $iProcessEndTime;
	/** @var int */
	private $iMaxChunkSize;

	public function __construct($iMaxExecutionTime, $iMaxChunkSize = null)
	{
		$this->iProcessEndTime = $iMaxExecutionTime + time();
		if (is_null($iMaxChunkSize)) {
			$this->iMaxChunkSize = MetaModel::GetConfig()->GetModuleParameter(AnonymizerHelper::MODULE_NAME, 'max_chunk_size', 1000);
		} else {
			$this->iMaxChunkSize = $iMaxChunkSize;
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
	 *
	 * @throws \ArchivedObjectException
	 * @throws \CoreCannotSaveObjectException
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 * @throws \CoreWarning
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
		$oTask = MetaModel::NewObject(self::BATCH_ANONYMIZATION_TASK);
		$oTask->Set('class_to_anonymize', $sClass);
		$oTask->Set('id_to_anonymize', $sId);
		$oTask->DBInsert();
	}

	public function ProcessAnonymization()
	{
		// Process Error tasks first
		$this->ProcessTaskList('SELECT '.self::BATCH_ANONYMIZATION_TASK." WHERE status = 'running'");

		// Process paused tasks
		$this->ProcessTaskList('SELECT '.self::BATCH_ANONYMIZATION_TASK." WHERE status = 'paused'");

		// New tasks to process
		$this->ProcessTaskList('SELECT '.self::BATCH_ANONYMIZATION_TASK." WHERE status = 'created'");
	}

	protected function ProcessTaskList($sOQL)
	{
		$oSearch = DBSearch::FromOQL($sOQL);
		$oSet = new DBObjectSet($oSearch);
		while ($oTask = $oSet->Fetch()) {
			if ($this->IsTimeoutReached()) {
				return;
			}
			$this->ProcessAnonymizationTask($oTask);
		}
	}

	/**
	 * @param \DBObject $oTask
	 *
	 * @return void
	 * @throws \ArchivedObjectException
	 * @throws \CoreException
	 */
	protected function ProcessAnonymizationTask(DBObject $oTask)
	{
		$sStatus = $oTask->Get('status');
		/** @var \Combodo\iTop\Anonymizer\Task\iAnonymizationTask $oAction */
		$oAction = null;
		$sAction = null;
		$bInProgress = true;
		while ($bInProgress) {
			switch ($sStatus) {
				case 'created':
				case 'finished':
					$sAction = $this->GetNextAction($sAction);
					if (class_exists($sAction) && isset(class_implements($sAction)[iAnonymizationTask::class])) {
						$oAction = new $sAction($oTask, $this->iProcessEndTime);
						$oAction->Init();
					} else {
						if (!is_null($sAction)) {
							AnonymizerLog::Error("Class $sAction is not an anonymization class");
						}
						$sStatus = 'finished';
						$bInProgress = false;
					}
					break;

				case 'running':
					$sAction = $oTask->Get('action');
					if (class_exists($sAction) && isset(class_implements($sAction)[iAnonymizationTask::class])) {
						$oAction = new $sAction($oTask, $this->iProcessEndTime);
						$oAction->Retry();
					} else {
						AnonymizerLog::Error("Class $sAction is not an anonymization class");
						$sStatus = 'finished';
						$bInProgress = false;
					}
					break;

				case 'paused':
					$sAction = $oTask->Get('action');
					if (class_exists($sAction) && isset(class_implements($sAction)[iAnonymizationTask::class])) {
						$oAction = new $sAction($oTask, $this->iProcessEndTime);
					} else {
						AnonymizerLog::Error("Class $sAction is not an anonymization class");
						$sStatus = 'finished';
						$bInProgress = false;
					}
					break;
			}

			if (!is_null($oAction)) {
				$sStatus = 'running';
				$oTask->Set('status', $sStatus);
				$oTask->Set('action', $sAction);
				$oTask->DBWrite();

				try {
					$bActionFinished = $oAction->Execute();
					if ($bActionFinished) {
						$sStatus = 'finished';
					} else {
						$sStatus ='paused';
						$oTask->Set('status', $sStatus);
						$oTask->DBWrite();
						$bInProgress = false;
					}
				} catch (Exception $e) {
					// stay in 'running' status
					AnonymizerLog::Error($e->getMessage());
					$bInProgress = false;
				}
			} else {
				$bInProgress = false;
			}
		}

		if ($sStatus == 'finished') {
			$oTask->DBDelete();
		}
	}

	protected function GetNextAction($sAction)
	{
		$aActions = MetaModel::GetConfig()->GetModuleParameter(AnonymizerHelper::MODULE_NAME, 'actions', []);
		if (is_null($sAction)) {
			if (isset($aActions[0])) {
				return $aActions[0];
			}
		}

		foreach ($aActions as $key => $sValue) {
			if ($sValue == $sAction) {
				if (isset($aActions[$key+1])) {
					return $aActions[$key+1];
				}
			}
		}

		return null;
	}

	protected function IsTimeoutReached()
	{
		return (time() > $this->iProcessEndTime);
	}
}