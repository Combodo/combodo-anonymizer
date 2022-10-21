<?php
/**
 * Localized data
 *
 * @copyright   Copyright (C) 2018 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

Dict::Add('EN US', 'English', 'English', array(
	// Dictionary entries go here
	'combodo-anonymizer/Operation:DisplayConfig/Title' => 'Anonymization',
	'combodo-anonymizer/Operation:ApplyConfig/Title' => 'Anonymization',
	'Anonymization:AnonymizeAll' => 'Anonymize All',
	'Anonymization:AnonymizeOne' => 'Anonymize',
	'Anonymization:OnePersonWarning' => 'Are you sure that you want to anonymize this Person? (this cannot be undone)',
	'Anonymization:ListOfPersonsWarning' => 'Are you sure that you want to anonymize %d Persons? (this cannot be undone)',
	'Anonymization:Confirmation' => 'Please confirm',
	'Anonymization:Information' => 'Information',
	'Anonymization:RefreshTheList' => 'Refresh the list to see the effect of anonymization... The links to these contacts will also be quickly anonymized.',
	'Anonymization:DoneOnePerson' => 'The contact has been anonymized... The links to this contact will also be quickly anonymized.',
	'Anonymization:InProgress' => 'Anonymization in progress...',
	'Anonymization:Success' => 'Anonymization successful',
	'Anonymization:Error' => 'Anonymization FAILED',
	'Anonymization:Close' => 'Close',
	'Anonymization:Configuration' => 'Configuration',
	'Menu:ConfigAnonymizer' => 'Anonymization',
	'Menu:AnonymizationTask' => 'Anonymization tasks',
	'Menu:AnonymizationTask+' => 'Anonymization tasks',
	'Anonymization:AutomationParameters' => 'Automatic anonymization',
	'Anonymization:AnonymizationDelay_Input' => 'Automatically anonymize Persons which are obsolete since more than %1$s days.',

	'Anonymization:Configuration:TimeRange' => 'Allowed execution time range',
	'Anonymization:Configuration:time' => 'Start time (HH:MM)',
	'Anonymization:Configuration:end_time' => 'End time (HH:MM)',
	'Anonymization:Configuration:Weekdays' => 'Week days',
	'Anonymization:Configuration:Weekday:monday' => 'Monday',
	'Anonymization:Configuration:Weekday:tuesday' => 'Tuesday',
	'Anonymization:Configuration:Weekday:wednesday' => 'Wednesday',
	'Anonymization:Configuration:Weekday:thursday' => 'Thursday',
	'Anonymization:Configuration:Weekday:friday' => 'Friday',
	'Anonymization:Configuration:Weekday:saturday' => 'Saturday',
	'Anonymization:Configuration:Weekday:sunday' => 'Sunday',

	// Default values used during anonymization
	'Anonymization:Person:name' => 'Contact',
	'Anonymization:Person:first_name' => 'Anonymous',
	'Anonymization:Person:email' => '%1$s.%2$s%3$s@anony.mized',
));

//
// Class: Person
//

Dict::Add('EN US', 'English', 'English', array(
	'Class:Person/Attribute:anonymized' => 'Anonymized',
	'Class:Person/Attribute:anonymized+' => '',
));

//
// Class: RessourceAnonymization
//

Dict::Add('EN US', 'English', 'English', array(
	'Class:RessourceAnonymization' => 'RessourceAnonymization',
	'Class:RessourceAnonymization+' => '',
));

//
// Class: AnonymizationTaskAction
//

Dict::Add('EN US', 'English', 'English', array(
	'Class:AnonymizationTaskAction' => 'Anonymization Task Action',
	'Class:AnonymizationTaskAction+' => '',
	'Class:AnonymizationTaskAction/Attribute:action_params' => 'Action params',
	'Class:AnonymizationTaskAction/Attribute:action_params+' => '',
));

//
// Class: AnonymizationTask
//

Dict::Add('EN US', 'English', 'English', array(
	'Class:AnonymizationTask' => 'Anonymization Task',
	'Class:AnonymizationTask+' => '',
	'Class:AnonymizationTask/Attribute:person_id' => 'Person',
	'Class:AnonymizationTask/Attribute:person_id+' => 'Person to anonymize',
	'Class:AnonymizationTask/Attribute:anonymization_context' => 'Anonymization context',
	'Class:AnonymizationTask/Attribute:anonymization_context+' => '',
));
