<?php
/**
 * Localized data
 *
 * @copyright   Copyright (C) 2018 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 * @author      Lars Kaltefleiter <lars.kaltefleiter@itomig.de>
 */
Dict::Add('DE DE', 'German', 'Deutsch', array(
	// Dictionary entries go here
	'combodo-anonymizer/Operation:DisplayConfig/Title' => 'Anonymization~~',
	'combodo-anonymizer/Operation:ApplyConfig/Title' => 'Anonymization~~',
	'Anonymization:AnonymizeAll' => 'Alle anonymisieren',
	'Anonymization:AnonymizeOne' => 'Anonymisieren',
	'Anonymization:OnePersonWarning' => 'Sind Sie sicher, dass Sie diese Person anonymisieren wollen? (Diese Operation kann nicht rückgängig gemacht werden)',
	'Anonymization:ListOfPersonsWarning' => 'Sind Sie sicher, dass Sie %d Personen anonymisieren wollen? (Diese Operation kann nicht rückgängig gemacht werden)',
	'Anonymization:Confirmation' => 'Bitte bestätigen',
	'Anonymization:Information' => 'Information',
	'Anonymization:RefreshTheList' => 'Bitte aktualisieren Sie die Liste, um das Resultat der Anonymisierung zu sehen...',
	'Anonymization:DoneOnePerson' => 'Der Kontakt wurde anonymisiert...',
	'Anonymization:InProgress' => 'Anonymisierung in Arbeit...',
	'Anonymization:Success' => 'Anonymisierung erfolgreich',
	'Anonymization:Error' => 'Anonymisierung FEHLGESCHLAGEN',
	'Anonymization:Close' => 'Schließen',
	'Anonymization:Configuration' => 'Konfiguration',
	'Menu:ConfigAnonymizer' => 'Anonymisierung',
	'Menu:AnonymizationTask' => 'Anonymization tasks~~',
	'Menu:AnonymizationTask+' => 'Anonymization tasks~~',
	'Anonymization:AutomationParameters' => 'Automatische Anonymisierung',
	'Anonymization:AnonymizationDelay_Input' => 'Automatisch Personen anonymisieren, die obsolet sind seit %1$s Tagen.',

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
	'Anonymization:Person:name' => 'Kontakt',
	'Anonymization:Person:first_name' => 'Anonym',
	'Anonymization:Person:email' => '%1$s.%2$s%3$s@anony.mized',
));

//
// Class: Person
//

Dict::Add('DE DE', 'German', 'Deutsch', array(
	'Class:Person/Attribute:anonymized' => 'Anonymisiert',
	'Class:Person/Attribute:anonymized+' => '',
));

//
// Class: RessourceAnonymization
//

Dict::Add('DE DE', 'German', 'Deutsch', array(
	'Class:RessourceAnonymization' => 'RessourceAnonymization~~',
	'Class:RessourceAnonymization+' => '~~',
));

//
// Class: AnonymizationTaskAction
//

Dict::Add('DE DE', 'German', 'Deutsch', array(
	'Class:AnonymizationTaskAction' => 'AnonymizationTaskAction~~',
	'Class:AnonymizationTaskAction+' => '~~',
	'Class:AnonymizationTaskAction/Attribute:action_params' => 'Action params~~',
	'Class:AnonymizationTaskAction/Attribute:action_params+' => '~~',
));

//
// Class: AnonymizationTask
//

Dict::Add('DE DE', 'German', 'Deutsch', array(
	'Class:AnonymizationTask' => 'AnonymizationTask~~',
	'Class:AnonymizationTask+' => '~~',
	'Class:AnonymizationTask/Attribute:person_id' => 'Person id~~',
	'Class:AnonymizationTask/Attribute:person_id+' => '~~',
	'Class:AnonymizationTask/Attribute:anonymization_context' => 'Anonymization context~~',
	'Class:AnonymizationTask/Attribute:anonymization_context+' => '~~',
));

// Additional language entries not present in English dict
Dict::Add('DE DE', 'German', 'Deutsch', array(
 'Class:AnonymizationTask/Attribute:class_to_anonymize' => 'Class to anonymize~~',
 'Class:AnonymizationTask/Attribute:class_to_anonymize+' => '~~',
 'Class:AnonymizationTask/Attribute:id_to_anonymize' => 'Id to anonymize~~',
 'Class:AnonymizationTask/Attribute:id_to_anonymize+' => '~~',
 'Anonymization:NotificationsPurgeParameters' => 'Automatische Löschen von Benachrichtigungen',
 'Anonymization:PurgeDelay_Input' => 'Automatisch Benachrichtigungen entfernen, die vor mehr als %1$s Tagen gesendet wurden.',
));
