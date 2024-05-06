<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SAS
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

if (version_compare(ITOP_DESIGN_LATEST_VERSION, '3.1') < 0) {
	/**
	 * Class AnonymizationPlugInLegacy
	 *
	 * @deprecated since 3.1.0
	 */
	class AnonymizationPlugInLegacy implements iPageUIExtension
	{
		/**
		 * @inheritDoc
		 */
		public function GetNorthPaneHtml(iTopWebPage $oPage)
		{
			// backward compatbility with iTop 2.4, emulate add_dict_entries
			$aDictEntries = array(
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
			);
			foreach ($aDictEntries as $sDictCode) {
				$oPage->add_dict_entry($sDictCode);
			}
		}

		/**
		 * @inheritDoc
		 */
		public function GetSouthPaneHtml(iTopWebPage $oPage)
		{

		}

		/**
		 * @inheritDoc
		 */
		public function GetBannerHtml(iTopWebPage $oPage)
		{

		}
	}
} else {
	/*
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
}
