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
	'Anonymization:AutomationParameters' => 'Automatic anonymization',
	'Anonymization:AnonymizationDelay_Input' => 'Automatically anonymize Persons which are obsolete since more than %1$s days.',
	
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
