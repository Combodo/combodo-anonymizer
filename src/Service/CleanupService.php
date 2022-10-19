<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\Anonymizer\Service;

use AttributeLinkedSetIndirect;
use CMDBObject;
use CMDBSource;
use Combodo\iTop\Anonymizer\Helper\AnonymizerLog;
use Combodo\iTop\BackgroundTaskEx\Service\DatabaseService;
use DBObjectSearch;
use DBObjectSet;
use Exception;
use MetaModel;
use ormPassword;
use Person;
use User;

class CleanupService
{
	private $sClass;
	private $sId;
	private $iProcessEndTime;

	/**
	 * @param $sClass
	 * @param $sId
	 * @param $iProcessEndTime
	 */
	public function __construct($sClass, $sId, $iProcessEndTime)
	{
		AnonymizerLog::Enable(APPROOT.'log/error.log');
		$this->sClass = $sClass;
		$this->sId = $sId;
		$this->iProcessEndTime = $iProcessEndTime;
	}

	/**
	 * Purge object history
	 *
	 * @param $iChunkSize
	 *
	 * @return bool true if finished
	 * @throws \ArchivedObjectException
	 * @throws \CoreCannotSaveObjectException
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 * @throws \CoreWarning
	 * @throws \MySQLException
	 * @throws \OQLException
	 */
	public function PurgeHistory($iChunkSize): bool
	{
		// TODO Replace with a call to DatabaseService::ExecuteSQLQueriesByChunk()
		// Delete any existing change tracking about the current object
		$oFilter = new DBObjectSearch('CMDBChangeOp');
		$oFilter->AddCondition('objclass', $this->sClass, '=');
		$oFilter->AddCondition('objkey', $this->sId, '=');

		$iDeleted = 1;
		while ($iDeleted > 0 && time() < $this->iProcessEndTime) {
			$iDeleted = $this->PurgeData($oFilter, $iChunkSize);
		}

		if ($iDeleted == 0) {
			$oMyChangeOp = MetaModel::NewObject('CMDBChangeOpPlugin');
			$oMyChangeOp->Set('objclass', $this->sClass);
			$oMyChangeOp->Set('objkey', $this->sId);
			$oMyChangeOp->Set('description', 'Anonymization');
			$oMyChangeOp->DBInsertNoReload();

			return true;
		}

		return false;
	}

	/**
	 * Cleanup all non-mandatory values //end of job
	 *
	 * @return bool
	 * @throws \ArchivedObjectException
	 * @throws \CoreCannotSaveObjectException
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 * @throws \Exception
	 */
	public function ResetObjectFields(): bool
	{
		/** @var \cmdbAbstractObject $oObject */
		$oObject = MetaModel::GetObject($this->sClass, $this->sId);
		foreach (MetaModel::ListAttributeDefs($this->sClass) as $sAttCode => $oAttDef) {
			if (!$oAttDef->IsWritable()) {
				continue;
			}

			if ($oAttDef->IsScalar()) {
				if (!$oAttDef->IsNullAllowed()) {
					// Try to put the default value is a suitable one exists
					$value = $oAttDef->GetDefaultValue($oObject);
					if (!$oAttDef->IsNull($value)) {
						$oObject->Set($sAttCode, $value);
					}
				} else {
					$oObject->Set($sAttCode, null);
				}
			} elseif ($oAttDef instanceof AttributeLinkedSetIndirect) {
//				$oValue = new DBObjectSet(new DBObjectSearch($oAttDef->GetLinkedClass()));
//				$oObject->Set($sAttCode, $oValue);
			}
		}
		$oObject->AllowWrite();
		$fStart = microtime(true);
		$oObject->DBWrite();
		$fDuration = microtime(true) - $fStart;
		AnonymizerLog::Debug(sprintf("ResetObjectFields duration %.2f", $fDuration));
		CMDBObject::SetCurrentChangeFromParams(null);
		CMDBObject::SetCurrentChange(null);

		return true;
	}

	/**
	 * @param \User $oUser
	 *
	 * @throws \ArchivedObjectException
	 * @throws \CoreCannotSaveObjectException
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 * @throws \Exception
	 */
	public function CleanupUser(User $oUser)
	{
		$oUser->Set('status', 'disabled');
		$iContactId = $oUser->Get('contactid');
		$oUser->Set('login', 'Anonymous-'.$iContactId.'-'.$this->sId);

		if (MetaModel::IsValidAttCode(get_class($oUser), 'password')) {
			$rawToken = random_bytes(32);
			$sToken = bin2hex($rawToken);
			$oPassword = new ormPassword();
			$oPassword->SetPassword($sToken);
			$oUser->Set('password', $oPassword);
		}
		$oUser->AllowWrite();
		$oUser->DBWrite();
	}

