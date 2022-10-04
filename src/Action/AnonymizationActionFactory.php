<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\Anonymizer\Action;

use Combodo\iTop\Anonymizer\Helper\AnonymizerException;
use DBObject;

class AnonymizationActionFactory
{

	/**
	 * @param string|null $sAction class of the action to create
	 * @param \DBObject $oAction
	 * @param int $iEndExecutionTime
	 *
	 * @return \Combodo\iTop\Anonymizer\Action\iAnonymizationAction|null
	 * @throws \Combodo\iTop\Anonymizer\Helper\AnonymizerException
	 */
	public function GetAnonymizationAction($sAction, DBObject $oAction, int $iEndExecutionTime)
	{
		if (is_null($sAction)) {
			return null;
		}
		if (class_exists($sAction) && isset(class_implements($sAction)[iAnonymizationAction::class])) {
			return new $sAction($oAction, $iEndExecutionTime);
		}
		throw new AnonymizerException("Class $sAction is not an anonymization class");
	}
}