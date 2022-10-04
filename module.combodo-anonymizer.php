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
	'combodo-anonymizer/1.3.0-dev',
	[
		// Identification
		//
		'label'        => 'Personal data anonymizer',
		'category'     => 'business',

		// Setup
		//
		'dependencies' => [
			'itop-config-mgmt/2.4.0',
			'itop-config/2.4.0',
			'combodo-background-task/1.0.0',
		],
		'mandatory' => false,
		'visible' => true,
		'installer' => 'AnonymizerInstaller',

		// Components
		//
		'datamodel' => [
			'vendor/autoload.php',
			'src/Model/AbstractBatchAnonymizationTask.php',
			'src/Model/PersonalDataAnonymizer.php',
			'src/Hook/AnonymizationMenuPlugIn.php',
			'src/Hook/AnonymizationJsPlugin.php',
			'model.combodo-anonymizer.php',
			'main.combodo-anonymizer.php',
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
			// Module specific settings go here, if any
			'week_days' => 'monday, tuesday, wednesday, thursday, friday, saturday, sunday',
			'time' => '00:30',
			'end_time' => '05:30',
			'enabled' => true,
			'debug' => true,
			'max_chunk_size' => 1000,
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
		public static function BeforeDatabaseCreation(Config $oConfiguration, $sPreviousVersion, $sCurrentVersion)
		{
			if (strlen($sPreviousVersion) > 0)
			{
				$sBackgroundTask = MetaModel::DBGetTable('BackgroundTask');
				// If you want to migrate data from one format to another, do it here
				$sQueryUpdate = "UPDATE `$sBackgroundTask` SET `class_name` = 'PersonalDataAnonymizer' WHERE `class_name` = 'AnonymisationBackgroundProcess'";
				CMDBSource::Query($sQueryUpdate);
			}
		}
	}
}