	/**
	 * Helper to remove selected objects without calling any handler
	 * Surpasses BulkDelete as it can handle abstract classes, but has the other limitation as it bypasses standard
	 * objects handlers
	 *
	 * @param \DBSearch $oFilter Scope of objects to wipe out
	 * @param $iChunkSize
	 *
	 * @return int The count of deleted objects
	 * @throws \CoreException
	 * @throws \MySQLException
	 * @throws \MySQLHasGoneAwayException
	 */
	protected function PurgeData($oFilter, $iChunkSize)
	{
		$sTargetClass = $oFilter->GetClass();
		$oSet = new DBObjectSet($oFilter);
		$oSet->SetLimit($iChunkSize);
		$oSet->OptimizeColumnLoad(array($sTargetClass => array('finalclass')));
		$aIdToClass = $oSet->GetColumnAsArray('finalclass');

		$aIds = array_keys($aIdToClass);
		if (count($aIds) > 0) {
			$aQuotedIds = CMDBSource::Quote($aIds);
			$sIdList = implode(',', $aQuotedIds);
			$aTargetClasses = array_merge(
				MetaModel::EnumChildClasses($sTargetClass, ENUM_CHILD_CLASSES_ALL),
				MetaModel::EnumParentClasses($sTargetClass)
			);
			CMDBSource::Query('START TRANSACTION');
			try {
				foreach ($aTargetClasses as $sSomeClass) {
					$sTable = MetaModel::DBGetTable($sSomeClass);
					$sPKField = MetaModel::DBGetKey($sSomeClass);

					$sDeleteSQL = "DELETE FROM `$sTable` WHERE `$sPKField` IN ($sIdList)";
					CMDBSource::DeleteFrom($sDeleteSQL);
				}
				CMDBSource::Query('COMMIT');
			}
			catch (Exception $e) {
				CMDBSource::Query('ROLLBACK');
				throw $e;
			}
		}

		return count($aIds);
	}

	public function AnonymizePerson(Person $oPerson)
	{
		$oService = new AnonymizerService();
		$aAnonymizedFields = $oService->GetAnonymizedFields($oPerson->GetKey());
		$oPerson->Set('name', $aAnonymizedFields['name']);
		$oPerson->Set('first_name', $aAnonymizedFields['first_name']);
		$oPerson->Set('email', $aAnonymizedFields['email']);
		// Mark the contact as obsolete
		$oPerson->Set('status', 'inactive');
		// Remove picture
		$oPerson->Set('picture', null);
	}

	/**
	 * Cleanup all references to the Person's name as an author in changes
	 *
	 * @param $aContext
	 *
	 * @return array
	 * @throws \CoreException
	 */
	public function GetCleanupChangesRequests($aContext, $bFirstUser)
	{
		$sChangeTable = MetaModel::DBGetTable('CMDBChange');
		$sKey = MetaModel::DBGetKey('CMDBChange');
		if (isset($aContext['origin']['date_create'])) {
			$sDateCreateCondition = " AND date >= '{$aContext['origin']['date_create']}'";
		} else {
			$sDateCreateCondition = "";
		}
		$sOrigFriendlyname = $aContext['origin']['friendlyname'];
		$sTargetFriendlyname = $aContext['anonymized']['friendlyname'];

		$aRequests = [];
		$oDatabaseService = new DatabaseService();

		if (MetaModel::IsValidAttCode('CMDBChange', 'user_id')) {
			$iMaxId = $oDatabaseService->QueryMaxKey($sKey, $sChangeTable);
			if ($bFirstUser) {
				$aRequests['req1'] = [
					'search_key'    => $sKey,
					'key'           => $sKey,
					'search_max_id' => $iMaxId,
					'search_query'  => "SELECT `$sKey` from `$sChangeTable` WHERE userinfo=".CMDBSource::Quote($sOrigFriendlyname).' AND user_id IS NULL'.$sDateCreateCondition,
					'apply_queries' => [$sChangeTable => "UPDATE `$sChangeTable` /*JOIN*/ SET userinfo=".CMDBSource::Quote($sTargetFriendlyname)],
				];
				$aRequests['req2'] = [
					'search_key'    => $sKey,
					'key'           => $sKey,
					'search_max_id' => $iMaxId,
					'search_query'  => "SELECT `$sKey` from `$sChangeTable` WHERE userinfo=".CMDBSource::Quote($sOrigFriendlyname.' (CSV)').' AND user_id IS NULL'.$sDateCreateCondition,
					'apply_queries' => [$sChangeTable => "UPDATE `$sChangeTable` /*JOIN*/ SET userinfo=".CMDBSource::Quote($sTargetFriendlyname.' (CSV)')],
				];
			}
			$aRequests['req3'] = [
				'search_key'    => $sKey,
				'key'           => $sKey,
				'search_max_id' => $iMaxId,
				'search_query'  => "SELECT `$sKey` from `$sChangeTable` WHERE user_id in (".$this->sId.')',
				'apply_queries' => [$sChangeTable => "UPDATE `$sChangeTable` /*JOIN*/ SET userinfo=".CMDBSource::Quote($sTargetFriendlyname)],
			];
		} elseif ($bFirstUser) {
			$iMaxId = $oDatabaseService->QueryMaxKey($sKey, $sChangeTable);
			$aRequests['req1'] = [
				'search_key'    => $sKey,
				'key'           => $sKey,
				'search_max_id' => $iMaxId,
				'search_query'  => "SELECT `$sKey` from `$sChangeTable` WHERE userinfo=".CMDBSource::Quote($sOrigFriendlyname).$sDateCreateCondition,
				'apply_queries' => [$sChangeTable => "UPDATE `$sChangeTable` /*JOIN*/ SET userinfo=".CMDBSource::Quote($sTargetFriendlyname)],
			];
			$aRequests['req2'] = [
				'search_key'    => $sKey,
				'key'           => $sKey,
				'search_max_id' => $iMaxId,
				'search_query'  => "SELECT `$sKey` from `$sChangeTable` WHERE userinfo=".CMDBSource::Quote($sOrigFriendlyname.' (CSV)').$sDateCreateCondition,
				'apply_queries' => [$sChangeTable => "UPDATE `$sChangeTable` /*JOIN*/ SET userinfo=".CMDBSource::Quote($sTargetFriendlyname.' (CSV)')],
			];
		}

		return $aRequests;
	}
}