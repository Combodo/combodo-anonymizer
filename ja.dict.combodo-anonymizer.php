<?php
/**
 * Localized data
 *
 * @copyright   Copyright (C) 2018 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */
Dict::Add('JA JP', 'Japanese', '日本語', array(
	// Dictionary entries go here
	'combodo-anonymizer/Operation:DisplayConfig/Title' => 'Anonymization~~',
	'combodo-anonymizer/Operation:ApplyConfig/Title' => 'Anonymization~~',
	'Anonymization:AnonymizeAll' => 'Anonymize All~~',
	'Anonymization:AnonymizeOne' => 'Anonymize~~',
	'Anonymization:OnePersonWarning' => 'Are you sure that you want to anonymize this Person? (this cannot be undone)~~',
	'Anonymization:ListOfPersonsWarning' => 'Are you sure that you want to anonymize %d Persons? (this cannot be undone)~~',
	'Anonymization:Confirmation' => 'Please confirm~~',
	'Anonymization:Information' => 'Information~~',
	'Anonymization:RefreshTheList' => 'Refresh the list to see the effect of anonymization...~~',
	'Anonymization:DoneOnePerson' => 'The contact has been anonymized...~~',
	'Anonymization:InProgress' => 'Anonymization in progress...~~',
	'Anonymization:Success' => 'Anonymization successful~~',
	'Anonymization:Error' => 'Anonymization FAILED~~',
	'Anonymization:Close' => 'Close~~',
	'Anonymization:Configuration' => 'Configuration~~',
	'Menu:ConfigAnonymizer' => 'Anonymization~~',
	'Anonymization:AutomationParameters' => 'Automatic anonymization~~',
	'Anonymization:AnonymizationDelay_Input' => 'Automatically anonymize Persons which are obsolete since more than %1$s days.~~',

	'Anonymization:Configuration:TimeRange' => 'Allowed execution time range~~',
	'Anonymization:Configuration:time' => 'Start time (HH:MM)~~',
	'Anonymization:Configuration:end_time' => 'End time (HH:MM)~~',
	'Anonymization:Configuration:Weekdays' => 'Week days~~',
	'Anonymization:Configuration:Weekday:monday' => 'Monday~~',
	'Anonymization:Configuration:Weekday:tuesday' => 'Tuesday~~',
	'Anonymization:Configuration:Weekday:wednesday' => 'Wednesday~~',
	'Anonymization:Configuration:Weekday:thursday' => 'Thursday~~',
	'Anonymization:Configuration:Weekday:friday' => 'Friday~~',
	'Anonymization:Configuration:Weekday:saturday' => 'Saturday~~',
	'Anonymization:Configuration:Weekday:sunday' => 'Sunday~~',

	// Default values used during anonymization
	'Anonymization:Person:name' => 'Contact~~',
	'Anonymization:Person:first_name' => 'Anonymous~~',
	'Anonymization:Person:email' => '%1$s.%2$s%3$s@anony.mized',
));

//
// Class: Person
//

Dict::Add('JA JP', 'Japanese', '日本語', array(
	'Class:Person/Attribute:anonymized' => 'Anonymized~~',
	'Class:Person/Attribute:anonymized+' => '~~',
));

//
// Class: RessourceAnonymization
//

Dict::Add('JA JP', 'Japanese', '日本語', array(
	'Class:RessourceAnonymization' => 'RessourceAnonymization~~',
	'Class:RessourceAnonymization+' => '~~',
));

//
// Class: AnonymizationTask
//

Dict::Add('JA JP', 'Japanese', '日本語', array(
	'Class:AnonymizationTask' => 'AnonymizationTask~~',
	'Class:AnonymizationTask+' => '~~',
	'Class:AnonymizationTask/Attribute:class_to_anonymize' => 'Class to anonymize~~',
	'Class:AnonymizationTask/Attribute:class_to_anonymize+' => '~~',
	'Class:AnonymizationTask/Attribute:id_to_anonymize' => 'Id to anonymize~~',
	'Class:AnonymizationTask/Attribute:id_to_anonymize+' => '~~',
	'Class:AnonymizationTask/Attribute:anonymization_context' => 'Anonymization context~~',
	'Class:AnonymizationTask/Attribute:anonymization_context+' => '~~',
));

//
// Class: AnonymizationTaskAction
//

Dict::Add('JA JP', 'Japanese', '日本語', array(
	'Class:AnonymizationTaskAction' => 'AnonymizationTaskAction~~',
	'Class:AnonymizationTaskAction+' => '~~',
	'Class:AnonymizationTaskAction/Attribute:action_params' => 'Action params~~',
	'Class:AnonymizationTaskAction/Attribute:action_params+' => '~~',
));

// Additional language entries not present in English dict
Dict::Add('JA JP', 'Japanese', '日本語', array(
 'Anonymization:NotificationsPurgeParameters' => 'Automatic purge of notifications~~',
 'Anonymization:PurgeDelay_Input' => 'Automatically delete all notifications emitted since more than %1$s days.~~',
));
