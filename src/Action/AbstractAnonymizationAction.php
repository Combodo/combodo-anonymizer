<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\Anonymizer\Action;

use DBObject;

abstract class AbstractAnonymizationAction implements iAnonymizationAction
{
	protected $oTask;
	protected $iEndExecutionTime;

	public function __construct(DBObject $oTask, $iEndExecutionTime)
	{
		$this->oTask = $oTask;
		$this->iEndExecutionTime = $iEndExecutionTime;
	}

	/**
	 * @inheritDoc
	 */
	public function Init()
	{
	}

	/**
	 * @inheritDoc
	 */
	public function Retry()
	{
	}
}