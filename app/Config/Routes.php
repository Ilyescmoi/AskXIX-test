<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

// Génération du rapport de vérification pour un bot.
//   GET  /rapport/<bot>  → lit writable/bots/<bot>/conversations.csv
//   POST /rapport/<bot>  → conversations envoyées dans la requête (upload « file » ou corps brut CSV)
$routes->match(['get', 'post'], 'rapport/(:segment)', 'ReportController::generate/$1', ['filter' => 'apikey']);

// Téléchargement du dernier document généré pour un bot (sans régénérer).
//   GET /telecharger/<bot>/<type>   où type = rapport | audit | csv
$routes->get('telecharger/(:segment)/(:segment)', 'ReportController::download/$1/$2', ['filter' => 'apikey']);
