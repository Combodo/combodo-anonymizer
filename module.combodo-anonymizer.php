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

//
// iTop module definition file
//

/** @noinspection PhpUnhandledExceptionInspection */
SetupWebPage::AddModule(
	__FILE__, // Path to the current file, all other file names are relative to the directory containing this file
	'combodo-anonymizer/1.3.0',
	[
		// Identification
		//
		'label'        => 'Personal data anonymizer',
		'category'     => 'business',

		// Setup
		//
		'dependencies' => [
			'combodo-background-task-extended/1.0.0',
		],
		'mandatory' => false,
		'visible' => true,
		'installer' => 'AnonymizerInstaller',

		// Components
		//
		'datamodel' => [
			'vendor/autoload.php',
			'src/Model/PersonalDataAnonymizer.php',
			'src/Hook/AnonymizationMenuPlugIn.php',
			'src/Hook/AnonymizationJsPlugin.php',
			'model.combodo-anonymizer.php',
			'src/Action/ActionManageSynchronization.php',
			'src/Action/ActionAnonymizePerson.php',
			'src/Action/ActionCleanupCaseLogs.php',
			'src/Action/ActionCleanupEmailNotification.php',
			'src/Action/ActionCleanupOnMention.php',
			'src/Action/ActionCleanupUsers.php',
			'src/Action/ActionPurgePersonHistory.php',
			'src/Action/ActionResetPersonFields.php',
			'src/Action/ActionEndOfAnonymization.php',
		],
		'webservice' => [

		],
		'data.struct' => [
			// add your 'structure' definition XML files here,
		],
		'data.sample' => [
			// add your sample data XML files here,
		],

		// Documentation
		//
		'doc.manual_setup' => '', // hyperlink to manual setup documentation, if any
		'doc.more_information' => '', // hyperlink to more information, if any 

		// Default settings
		//
		'settings' => [
			// datamodel module_parameters
		],
	]
);


if (!class_exists('AnonymizerInstaller'))
{
	// Module installation handler
	//
	class AnonymizerInstaller extends ModuleInstallerAPI
	{
		/**
		 * Handler called before creating or upgrading the database schema
		 *
		 * @param $oConfiguration Config The new configuration of the application
		 * @param $sPreviousVersion string Previous version number of the module (empty string in case of first install)
		 * @param $sCurrentVersion string Current version number of the module
		 *
		 * @throws \CoreException
		 * @throws \MySQLException
		 * @throws \MySQLHasGoneAwayException
		 */
		public static function AfterDatabaseCreation(Config $oConfiguration, $sPreviousVersion, $sCurrentVersion)
		{
			if (strlen($sPreviousVersion) > 0) {
				$sBackgroundTask = MetaModel::DBGetTable('BackgroundTask');
				// If you want to migrate data from one format to another, do it here
				$sQueryUpdate = "UPDATE `$sBackgroundTask` SET `class_name` = 'PersonalDataAnonymizer' WHERE `class_name` = 'AnonymisationBackgroundProcess'";
				CMDBSource::Query($sQueryUpdate);
			}
		}
	}
}


