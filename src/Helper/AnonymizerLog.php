<?php
/*
 * @copyright   Copyright (C) 2010-2024 Combodo SAS
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\Anonymizer\Helper;

use IssueLog;
use LogAPI;

class AnonymizerLog extends LogAPI
{
	const CHANNEL_DEFAULT = 'AnonymizerLog';
	const DEBUG_FILE = APPROOT.'log/anonymizer.log';

	protected static $m_oFileLog = null;

	public static function Error($sMessage, $sChannel = null, $aContext = array())
	{
		parent::Debug($sMessage, $sChannel, $aContext);
		IssueLog::Error("ERROR: $sMessage", self::CHANNEL_DEFAULT, $aContext);
	}

	public static function Info($sMessage, $sChannel = null, $aContext = array())
	{
		parent::Debug($sMessage, $sChannel, $aContext);
		IssueLog::Info($sMessage, self::CHANNEL_DEFAULT, $aContext);
	}

	public static function Warning($sMessage, $sChannel = null, $aContext = array())
	{
		parent::Debug($sMessage, $sChannel, $aContext);
		IssueLog::Warning($sMessage, self::CHANNEL_DEFAULT, $aContext);
	}
}
