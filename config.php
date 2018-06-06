<?php
/**
 * Interactive edition of the configuration of the Anonymizer module
 *
 * @copyright   Copyright (C) 2018 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

require_once(APPROOT.'application/application.inc.php');
require_once(APPROOT.'application/itopwebpage.class.inc.php');
require_once(APPROOT.'application/startup.inc.php');
require_once(APPROOT.'application/loginwebpage.class.inc.php');

/////////////////////////////////////////////////////////////////////

/**
 * Display the form to edit the configuration
 * @param WebPage $oP
 * @param Config $oConfig
 * @return void
 */
function DisplayConfigurationForm(WebPage $oP, Config $oConfig)
{
	$sModuleName = basename(__DIR__);
	
	$bCleanupNodifications = (bool) $oConfig->GetModuleSetting($sModuleName, 'cleanup_notifications', false);
	$iNotificationsPurgeDelay = $oConfig->GetModuleSetting($sModuleName, 'notifications_retention', -1);
	$bAnonymizeObsoletePersons = $oConfig->GetModuleSetting($sModuleName, 'anonymize_obsolete_persons', false);
	$iAnonymizationDelay = $oConfig->GetModuleSetting($sModuleName, 'obsolete_persons_retention', -1);
		
	$oP->add('<form method="post">');
	$oP->add('<input type="hidden" name="operation" value="apply">');
	$oP->add('<input type="hidden" name="transaction_id" value="'.utils::GetNewTransactionId().'">');
	
	$oP->add('<fieldset><legend>'.Dict::S('Anonymization:AutomationParameters').'</legend>');
	$sChecked = ($bAnonymizeObsoletePersons && ($iAnonymizationDelay >= 0)) ? 'checked' : '';
	$sDelay = ($bAnonymizeObsoletePersons && ($iAnonymizationDelay >= 0)) ? $iAnonymizationDelay : '';
	$sLabel = Dict::Format('Anonymization:AnonymizationDelay_Input', '<input id="anonymization_delay" type="text" size="4" name="anonymization_delay" value="'.$sDelay.'">');
	$oP->p('<input type="checkbox" '.$sChecked.' id="checkbox_anonymize" name=""><label for="checkbox_anonymize">&nbsp;'.$sLabel.'</label>');
	$oP->add('</fieldset>');
	
	$oP->add('<fieldset><legend>'.Dict::S('Anonymization:NotificationsPurgeParameters').'</legend>');
	$sChecked = ($bCleanupNodifications && ($iNotificationsPurgeDelay >= 0)) ? 'checked' : '';
	$sDelay = ($bCleanupNodifications && ($iNotificationsPurgeDelay >= 0)) ? $iNotificationsPurgeDelay : '';
	$sLabel = Dict::Format('Anonymization:PurgeDelay_Input', '<input id="notifications_purge_delay" type="text" size="4" name="notifications_purge_delay" value="'.$sDelay.'">');
	$oP->p('<input type="checkbox" '.$sChecked.' id="checkbox_purge" name=""><label for="checkbox_purge">&nbsp;'.$sLabel.'</label>');
	$oP->add('</fieldset>');
	$oP->p('<button id="btn_cancel">'.Dict::S('UI:Button:Cancel').'</button> <button id="btn_apply" type="submit">'.Dict::S('UI:Button:Apply').'</button>');
	$oP->add('</form>');
	
	$sJSUrl = utils::GetAbsoluteUrlModulesRoot().basename(__DIR__).'/js/anonymize.js';
	$oP->add_linked_script($sJSUrl);
	$oP->add_ready_script('AnonymizationUpdateFormButtons(); $("#checkbox_anonymize, #checkbox_purge").on("click", function() { AnonymizationUpdateFormButtons(); });');
}

/**
 * Read the form parameters and update the configuration file accordingly
 * @param WebPage $oP
 * @param Config $oConfig
 * @return void
 */
