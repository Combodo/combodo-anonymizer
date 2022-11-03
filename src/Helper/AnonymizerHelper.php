<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\Anonymizer\Helper;

use Config;
use UserRights;
use utils;

class AnonymizerHelper
{
	const MODULE_NAME = 'combodo-anonymizer';
	const MENU_ID = 'ConfigAnonymizer';
	const ADAPTATIVE_MIN_TIME = 10.0;
	const ADAPTATIVE_MAX_TIME = 60.0;
	const ADAPTATIVE_MAX_CHUNK_SIZE = 100000000;
	const ACTION_LIST = [
		'ActionResetPersonFields',
        'ActionAnonymizePerson',
        'ActionCleanupCaseLogs',
        'ActionCleanupOnMention',
        'ActionCleanupEmailNotification',
        'ActionCleanupUsers',
        'ActionPurgePersonHistory',
        'ActionEndOfAnonymization',
	];

	public function CanAnonymize()
	{
		return (UserRights::IsAdministrator() || UserRights::IsActionAllowed('RessourceAnonymization', UR_ACTION_MODIFY));
	}

	/**
	 * @param \Config $oConfig
	 *
	 * @return void
	 * @throws \ConfigException
	 */
	public function SaveItopConfiguration(Config $oConfig)
	{
		$sConfigFile = APPROOT.'conf/'.utils::GetCurrentEnvironment().'/config-itop.php';
		@chmod($sConfigFile, 0770); // Allow overwriting the file
		$oConfig->WriteToFile($sConfigFile);
		@chmod($sConfigFile, 0444); // Read-only
	}
}