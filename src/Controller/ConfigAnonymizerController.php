<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\Anonymizer\Controller;

use Combodo\iTop\Anonymizer\Helper\AnonymizerHelper;
use Combodo\iTop\Application\TwigBase\Controller\Controller;
use Dict;
use Exception;
use MetaModel;
use utils;

class ConfigAnonymizerController extends Controller
{
	/**
	 * @return void
	 * @throws \Exception
	 */
	public function OperationDisplayConfig()
	{
		$aParams = $this->GetConfigParameters();
		$aParams['sMessage'] = '';

		$this->AddLinkedScript(utils::GetAbsoluteUrlModulesRoot().AnonymizerHelper::MODULE_NAME.'/assets/js/anonymize.js');
		$this->DisplayPage($aParams);
	}

	/**
	 * @return void
	 * @throws \Exception
	 */
	public function OperationApplyConfig()
	{
		$aParams = [];
		$sTransactionId = utils::ReadPostedParam('transaction_id', '', 'transaction_id');
		if (!utils::IsTransactionValid($sTransactionId)) {
			$aParams['sMessageType'] = 'error';
			$aParams['sMessage'] = Dict::S('UI:Error:ObjectAlreadyUpdated');
		} else {
			$oConfig = MetaModel::GetConfig();
			$sModuleName = AnonymizerHelper::MODULE_NAME;

			$iAnonymizationDelay = (int)utils::ReadPostedParam('anonymization_delay', -1, 'integer');
			if ($iAnonymizationDelay >= 0) {
				$oConfig->SetModuleSetting($sModuleName, 'anonymize_obsolete_persons', true);
				$oConfig->SetModuleSetting($sModuleName, 'obsolete_persons_retention', $iAnonymizationDelay);
			} else {
				// No automatic anonymization
				$oConfig->SetModuleSetting($sModuleName, 'anonymize_obsolete_persons', false);
				$oConfig->SetModuleSetting($sModuleName, 'obsolete_persons_retention', -1);
			}
			$aParams = $this->GetConfigParameters();

			try {
				$oHelper = new AnonymizerHelper();
				$oHelper->SaveItopConfiguration();

				$aParams['$sMessageType'] = 'ok';
				$aParams['$sMessage'] = Dict::S('config-saved');
			}
			catch (Exception $e) {
				$aParams['$sMessageType'] = 'error';
				$aParams['$sMessage'] = $e->getMessage();
			}

		}

		$this->AddLinkedScript(utils::GetAbsoluteUrlModulesRoot().AnonymizerHelper::MODULE_NAME.'/assets/js/anonymize.js');
		$this->DisplayPage($aParams, 'DisplayConfig');
	}

	/**
	 *
	 * @return array
	 * @throws \Exception
	 */
	private function GetConfigParameters(): array
	{
		$sModuleName = AnonymizerHelper::MODULE_NAME;
		$oConfig = MetaModel::GetConfig();
		$aParams = [];
		$aParams['bAnonymizeObsoletePersons'] = $oConfig->GetModuleSetting($sModuleName, 'anonymize_obsolete_persons', 'false') === 'true';
		$aParams['iAnonymizationDelay'] = $oConfig->GetModuleSetting($sModuleName, 'obsolete_persons_retention', -1);
		$aParams['sTransactionId'] = utils::GetNewTransactionId();

		return $aParams;
	}
}