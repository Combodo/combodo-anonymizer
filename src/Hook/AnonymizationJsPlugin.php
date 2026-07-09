<?php

/**
 * @copyright   Copyright (C) 2010-2024 Combodo SAS
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

/**
 * Class AnonymizationJsPlugin
 */
class AnonymizationJsPlugin implements iBackofficeDictEntriesExtension
{
	public function GetDictEntries(): array
	{
		return [
			'Anonymization:AnonymizeAll',
			'Anonymization:AnonymizeOne',
			'Anonymization:OnePersonWarning',
			'Anonymization:ListOfPersonsWarning',
			'Anonymization:Confirmation',
			'Anonymization:Information',
			'Anonymization:RefreshTheList',
			'Anonymization:DoneOnePerson',
			'Anonymization:InProgress',
			'Anonymization:Success',
			'Anonymization:Error',
			'Anonymization:Close',
			'Anonymization:Configuration',
			'Menu:ConfigAnonymizer',
			'Anonymization:AutomationParameters',
			'Anonymization:NotificationsPurgeParameters',
			'Anonymization:AnonymizationDelay_Input',
			'Anonymization:PurgeDelay_Input',
			'Anonymization:Person:name',
			'Anonymization:Person:first_name',
			'UI:Button:Ok',
		];
	}
}
