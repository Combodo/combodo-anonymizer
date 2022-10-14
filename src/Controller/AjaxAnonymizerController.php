<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\Anonymizer\Controller;

use cmdbAbstractObject;
use Combodo\iTop\Anonymizer\Service\AnonymizerService;
use Combodo\iTop\Application\TwigBase\Controller\Controller;
use CoreUnexpectedValue;
use DBSearch;
use Dict;
use utils;

class AjaxAnonymizerController extends Controller
{
	public function OperationAnonymizeOne()
	{
		$aParams = [];
		$sId = utils::ReadParam('id');
		$sClass = utils::ReadParam('class', 'Person');

		$oService = new AnonymizerService();
		$oService->AnonymizeOneObject($sClass, $sId, true);

		cmdbAbstractObject::SetSessionMessage($sClass, $sId, 'anonymization', Dict::S('Anonymization:DoneOnePerson'), 'ok', 1);
		$aParams['sUrl'] = utils::GetAbsoluteUrlAppRoot()."pages/UI.php?operation=details&class=$sClass&id=$sId";

		$this->DisplayAjaxPage($aParams);
	}

	public function OperationAnonymizeList()
	{
		$aParams = [];
		$sFilter = utils::ReadParam('filter', '', false, 'raw_data');
		if (empty($sFilter)) {
			throw new CoreUnexpectedValue('mandatory filter parameter is empty !');
		}

		$oService = new AnonymizerService();
		$oService->AnonymizeObjectList(DBSearch::unserialize($sFilter), true);

		$this->DisplayAjaxPage($aParams);
	}
}