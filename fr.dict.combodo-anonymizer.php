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
	'combodo-anonymizer/Operation:ApplyConfig/Title' => 'Anonymisation',
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
	'Menu:AnonymizationTask' => 'Tâches d\'anonymisation',
	'Menu:AnonymizationTask+' => 'Tâches d\'anonymisation',
	'Anonymization:AutomationParameters' => 'Anonymisation automatique',
	'Anonymization:AnonymizationDelay_Input' => 'Anonymiser automatiquement les Personnes obsolètes depuis plus de %1$s jours.',

	'Anonymization:Configuration:TimeRange' => 'Plage d\'exécution autorisée',
	'Anonymization:Configuration:time' => 'Heure de début (HH:MM)',
	'Anonymization:Configuration:end_time' => 'Heure de fin (HH:MM)',
	'Anonymization:Configuration:Weekdays' => 'Jours de la semaine',
	'Anonymization:Configuration:Weekday:monday' => 'Lundi',
	'Anonymization:Configuration:Weekday:tuesday' => 'Mardi',
	'Anonymization:Configuration:Weekday:wednesday' => 'Mercredi',
	'Anonymization:Configuration:Weekday:thursday' => 'Jeudi',
	'Anonymization:Configuration:Weekday:friday' => 'Vendredi',
	'Anonymization:Configuration:Weekday:saturday' => 'Samedi',
	'Anonymization:Configuration:Weekday:sunday' => 'Dimanche',

	// Default values used during anonymization
	'Anonymization:Person:name' => 'Contact',
	'Anonymization:Person:first_name' => 'Anonyme',
	'Anonymization:Person:email' => '%1$s.%2$s%3$s@anony.mise',
));

//
// Class: Person
//

Dict::Add('FR FR', 'French', 'Français', array(
	'Class:Person/Attribute:anonymized' => 'Anonymisé(e)',
	'Class:Person/Attribute:anonymized+' => '',
));

//
// Class: RessourceAnonymization
//

Dict::Add('FR FR', 'French', 'Français', array(
	'Class:RessourceAnonymization' => 'RessourceAnonymization~~',
	'Class:RessourceAnonymization+' => '~~',
));

//
// Class: AnonymizationTaskAction
//

Dict::Add('FR FR', 'French', 'Français', array(
	'Class:AnonymizationTaskAction' => 'Action d\'anonymisation',
	'Class:AnonymizationTaskAction+' => '',
	'Class:AnonymizationTaskAction/Attribute:action_params' => 'Paramètres',
	'Class:AnonymizationTaskAction/Attribute:action_params+' => '',
));

//
// Class: AnonymizationTask
//

Dict::Add('FR FR', 'French', 'Français', array(
	'Class:AnonymizationTask' => 'Tâche d\'anonymisation',
	'Class:AnonymizationTask+' => '',
	'Class:AnonymizationTask/Attribute:person_id' => 'Personne',
	'Class:AnonymizationTask/Attribute:person_id+' => 'Personne à anonymiser',
	'Class:AnonymizationTask/Attribute:anonymization_context' => 'Contexte',
	'Class:AnonymizationTask/Attribute:anonymization_context+' => '',
));
