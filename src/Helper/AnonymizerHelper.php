<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\Anonymizer\Helper;

use MetaModel;
use utils;

class AnonymizerHelper
{
	const MODULE_NAME = 'combodo-anonymizer';

	/**
	 * @return void
	 * @throws \ConfigException
	 */
	public static function SaveItopConfiguration()
	{
		$oConfig = MetaModel::GetConfig();
		$sConfigFile = APPROOT.'conf/'.utils::GetCurrentEnvironment().'/config-itop.php';
		@chmod($sConfigFile, 0770); // Allow overwriting the file
		$oConfig->WriteToFile($sConfigFile);
		@chmod($sConfigFile, 0444); // Read-only
	}
}