<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\Anonymizer\Test\Service;

use DBObject;

class AnonymizationTestActionFactory extends \Combodo\iTop\Anonymizer\Action\AnonymizationActionFactory
{
	private $aParamsArray = [];
	private $iCurrAction;

	/**
	 * @param array $aParamsArray
	 */
	public function __construct(array $aParamsArray)
	{
		$this->aParamsArray = $aParamsArray;
		$this->iCurrAction = 0;
	}

	public function GetAnonymizationAction($sAction, DBObject $oAction, $iEndExecutionTime)
	{
		$oAction = parent::GetAnonymizationAction($sAction, $oAction, $iEndExecutionTime);
		if ($oAction && method_exists($oAction, 'SetParams')) {
			$oAction->SetParams($this->aParamsArray[$this->iCurrAction]);
			if (isset($this->aParamsArray[$this->iCurrAction+1])) {
				$this->iCurrAction++;
			}
		}
		return $oAction;
	}

}