<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */


use Combodo\iTop\Anonymizer\Helper\AnonymizerHelper;
use Combodo\iTop\Anonymizer\Helper\AnonymizerLog;
use Combodo\iTop\Anonymizer\Service\AnonymizerService;
use Combodo\iTop\Anonymizer\Service\CleanupService;
use Combodo\iTop\BackgroundTaskEx\Service\DatabaseService;

/**
 * remove login and deactivate user linked to anonymized person
 *
 */
class ActionCleanupUsers extends AnonymizationTaskAction
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
			'db_table'            => 'priv_anonym_action_cleanup_users',
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
	 * modify iChunkSize (divide by 2) before continuing to clean the data of the anonymized person
	 *
	 * @return void
	 * @throws \ArchivedObjectException
	 * @throws \CoreCannotSaveObjectException
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 */
	public function InitActionParams(): bool
	{
		$oTask = $this->GetTask();

		$aParams['iChunkSize'] = MetaModel::GetConfig()->GetModuleSetting(AnonymizerHelper::MODULE_NAME, 'init_chunk_size', 1000);

		$sId = $oTask->Get('person_id');
		$oService = new AnonymizerService();
		$aParams['aUserIds'] = $oService->GetUserIdListFromContact($sId);
		$aParams['sCurrentUserId'] = reset($aParams['aUserIds']);
		$aParams['first_user'] = true;

		$this->Set('action_params', json_encode($aParams));
		$this->DBWrite();

		return true;
	}

	/**
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
			AnonymizerLog::Debug('Stop retry action ActionCleanupUsers with params '.json_encode($aParams));
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
		if ($this->Get('action_params') == '') {
			return true;
		}

		AnonymizerLog::Enable(AnonymizerLog::DEBUG_FILE);

		$oTask = $this->GetTask();

		$aParams = json_decode($this->Get('action_params'), true);
		$aContext = json_decode($oTask->Get('anonymization_context'), true);

		// Progress until the current user
		$iUserId = false;
		foreach ($aParams['aUserIds'] as $iUserId) {
			if ($iUserId === $aParams['sCurrentUserId']) {
				break;
			}
		}

		while ($iUserId !== false) {
			/** @var \User $oUser */
			$sUserClass = User::class;
			$oUser = MetaModel::GetObject($sUserClass, $iUserId, false, true);
			AnonymizerLog::Debug("Anonymize User ".$oUser->Get('login'));
			$oService = new CleanupService(get_class($oUser), $iUserId, $iEndExecutionTime);
			$oDatabaseService = new DatabaseService(AnonymizerLog::DEBUG_FILE);
			// Disable User, reset login and password
			try {
				$oService->CleanupUser($oUser);
				AnonymizerLog::Debug('Purge History for User '.$oUser->Get('login'));
				if (!$oService->PurgeHistory($aParams['iChunkSize'])) {
					// Timeout stop here
					return false;
				}
			} catch (Exception $e) {
				// Error stop here
				AnonymizerLog::Error("Error during anonymization of $sUserClass::$iUserId - ".$e->getMessage());
				return true;
			}
			// Get all the request set to execute for every user
			$aRequests = $oService->GetCleanupChangesRequests($aContext, $aParams['first_user']);

			if (count($aRequests) === 0) {
				AnonymizerLog::Debug("Unable to clean change requests.");
				return true;
			}

			foreach ($aRequests as $sName => $aRequest) {
				$iProgress = $aParams['aChangesProgress'][$sName] ?? 0;
				$bCompleted = ($iProgress == -1);
				while (!$bCompleted && time() < $iEndExecutionTime) {
					try {
						AnonymizerLog::Debug("ExecuteQueries $sName");
						$fStart = microtime(true);
						$bCompleted = $oDatabaseService->ExecuteQueriesByChunk($aRequest, $iProgress, $aParams['iChunkSize']);
						$fDuration = microtime(true) - $fStart;
						if ($fDuration < AnonymizerHelper::ADAPTATIVE_MIN_TIME) {
							$aParams['iChunkSize'] *= 2;
							if ($aParams['iChunkSize'] > AnonymizerHelper::ADAPTATIVE_MAX_CHUNK_SIZE) {
								$aParams['iChunkSize'] = AnonymizerHelper::ADAPTATIVE_MAX_CHUNK_SIZE;
							}
						} elseif ($fDuration > AnonymizerHelper::ADAPTATIVE_MAX_TIME && $aParams['iChunkSize'] > 1) {
							$aParams['iChunkSize'] /= 2;
						}
						// Save progression
						$aParams['aChangesProgress'][$sName] = $iProgress;
						$this->Set('action_params', json_encode($aParams));
						$this->DBWrite();
					}
					catch (MySQLHasGoneAwayException $e) {
						//in this case retry is possible
						AnonymizerLog::Error('Error MySQLHasGoneAwayException during ActionCleanupUsers with params '.$this->Get('action_params').' with message :'.$e->getMessage().' try again later');

						return false;
					}
					catch (Exception $e) {
						AnonymizerLog::Error('Error during ActionCleanupUsers with params '.$this->Get('action_params').' with message :'.$e->getMessage());
						$aParams['aChangesProgress'][$sName] = -1;
						$bCompleted = true;
					}
					AnonymizerLog::Debug("ExecuteActionWithQueriesByChunk: name: $sName progress: $iProgress completed: $bCompleted");
				}
				if (!$bCompleted) {
					// Timeout
					AnonymizerLog::Debug('Timeout');
					return false;
				}
			}
			$aParams['aChangesProgress'] = [];
			$iUserId = next($aParams['aUserIds']);
			$aParams['first_user'] = false;

			// Save progression
			$aParams['sCurrentUserId'] = $iUserId;
			$this->Set('action_params', json_encode($aParams));
			$this->DBWrite();
		}

		return true;
	}
}