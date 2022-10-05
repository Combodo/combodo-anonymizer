<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\Anonymizer\Service;

use CMDBSource;
use Combodo\iTop\Anonymizer\Action\AnonymizationActionFactory;
use Combodo\iTop\Anonymizer\Helper\AnonymizerException;
use Combodo\iTop\Anonymizer\Helper\AnonymizerHelper;
use Combodo\iTop\Anonymizer\Helper\AnonymizerLog;
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
	/** @var array */
	private $aActions;
	/** @var \Combodo\iTop\Anonymizer\Action\AnonymizationActionFactory */
	private $oActionFactory;

	public function __construct($iMaxExecutionTime)
	{
		$this->iProcessEndTime = $iMaxExecutionTime + time();
		$this->iMaxChunkSize = MetaModel::GetConfig()->GetModuleParameter(AnonymizerHelper::MODULE_NAME, 'max_chunk_size', 1000);
		$this->aActions = MetaModel::GetConfig()->GetModuleParameter(AnonymizerHelper::MODULE_NAME, 'actions', []);
		$this->oActionFactory = new AnonymizationActionFactory();
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
		// Process Error tasks first
		if (!$this->ProcessTaskList('SELECT '.self::BATCH_ANONYMIZATION_TASK." WHERE status = 'running'")) {
			return;
		}

		// Process paused tasks
		if (!$this->ProcessTaskList('SELECT '.self::BATCH_ANONYMIZATION_TASK." WHERE status = 'paused'")) {
			return;
		}

		// New tasks to process
		$this->ProcessTaskList('SELECT '.self::BATCH_ANONYMIZATION_TASK." WHERE status = 'created'");
	}

	/**
	 * @param string $sOQL
	 *
	 * @return bool
	 * @throws \ArchivedObjectException
	 * @throws \CoreCannotSaveObjectException
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 * @throws \DeleteException
	 * @throws \MySQLException
	 * @throws \MySQLHasGoneAwayException
	 * @throws \OQLException
	 */
	protected function ProcessTaskList(string $sOQL): bool
	{
		$oSearch = DBSearch::FromOQL($sOQL);
		$oSet = new DBObjectSet($oSearch);
		while ($oTask = $oSet->Fetch()) {
			if ($this->IsTimeoutReached()) {
				return false;
			}
			if ($this->ProcessAnonymizationTask($oTask) == 'finished') {
				$oTask->DBDelete();
			} else {
				return false;
			}
		}
		return true;
	}

	/**
	 * @param \DBObject $oTask
	 *
	 * @return bool
	 * @throws \ArchivedObjectException
	 * @throws \CoreException
	 */
	protected function ProcessAnonymizationTask(DBObject $oTask)
	{
		$sStatus = $oTask->Get('status');
		/** @var \Combodo\iTop\Anonymizer\Action\iAnonymizationAction $oAction */
		$oAction = null;
		$sAction = null;
		$bInProgress = true;
		while ($bInProgress) {
			try {
				switch ($sStatus) {
					case 'created':
					case 'finished':
						$sAction = $this->GetNextAction($sAction);
						$oAction = $this->oActionFactory->GetAnonymizationAction($sAction, $oTask, $this->iProcessEndTime);
						if (is_null($oAction)) {
							$sStatus = 'finished';
							$bInProgress = false;
						} else {
							$oAction->Init();
						}
						break;

					case 'running':
						$sAction = $oTask->Get('action');
						$oAction = $this->oActionFactory->GetAnonymizationAction($sAction, $oTask, $this->iProcessEndTime);
						if (is_null($oAction)) {
							$sStatus = 'finished';
							$bInProgress = false;
						} else {
							$oAction->Retry();
						}
						break;

					case 'paused':
						$sAction = $oTask->Get('action');
						$oAction = $this->oActionFactory->GetAnonymizationAction($sAction, $oTask, $this->iProcessEndTime);
						if (is_null($oAction)) {
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

					$bActionFinished = $oAction->Execute();
					if ($bActionFinished) {
						$sStatus = 'finished';
					} else {
						$sStatus = 'paused';
						$oTask->Set('status', $sStatus);
						$oTask->DBWrite();
						$bInProgress = false;
					}
				}
			}
			catch (AnonymizerException $e) {
				$bInProgress = false;
			}
			catch (Exception $e) {
				// stay in 'running' status
				AnonymizerLog::Error($e->getMessage());
				$bInProgress = false;
			}
		}

		return $sStatus;
	}

	protected function GetNextAction($sAction)
	{
		if (is_null($sAction)) {
			if (isset($this->aActions[0])) {
				return $this->aActions[0];
			}
		}

		foreach ($this->aActions as $key => $sValue) {
			if ($sValue == $sAction) {
				if (isset($this->aActions[$key + 1])) {
					return $this->aActions[$key + 1];
				}
			}
		}

		return null;
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
	 * @param \Combodo\iTop\Anonymizer\Action\AnonymizationActionFactory $oAnonymizationTaskFactory
	 */
	public function SetAnonymizationActionFactory(AnonymizationActionFactory $oAnonymizationTaskFactory)
	{
		$this->oActionFactory = $oAnonymizationTaskFactory;
	}


}