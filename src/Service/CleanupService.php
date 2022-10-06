<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\Anonymizer\Service;

use AttributeLinkedSetIndirect;
use CMDBSource;
use Combodo\iTop\Anonymizer\Helper\AnonymizerHelper;
use Combodo\iTop\Anonymizer\Helper\limit;
use Combodo\iTop\Anonymizer\Helper\MySQLException;
use Combodo\iTop\Anonymizer\Helper\primary;
use Combodo\iTop\Anonymizer\Helper\start;
use DBObjectSearch;
use DBObjectSet;
use MetaModel;
use ormPassword;
use User;

class CleanupService
{
	private $sClass;
	private $sId;
	private $iProcessEndTime;
	private $iMaxChunkSize;

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
		$this->iMaxChunkSize = MetaModel::GetConfig()->GetModuleParameter(AnonymizerHelper::MODULE_NAME, 'max_chunk_size', 1000);
	}

	/**
	 * @param $sSqlSearch
	 * @param $aSqlUpdate array to update elements found by $sSqlSearch, don't specify the where close
	 * @param $sKey primary key of updated table
	 * @param $sIdMin start the search at this value
	 * @param $iMaxChunkSize limit size of processed data
	 * Search objects to update and execute update by lot of  max_chunk_size elements
	 * return true if all objects where updated, false if the function don't have the time to finish
	 *
	 * @return int max id processed if finish or no data found, return -1
	 * @throws \CoreException
	 * @throws \MySQLException
	 * @throws \MySQLHasGoneAwayException
	 */
	public function ExecuteActionWithQueriesByChunk($sSqlSearch, $aSqlUpdate, $sKey, $sIdMin, $iMaxChunkSize)
	{
		$sSQL = $sSqlSearch." AND $sKey > $sIdMin ORDER BY $sKey LIMIT ".$iMaxChunkSize;
		$oResult = CMDBSource::Query($sSQL);

		$aObjects = [];
		$sId = -1;
		if ($oResult->num_rows > 0) {
			while ($oRaw = $oResult->fetch_assoc()) {
				$sId = $oRaw[$sKey];
				$aObjects[] = $sId;
			}
			foreach ($aSqlUpdate as $sSqlUpdate) {
				$sSQL = $sSqlUpdate." WHERE `$sKey` IN (".implode(', ', $aObjects).");";
				CMDBSource::Query($sSQL);
			}
		}
		if (count($aObjects) < $iMaxChunkSize) {
			return -1;
		}

		return $sId;
	}


	public function PurgeLinks()
	{
		$oPerson = MetaModel::GetObject($this->sClass, $this->sId);
		// Cleanup all non mandatory values //end of job
		foreach (MetaModel::ListAttributeDefs($this->sClass) as $sAttCode => $oAttDef) {
			if (!$oAttDef->IsWritable()) {
				continue;
			}

			if ($oAttDef instanceof AttributeLinkedSetIndirect) {
				$oValue = DBObjectSet::FromScratch($oAttDef->GetLinkedClass());
				$oPerson->Set($sAttCode, $oValue);
			}
		}
		$oPerson->DBWrite();
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
	 *
	 * @return int The count of deleted objects
	 * @throws \CoreException
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
			foreach ($aTargetClasses as $sSomeClass) {
				$sTable = MetaModel::DBGetTable($sSomeClass);
				$sPKField = MetaModel::DBGetKey($sSomeClass);

				$sDeleteSQL = "DELETE FROM `$sTable` WHERE `$sPKField` IN ($sIdList)";
				CMDBSource::DeleteFrom($sDeleteSQL);
			}
		}

		return count($aIds);
	}

}