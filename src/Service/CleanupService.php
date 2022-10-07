<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\Anonymizer\Service;

use AttributeLinkedSetIndirect;
use CMDBObject;
use CMDBSource;
use Combodo\iTop\Anonymizer\Helper\AnonymizerException;
use Combodo\iTop\Anonymizer\Helper\AnonymizerLog;
use DBObjectSearch;
use DBObjectSet;
use Exception;
use MetaModel;
use MySQLHasGoneAwayException;
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
		$this->sClass = $sClass;
		$this->sId = $sId;
		$this->iProcessEndTime = $iProcessEndTime;
	}

	/**
	 * @param string $sSqlSearch
	 * @param array $aSqlUpdate array to update elements found by $sSqlSearch, don't specify the where close
	 * @param string $sKey primary key of updated table
	 * @param string $sProgressId start the search at this value => updated with the last id computed
	 * @param int $iMaxChunkSize limit size of processed data
	 * Search objects to update and execute update by lot of  max_chunk_size elements
	 * return true if all objects where updated, false if the function don't have the time to finish
	 *
	 * @return bool  true if completed
	 * @throws \CoreException
	 * @throws \MySQLException
	 * @throws \MySQLHasGoneAwayException
	 * @throws \Combodo\iTop\Anonymizer\Helper\AnonymizerException
	 */
	public function ExecuteActionWithQueriesByChunk($sSqlSearch, $aSqlUpdate, $sKey, &$sProgressId, $iMaxChunkSize): bool
	{
		$sId = $sProgressId;
		$sSQL = $sSqlSearch." AND $sKey > $sProgressId ORDER BY $sKey LIMIT ".$iMaxChunkSize;
		AnonymizerLog::Debug($sSQL);
		$oResult = CMDBSource::Query($sSQL);

		$aObjects = [];
		if ($oResult->num_rows > 0) {
			while ($oRaw = $oResult->fetch_assoc()) {
				$sId = $oRaw[$sKey];
				$aObjects[] = $sId;
			}
			CMDBSource::Query('START TRANSACTION');
			try {
				foreach ($aSqlUpdate as $sSqlUpdate) {
					$sSQL = $sSqlUpdate." WHERE `$sKey` IN (".implode(', ', $aObjects).");";
					AnonymizerLog::Debug($sSQL);
					CMDBSource::Query($sSQL);
				}
				CMDBSource::Query('COMMIT');
				// Save progression
				$sProgressId = $sId + 1;
			}
			catch (MySQLHasGoneAwayException $e) {
				// Allow to retry the same set
				CMDBSource::Query('ROLLBACK');
				if ($iMaxChunkSize == 1) {
					// This is hopeless for this entry
					throw new AnonymizerException($e->getMessage());
				}
				throw $e;
			}
			catch (Exception $e) {
				CMDBSource::Query('ROLLBACK');
				if ($iMaxChunkSize == 1) {
					// Ignore current entries and skip to the next ones
					$sProgressId = $sId + 1;
					AnonymizerLog::Error($e->getMessage());

					return false;
				}

				// Try with a reduced set in order to find the entries in error
				throw $e;
			}
			if (count($aObjects) < $iMaxChunkSize) {
				// completed
				$sProgressId = -1;

				return true;
			}
		} else {
			$sProgressId = -1;

			return true;
		}

		// not completed yet
		return false;
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
				$oValue = DBObjectSet::FromScratch($oAttDef->GetLinkedClass());
				$oObject->Set($sAttCode, $oValue);
			}
		}
		$oObject->AllowWrite();
		$oObject->DBWrite();
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
	public function GetCleanupChangesRequests($aContext)
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

		if (MetaModel::IsValidAttCode('CMDBChange', 'user_id')) {
			$aRequests['req1'] = [
				'key'     => $sKey,
				'select'  => "SELECT `$sKey` from `$sChangeTable` WHERE userinfo=".CMDBSource::Quote($sOrigFriendlyname).' AND user_id IS NULL'.$sDateCreateCondition,
				'updates' => ["UPDATE `$sChangeTable` SET userinfo=".CMDBSource::Quote($sTargetFriendlyname)],
			];
			$aRequests['req2'] = [
				'key'     => $sKey,
				'select'  => "SELECT `$sKey` from `$sChangeTable` WHERE userinfo=".CMDBSource::Quote($sOrigFriendlyname.' (CSV)').' AND user_id IS NULL'.$sDateCreateCondition,
				'updates' => ["UPDATE `$sChangeTable` SET userinfo=".CMDBSource::Quote($sTargetFriendlyname.' (CSV)')],
			];
			$aRequests['req3'] = [
				'key'     => $sKey,
				'select'  => "SELECT `$sKey` from `$sChangeTable` WHERE user_id in (".$this->sId.')',
				'updates' => ["UPDATE `$sChangeTable` SET userinfo=".CMDBSource::Quote($sTargetFriendlyname)],
			];
		} else {
			$aRequests['req1'] = [
				'key'     => $sKey,
				'select'  => "SELECT `$sKey` from `$sChangeTable` WHERE userinfo=".CMDBSource::Quote($sOrigFriendlyname).$sDateCreateCondition,
				'updates' => ["UPDATE `$sChangeTable` SET userinfo=".CMDBSource::Quote($sTargetFriendlyname)],
			];
			$aRequests['req2'] = [
				'key'     => $sKey,
				'select'  => "SELECT `$sKey` from `$sChangeTable` WHERE userinfo=".CMDBSource::Quote($sOrigFriendlyname.' (CSV)').$sDateCreateCondition,
				'updates' => ["UPDATE `$sChangeTable` SET userinfo=".CMDBSource::Quote($sTargetFriendlyname.' (CSV)')],
			];
		}

		return $aRequests;
	}
}