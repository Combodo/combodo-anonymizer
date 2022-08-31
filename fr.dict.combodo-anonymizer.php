<?php
/**
 * Localized data
 *
 * @copyright   Copyright (C) 2018 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */
Dict::Add('FR FR', 'French', 'Français', array(
	// Dictionary entries go here
	'Anonymization:AnonymizeAll' => 'Tout anonymiser',
	'Anonymization:AnonymizeOne' => 'Anonymiser',
	'Anonymization:OnePersonWarning' => 'Etes vous sûr(e) de vouloir anonymiser cette Personne ? (ceci ne pourra pas être annulé)',
	'Anonymization:ListOfPersonsWarning' => 'Etes vous sûr(e) de vouloir anonymiser %d Personnes? (ceci ne pourra pas être annulé)',
	'Anonymization:Confirmation' => 'Veuillez confirmer',
	'Anonymization:Information' => 'Information',
	'Anonymization:RefreshTheList' => 'Rechargez la liste pour voir les résultat...',
	'Anonymization:DoneOnePerson' => 'La personne a été anonymisée...',
	'Anonymization:InProgress' => 'Anonymisation en cours...',
	'Anonymization:Success' => 'Anonymisation réussie',
	'Anonymization:Error' => 'Echec de l\'anonymisation',
	'Anonymization:Close' => 'Fermer',
	'Anonymization:Configuration' => 'Configuration',
	'Menu:ConfigAnonymizer' => 'Anonymisation et purge',
	'Anonymization:AutomationParameters' => 'Anonymisation automatique',
	'Anonymization:NotificationsPurgeParameters' => 'Suppression automatique des notifications',
	'Anonymization:AnonymizationDelay_Input' => 'Anonymiser automatiquement les Personnes obsolètes depuis plus de %1$s jours.',
	'Anonymization:PurgeDelay_Input' => 'Supprimer automatiquement toutes les notifications émises depuis plus de %1$s jours.',
	
	// Default values used during anonymization
	'Anonymization:Person:name' => 'Contact',
	'Anonymization:Person:first_name' => 'Anonyme',
));

//
// Class: Person
//

Dict::Add('FR FR', 'French', 'Français', array(
	'Class:Person/Attribute:anonymized' => 'Anonymisé(e)~~',
	'Class:Person/Attribute:anonymized+' => '~~',
));
