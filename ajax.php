<?php
/**
 * Copyright (C) 2013-2020 Combodo SARL
 *
 * This file is part of iTop.
 *
 * iTop is free software; you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * iTop is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 */

require_once('../../approot.inc.php');
require_once(APPROOT.'application/utils.inc.php');

try
{
	require_once(APPROOT.'/application/application.inc.php');
	require_once(APPROOT.'/application/startup.inc.php');
//remove require itopdesignformat at the same time as version_compare(ITOP_DESIGN_LATEST_VERSION , '3.0') < 0
	if (! defined("ITOP_DESIGN_LATEST_VERSION")) {
		require_once APPROOT.'setup/itopdesignformat.class.inc.php';
	}
	if (version_compare(ITOP_DESIGN_LATEST_VERSION, '3.0') < 0) {
		require_once(APPROOT.'/application/webpage.class.inc.php');
		require_once(APPROOT.'/application/ajaxwebpage.class.inc.php');
	}
	require_once(APPROOT.'/application/loginwebpage.class.inc.php');
	
	LoginWebPage::DoLoginEx(null /* any portal */, false /* must be admin */);
	if(!AnonymizationUtils::CanAnonymize()){
	   	throw new SecurityException(Dict::Format('UI:Error:ActionNotAllowed' ));
	}

	if (version_compare(ITOP_DESIGN_LATEST_VERSION , '3.0') < 0) {
		$oP = new ajax_page('');
	} else {
		$oP = new AjaxPage('');
	}
	
	$sOperation = utils::ReadParam('operation');
	
	switch($sOperation)
	{
		// Anonymize one Person
		case 'anonymize_one':
		$iContact = utils::ReadParam('id');
		/**
		 * @var Person $oPerson
		 */
		$oPerson = MetaModel::GetObject('Person', $iContact);
		CMDBSource::Query('START TRANSACTION');
		$oPerson->Anonymize();
		CMDBSource::Query('COMMIT');

		//run one time background process.
		$oSearch = DBObjectSearch::FromOQL("SELECT BackgroundTask WHERE class_name = 'PersonalDataAnonymizer'");
		$oSet = new DBObjectSet($oSearch);
		while($oTask = $oSet->Fetch())
		{
			$oPlannedStart = new DateTime();
			$oTask->Set('next_run_date', $oPlannedStart->format('Y-m-d H:i:s'));
			$oTask->DBUpdate();
		}

		$sUrl = utils::GetAbsoluteUrlAppRoot().'pages/UI.php?operation=details&class='.get_class($oPerson).'&id='.$iContact;
		cmdbAbstractObject::SetSessionMessage(get_class($oPerson), $oPerson->GetKey(), 'anonymization', Dict::S('Anonymization:DoneOnePerson'), 'ok', 1);
		$oP->add_ready_script("window.location.href='$sUrl'");
		break;
		
		// Anonymize a complete list
		case 'anonymize_list':
		$sFilter = utils::ReadParam('filter', "", false, 'raw_data');
		if (empty($sFilter))
		{
			throw new CoreUnexpectedValue('mandatory filter parameter is empty !');
		}
		$oSearch = DBSearch::unserialize($sFilter);
		$oSet = new DBObjectSet($oSearch);
		
		$iPreviousTimeLimit = ini_get('max_execution_time');
		$iLoopTimeLimit = MetaModel::GetConfig()->Get('max_execution_time_per_loop');
		$iCount = 0;
		CMDBSource::Query('START TRANSACTION');
		while($oPerson = $oSet->Fetch())
		{
			set_time_limit($iLoopTimeLimit);
			$oPerson->Anonymize();
			$iCount++;
		}
		set_time_limit($iPreviousTimeLimit);
		CMDBSource::Query('COMMIT');

		//run one time background process.
		$oSearch = DBObjectSearch::FromOQL("SELECT BackgroundTask WHERE class_name = 'PersonalDataAnonymizer'");
		$oSet = new DBObjectSet($oSearch);
		while($oTask = $oSet->Fetch())
		{
			$oPlannedStart = new DateTime();
			$oTask->Set('next_run_date', $oPlannedStart->format('Y-m-d H:i:s'));
			$oTask->DBUpdate();
		}

		$oP->add_ready_script('AnonymizationDialog('.json_encode(Dict::S('Anonymization:Success')).', '.json_encode(Dict::S('Anonymization:RefreshTheList')).')');
		break;
		
		default:
			throw new Exception('Unsupported operation code: "'.$sOperation.'"');
	}
	
	$oP->output();
}
catch(Exception $e)
{
	if (version_compare(ITOP_DESIGN_LATEST_VERSION, '3.0') < 0) {
		$oP = new ajax_page('');
	} else {
		$oP = new AjaxPage('');
	}
	try {
		CMDBSource::Query('ROLLBACK');
	}
	catch (Exception $eRollback) {
		// we may not have opened a transaction... we shouldn't ccrash if this is the case !
	}
	$oP->add_ready_script('AnonymizationDialog('.json_encode(Dict::S('Anonymization:Error')).', '.json_encode("Internal Error: ".$e->getMessage().' All modifications have been reverted.').')');
	$oP->output();
}