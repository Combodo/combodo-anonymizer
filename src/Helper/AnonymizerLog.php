<?php
/*
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\Anonymizer\Helper;

use LogAPI;

class AnonymizerLog extends LogAPI
{
	const CHANNEL_DEFAULT = 'AnonymizerLog';

	protected static $m_oFileLog = null;
}
