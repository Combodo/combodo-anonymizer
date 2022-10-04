<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\Anonymizer\Service;

use AttributeLinkedSetIndirect;
use CMDBSource;
use Combodo\iTop\Anonymizer\Helper\AnonymizerHelper;
use DBObjectSearch;
use DBObjectSet;
use MetaModel;

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
	 * Helper to remove selected objects without calling any handler
	 * Surpasses BulkDelete as it can handle abstract classes, but has the other limitation as it bypasses standard
	 * objects handlers
	 *
	 * @param \DBSearch $oFilter Scope of objects to wipe out
	 *
	 * @return int The count of deleted objects
	 * @throws \CoreException
	 */
	public function PurgeData($oFilter, $iChunkSize)
	{
		$sTargetClass = $oFilter->GetClass();
		$oSet = new DBObjectSet($oFilter);
		$oSet->SetLimit($iChunkSize);
		$oSet->OptimizeColumnLoad(array($sTargetClass => array('finalclass')));
		$aIdToClass = $oSet->GetColumnAsArray('finalclass');

		$aIds = array_keys($aIdToClass);
		if (count($aIds) > 0)
		{
			$aQuotedIds = CMDBSource::Quote($aIds);
			$sIdList = implode(',', $aQuotedIds);
			$aTargetClasses = array_merge(
				MetaModel::EnumChildClasses($sTargetClass, ENUM_CHILD_CLASSES_ALL),
				MetaModel::EnumParentClasses($sTargetClass)
			);
			foreach($aTargetClasses as $sSomeClass)
			{
				$sTable = MetaModel::DBGetTable($sSomeClass);
				$sPKField = MetaModel::DBGetKey($sSomeClass);

				$sDeleteSQL = "DELETE FROM `$sTable` WHERE `$sPKField` IN ($sIdList)";
				CMDBSource::DeleteFrom($sDeleteSQL);
			}
		}
		return count($aIds);
	}
}