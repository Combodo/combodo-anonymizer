<?php
/**
 * Localized data
 *
 * @copyright   Copyright (C) 2018 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */
Dict::Add('FR FR', 'French', 'Français', array(
	// Dictionary entries go here
	'combodo-anonymizer/Operation:DisplayConfig/Title' => 'Anonymisation',
	'Anonymization:AnonymizeAll' => 'Tout anonymiser',
	'Anonymization:AnonymizeOne' => 'Anonymiser',
	'Anonymization:OnePersonWarning' => '̊̂Êtes vous sûr(e) de vouloir anonymiser cette Personne ? (ceci ne pourra pas être annulé)',
	'Anonymization:ListOfPersonsWarning' => 'Êtes vous sûr(e) de vouloir anonymiser %d Personnes? (ceci ne pourra pas être annulé)',
	'Anonymization:Confirmation' => 'Veuillez confirmer',
	'Anonymization:Information' => 'Information',
	'Anonymization:RefreshTheList' => 'Rechargez la liste pour voir les résultat...',
	'Anonymization:DoneOnePerson' => 'La personne a été anonymisée...',
	'Anonymization:InProgress' => 'Anonymisation en cours...',
	'Anonymization:Success' => 'Anonymisation réussie',
	'Anonymization:Error' => 'Échec de l\'anonymisation',
	'Anonymization:Close' => 'Fermer',
	'Anonymization:Configuration' => 'Configuration',
	'Menu:ConfigAnonymizer' => 'Anonymisation',
	'Anonymization:AutomationParameters' => 'Anonymisation automatique',
	'Anonymization:AnonymizationDelay_Input' => 'Anonymiser automatiquement les Personnes obsolètes depuis plus de %1$s jours.',
	
	// Default values used during anonymization
	'Anonymization:Person:name' => 'Contact',
	'Anonymization:Person:first_name' => 'Anonyme',
	'Anonymization:Person:email' => '%1$s.%2$s%3$s@anony.mise',
));

//
// Class: Person
//

Dict::Add('FR FR', 'French', 'Français', array(
	'Class:Person/Attribute:anonymized' => 'Anonymisé(e)~~',
	'Class:Person/Attribute:anonymized+' => '~~',
));
