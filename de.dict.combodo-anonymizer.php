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
	'Menu:ConfigAnonymizer' => 'Anonymisierung und löschen',
	'Anonymization:AutomationParameters' => 'Automatische Anonymisierung',
	'Anonymization:NotificationsPurgeParameters' => 'Automatische Löschen von Benachrichtigungen',
	'Anonymization:AnonymizationDelay_Input' => 'Automatisch Personen anonymisieren, die obsolet sind seit %1$s Tagen.',
	'Anonymization:PurgeDelay_Input' => 'Automatisch Benachrichtigungen entfernen, die vor mehr als %1$s Tagen gesendet wurden.',
	
	// Default values used during anonymization
	'Anonymization:Person:name' => 'Kontakt',
	'Anonymization:Person:first_name' => 'Anonym',
));

//
// Class: Person
//

Dict::Add('DE DE', 'German', 'Deutsch', array(
	'Class:Person/Attribute:anonymized' => 'Anonymisiert',
	'Class:Person/Attribute:anonymized+' => '',
));
