<?php
/**
 * Localized data
 *
 * @copyright   Copyright (C) 2018 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */
Dict::Add('CS CZ', 'Czech', 'Čeština', array(
	// Dictionary entries go here
	'combodo-anonymizer/Operation:DisplayConfig/Title' => 'Anonymizace',
	'combodo-anonymizer/Operation:ApplyConfig/Title' => 'Anonymizace dat v iTopu',
	'Anonymization:AnonymizeAll' => 'Anonymizuj vše',
	'Anonymization:AnonymizeOne' => 'Anonymizuj',
	'Anonymization:OnePersonWarning' => 'Opravdu jste si jistý s nevratnou anonymizací tohoto kontaktu?',
	'Anonymization:ListOfPersonsWarning' => 'Opravdu jste si jistý s nevratnou anonymizací těchto %d kontaktů?',
	'Anonymization:Confirmation' => 'Prosím o potvrzení',
	'Anonymization:Information' => 'Informace',
	'Anonymization:RefreshTheList' => 'Obnov stránku pro načtení aktuálního stavu Anonymizace...',
	'Anonymization:DoneOnePerson' => 'Kontakt byl anonymizován!',
	'Anonymization:InProgress' => 'Probíhá anonymizace...',
	'Anonymization:Success' => 'Anonymizace úspěšně provedena',
	'Anonymization:Error' => 'Anonymizace SELHALA!',
	'Anonymization:Close' => 'Zavřít',
	'Anonymization:Configuration' => 'Nastavení',
	'Menu:ConfigAnonymizer' => 'Anonymizace',
	'Menu:AnonymizationTask' => 'Úlohy Anonymizace',
	'Menu:AnonymizationTask+' => '',
	'Anonymization:AutomationParameters' => 'Automatická anonymizace',
	'Anonymization:AnonymizationDelay_Input' => 'Automaticky anonymizuj kontakty, které mají status zastaralé déle jak %1$s dní',

	'Anonymization:Configuration:TimeRange' => 'Povolený čas spouštění úlohy',
	'Anonymization:Configuration:time' => 'Začátek (HH:MM)',
	'Anonymization:Configuration:end_time' => 'Konec (HH:MM)',
	'Anonymization:Configuration:Weekdays' => 'Dny v týdnu',
	'Anonymization:Configuration:Weekday:monday' => 'Pondělí',
	'Anonymization:Configuration:Weekday:tuesday' => 'Úterý',
	'Anonymization:Configuration:Weekday:wednesday' => 'Středa',
	'Anonymization:Configuration:Weekday:thursday' => 'Čtvrtek',
	'Anonymization:Configuration:Weekday:friday' => 'Pátek',
	'Anonymization:Configuration:Weekday:saturday' => 'Sobota',
	'Anonymization:Configuration:Weekday:sunday' => 'Neděle',

	// Default values used during anonymization
	'Anonymization:Person:name' => 'Kontakt',
	'Anonymization:Person:first_name' => 'Anonymní',
	'Anonymization:Person:email' => '%1$s.%2$s%3$s@anony.mized',
));

//
// Class: Person
//

Dict::Add('CS CZ', 'Czech', 'Čeština', array(
	'Class:Person/Attribute:anonymized' => 'Anonymizován',
	'Class:Person/Attribute:anonymized+' => '',
));

//
// Class: RessourceAnonymization
//

Dict::Add('CS CZ', 'Czech', 'Čeština', array(
	'Class:RessourceAnonymization' => 'RessourceAnonymization~~',
	'Class:RessourceAnonymization+' => '~~',
));

//
// Class: AnonymizationTaskAction
//

Dict::Add('CS CZ', 'Czech', 'Čeština', array(
	'Class:AnonymizationTaskAction' => 'AnonymizationTaskAction~~',
	'Class:AnonymizationTaskAction+' => '~~',
	'Class:AnonymizationTaskAction/Attribute:action_params' => 'Action params~~',
	'Class:AnonymizationTaskAction/Attribute:action_params+' => '~~',
));

//
// Class: AnonymizationTask
//

Dict::Add('CS CZ', 'Czech', 'Čeština', array(
	'Class:AnonymizationTask' => 'Anonymizační úloha',
	'Class:AnonymizationTask+' => '~~',
	'Class:AnonymizationTask/Attribute:person_id' => 'Kontakt id',
	'Class:AnonymizationTask/Attribute:person_id+' => 'Identifikační číslo kontaktu',
	'Class:AnonymizationTask/Attribute:anonymization_context' => 'Anonymization context~~',
	'Class:AnonymizationTask/Attribute:anonymization_context+' => '~~',
));

// Additional language entries not present in English dict
Dict::Add('CS CZ', 'Czech', 'Čeština', array(
 'Class:AnonymizationTask/Attribute:class_to_anonymize' => 'Třída k anonymizování',
 'Class:AnonymizationTask/Attribute:class_to_anonymize+' => 'Třída, kterou požadujete anonymizovat',
 'Class:AnonymizationTask/Attribute:id_to_anonymize' => 'Id k anonymizování',
 'Class:AnonymizationTask/Attribute:id_to_anonymize+' => 'Identifikační číslo, které požadujete anonymizovat',
 'Anonymization:NotificationsPurgeParameters' => 'Automatické mazání notifikací',
 'Anonymization:PurgeDelay_Input' => 'Automaticky smazat všechny notifikace starší %1$s dní.',
));