function ApplyConfiguration(WebPage $oP, Config $oConfig)
{
	$sModuleName = basename(__DIR__);
	$sTransactionId = utils::ReadPostedParam('transaction_id', '');
	if (!utils::IsTransactionValid($sTransactionId, false))
	{
		$oP->p('<div id="save_result" class="header_message message_error">'.Dict::S('UI:Error:ObjectAlreadyUpdated').'</div>');
	}
	else
	{
		$iNotificationsPurgeDelay = (int)utils::ReadPostedParam('notifications_purge_delay', -1, 'integer');
		if ($iNotificationsPurgeDelay >= 0)
		{
			$oConfig->SetModuleSetting($sModuleName, 'cleanup_notifications', true);
			$oConfig->SetModuleSetting($sModuleName, 'notifications_retention', $iNotificationsPurgeDelay);
		}
		else
		{
			// No automatic purge of notifications
			$oConfig->SetModuleSetting($sModuleName, 'cleanup_notifications', false);
			$oConfig->SetModuleSetting($sModuleName, 'notifications_retention', -1);
		}
		
		$iAnonymizationDelay = (int)utils::ReadPostedParam('anonymization_delay', -1, 'integer');
		if ($iAnonymizationDelay >= 0)
		{
			$oConfig->SetModuleSetting($sModuleName, 'anonymize_obsolete_persons', true);
			$oConfig->SetModuleSetting($sModuleName, 'obsolete_persons_retention', $iAnonymizationDelay);
		}
		else
		{
			// No automatic anonymization
			$oConfig->SetModuleSetting($sModuleName, 'anonymize_obsolete_persons', false);
			$oConfig->SetModuleSetting($sModuleName, 'obsolete_persons_retention', -1);
		}
		
		try
		{
			$sConfigFile = APPROOT.'conf/'.utils::GetCurrentEnvironment().'/config-itop.php';
			@chmod($sConfigFile, 0770); // Allow overwriting the file
			$oConfig->WriteToFile($sConfigFile);
			@chmod($sConfigFile, 0444); // Read-only
			
			$oP->p('<div id="save_result" class="header_message message_ok">'.Dict::S('config-saved').'</div>');
		}
		catch(Exception $e)
		{
			$oP->p('<div id="save_result" class="header_message message_error">'.$e->getMessage().'</div>');
		}
	}
		
}

/////////////////////////////////////////////////////////////////////
// Main program
//
if (MetaModel::IsValidClass('ResourceAdminMenu'))
{
	// Since iTop 2.5, access to the configuration can be granted to non-administrators
	LoginWebPage::DoLogin(); // Check user rights and prompt if needed
	ApplicationMenu::CheckMenuIdEnabled('ConfigAnonymizer');
}
else
{
	// Prior to iTop 2.5, acces is only for administrators
	LoginWebPage::DoLogin(true); // Check user rights and prompt if needed (must be admin)
}

$oP = new iTopWebPage(Dict::S('Anonymization:Configuration'));
$oP->set_base(utils::GetAbsoluteUrlAppRoot().'pages/');

try
{
	$sOperation = utils::ReadParam('operation', '');
	
	$oP->add("<h1>".Dict::S('Anonymization:Configuration')."</h1>");
	
	if (MetaModel::GetConfig()->Get('demo_mode'))
	{
		$oP->add("<div class=\"header_message message_info\">Sorry, iTop is in <b>demonstration mode</b>: the configuration cannot be edited.</div>");
	}
	else
	{
		$oConfig = MetaModel::GetConfig();
		switch($sOperation)
		{
			case 'apply':
			ApplyConfiguration($oP, $oConfig);
			DisplayConfigurationForm($oP, $oConfig);
			break;
			
			default:
			DisplayConfigurationForm($oP, $oConfig);
		}
	}
}
catch(Exception $e)
{
	$oP->p('<b>'.$e->getMessage().'</b>');
}

$oP->output();

		