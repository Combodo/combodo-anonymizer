<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\Anonymizer\Controller;

use cmdbAbstractObject;
use Combodo\iTop\Anonymizer\Helper\AnonymizerHelper;
use Combodo\iTop\Anonymizer\Service\AnonymizerService;
use Combodo\iTop\Application\TwigBase\Controller\Controller;
use CoreUnexpectedValue;
use Dict;
use MetaModel;
use utils;

class AjaxAnonymizerController extends Controller
{
	public function OperationAnonymizeOne()
	{
		$aParams = [];
		$sId = utils::ReadParam('id');
		$sClass = utils::ReadParam('class', 'Person');

		$iMaxExecutionTime = MetaModel::GetConfig()->GetModuleParameter(AnonymizerHelper::MODULE_NAME, 'max_interactive_anonymization_time_in_s', 30);
		$oService = new AnonymizerService($iMaxExecutionTime);
		$oService->AnonymizeOneObject($sClass, $sId);

		cmdbAbstractObject::SetSessionMessage($sClass, $sId, 'anonymization', Dict::S('Anonymization:DoneOnePerson'), 'ok', 1);
		$aParams['sURL'] = utils::GetAbsoluteUrlAppRoot()."pages/UI.php?operation=details&class=$sClass&id=$sId";
		$this->DisplayAjaxPage($aParams);
	}

	public function OperationAnonymizeList()
	{
		$aParams = [];
		$sFilter = utils::ReadParam('filter', '', false, 'raw_data');
		if (empty($sFilter)) {
			throw new CoreUnexpectedValue('mandatory filter parameter is empty !');
		}

		$iMaxExecutionTime = MetaModel::GetConfig()->GetModuleParameter(AnonymizerHelper::MODULE_NAME, 'max_interactive_anonymization_time_in_s', 30);
		$oService = new AnonymizerService($iMaxExecutionTime);
		$oService->AnonymizeObjectList($sFilter);

		$this->DisplayAjaxPage($aParams);
	}
}