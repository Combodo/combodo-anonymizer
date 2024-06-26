<?php
/*
 * @copyright   Copyright (C) 2010-2024 Combodo SAS
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\Anonymizer\Helper;

use Exception;
use Throwable;

class AnonymizerException extends Exception
{
	public function __construct($message = "", $code = 0, Throwable $previous = null)
	{
		AnonymizerLog::Error($message);
		parent::__construct(AnonymizerHelper::MODULE_NAME.': '.$message, $code, $previous);
	}
}