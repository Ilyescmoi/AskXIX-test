<?php
/**
 * Configuration centrale de l'application.
 * - La clé API vient EXCLUSIVEMENT de l'environnement (jamais en dur).
 * - Les taxonomies (listes fermées) sont ici pour ajout/édition facile.
 * - Les règles de "non-réponse" factuelles sont ici (utilisées par Stats.php, sans IA).
 */

// Charge le fichier .env (s'il existe) AVANT de lire les variables via getenv().
// L'environnement réel (ligne de commande, serveur) reste prioritaire sur le .env.
require_once __DIR__ . '/env.php';
load_env(__DIR__ . '/.env');

// Fuseau horaire de l'app (sinon les dates du rapport / noms de fichiers seraient en UTC).
date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Europe/Paris');

return [

    // --- Fournisseur IA (Mistral, ou tout endpoint compatible OpenAI/chat) ---
    'ai' => [
        // Clé lue dans l'environnement. Aucune valeur par défaut sensible.
        'api_key'     => getenv('MISTRAL_API_KEY') ?: '',
        // Endpoint chat-completions (surchargé via env pour un modèle open-source local).
        'endpoint'    => getenv('MISTRAL_ENDPOINT') ?: 'https://api.mistral.ai/v1/chat/completions',
        // Modèle par défaut (surchargé via env).
        'model'       => getenv('MISTRAL_MODEL') ?: 'mistral-small-latest',
        // Robustesse réseau.
        'timeout'     => 45,   // secondes par requête
        'max_retries' => 3,    // nb de tentatives sur erreur transitoire
        'retry_delay' => 2,    // secondes entre tentatives (backoff de base)
        'temperature' => 0,    // déterminisme maximal pour la classification
        'batch_size'  => 20,   // nb de messages classés par appel IA (moins d'appels = plus rapide)
        // Longueur max du texte envoyé à l'IA par champ (les stats factuelles, elles,
        // utilisent toujours le texte complet). Évite des prompts énormes sur réponses longues.
        'max_field_chars' => 1200,
    ],

    // --- Taxonomie INTENTIONS (liste FERMÉE). "indetermine" obligatoire en dernier recours. ---
    // Adaptée à un assistant de présentation d'un projet immobilier / bâtiment.
    'intents' => [
        'demande_information',   // question factuelle sur le projet / le bâtiment
        'demande_visuel',        // veut une image / perspective / brochure
        'demande_contact',       // cherche un interlocuteur (commercial, JLL...)
        'salutation',            // bonjour / merci / au revoir
        'hors_sujet',            // question sans rapport avec le projet
        'autre',                 // compréhensible mais hors des cas ci-dessus
        'indetermine',           // texte vide/ambigu/illisible → JAMAIS d'invention
    ],

    // --- Taxonomie SUJETS / THÈMES (liste FERMÉE). À adapter à ton métier. ---
    // Adaptée au projet immobilier "Farman" (caractéristiques, services, accès...).
    'topics' => [
        'caracteristiques_batiment',    // étages, surfaces, capacité, sous-sols, niveaux
        'architecture_projet',          // architecte, promoteur, maître d'ouvrage
        'services_amenites',            // café, restaurant, crèche, tennis, jardins
        'accessibilite_pmr',            // normes PMR, accès handicapés
        'environnement_certifications', // labels, certifications, espaces verts
        'transport_acces',              // tramway, temps de trajet, station, héliport
        'localisation_adresse',         // adresse, situation, à proximité
        'contact_commercial',           // JLL, interlocuteur commercial
        'visuels_media',                // perspectives, images, brochure
        'autre',
        'indetermine',
    ],

    // --- Taxonomie QUALITÉ (liste FERMÉE, jugée par l'IA sur le texte seul). ---
    'quality_labels' => [
        'coherente',   // répond bien à la question
        'partielle',   // répond en partie
        'non_reponse', // n'apporte pas de réponse
        'hors_sujet',  // répond à côté
        'indetermine',
    ],

    // --- Libellés lisibles (FR) pour l'affichage (clé technique -> libellé soigné). ---
    // Rédigés en langage simple, compréhensible par tous (y compris hors métier).
    'labels' => [
        'intents' => [
            'demande_information' => 'Recherche d\'information',
            'demande_visuel'      => 'Demande d\'image / brochure',
            'demande_contact'     => 'Demande de contact',
            'salutation'          => 'Politesse (bonjour, merci)',
            'hors_sujet'          => 'Hors sujet',
            'autre'               => 'Autre',
            'indetermine'         => 'Non classé',
        ],
        'topics' => [
            'caracteristiques_batiment'    => 'Le bâtiment (étages, surfaces…)',
            'architecture_projet'          => 'Le projet & l\'architecte',
            'services_amenites'            => 'Commerces & services (café, resto…)',
            'accessibilite_pmr'            => 'Accès handicapés (PMR)',
            'environnement_certifications' => 'Environnement & labels',
            'transport_acces'              => 'Transports & accès',
            'localisation_adresse'         => 'Emplacement & adresse',
            'contact_commercial'           => 'Contacts commerciaux',
            'visuels_media'                => 'Images & brochures',
            'autre'                        => 'Autres sujets',
            'indetermine'                  => 'Non classé',
        ],
        'quality' => [
            'coherente'   => 'Réponse pertinente',
            'partielle'   => 'Réponse partielle',
            'non_reponse' => 'Pas de réponse',
            'hors_sujet'  => 'Réponse hors sujet',
            'indetermine' => 'Non évalué',
        ],
    ],

    // --- Règles FACTUELLES de non-réponse (PHP pur, AUCUNE IA). ---
    'non_response_rules' => [
        'min_length' => 15,  // réponse < 15 caractères = trop courte
        // Formules de repli (matching insensible à la casse/accents, sous-chaîne).
        'fallback_phrases' => [
            'je ne sais pas',
            'je ne peux pas',
            'je n\'ai pas compris',
            'pouvez-vous reformuler',
            'desole',
            'aucune information',
            'contactez le support',
            'je ne suis pas en mesure',
        ],
    ],

    // --- Affichage / rapport ---
    'report' => [
        'app_name'  => 'Rapport d\'analyse conversationnelle',
        'subtitle'  => 'Synthèse des échanges de l\'assistant — à destination des équipes commerciales',
        'top_n'     => 10,   // top questions / questions sans réponse affichées
        'best_n'    => 5,    // exemples de réponses pertinentes affichés (rapport ET formule §3)
        'locale'    => 'fr_FR',
    ],

    // --- Traçabilité / document d'audit ---
    // Tout ce qui règle la « preuve » : registre complet, journal IA, références #NNN.
    'trace' => [
        'response_excerpt_chars'  => 400,  // extrait de réponse IA brute conservé par lot
        'raw_label_chars'         => 60,   // troncature d'une étiquette brute hors liste (PDF + CSV)
        'registry_question_chars' => 90,   // troncature de la question dans le registre PDF
        'registry_reponse_chars'  => 110,  // troncature de la réponse dans le registre PDF
        'registry_chunk'          => 100,  // taille des groupes du registre (1 table par groupe)
        'refs_inline_max'         => 30,   // plages d'ids affichées dans l'annexe avant « +N »
        'refs_report_max'         => 6,    // refs affichées en ligne dans le rapport principal
        'max_anchors'             => 3000, // au-delà : registre sans liens internes (PDF allégé)
        // Codes courts du registre (légende imprimée dans le document de traçabilité).
        'codes' => [
            'intents' => [
                'demande_information' => 'INF', 'demande_visuel' => 'VIS',
                'demande_contact' => 'CON', 'salutation' => 'SAL',
                'hors_sujet' => 'HSJ', 'autre' => 'AUT', 'indetermine' => 'IND',
            ],
            'topics' => [
                'caracteristiques_batiment' => 'BAT', 'architecture_projet' => 'ARC',
                'services_amenites' => 'SRV', 'accessibilite_pmr' => 'PMR',
                'environnement_certifications' => 'ENV', 'transport_acces' => 'TRA',
                'localisation_adresse' => 'LOC', 'contact_commercial' => 'COM',
                'visuels_media' => 'MED', 'autre' => 'AUT', 'indetermine' => 'IND',
            ],
            'quality' => [
                'coherente' => 'OK', 'partielle' => 'PART', 'non_reponse' => 'NR',
                'hors_sujet' => 'HS', 'indetermine' => 'IND',
            ],
        ],
    ],
];
