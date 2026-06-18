<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Configuration du vérificateur de fidélité.
 *
 * - La clé API et le fournisseur LLM viennent EXCLUSIVEMENT de l'environnement (.env) :
 *   LLM_API_KEY / LLM_ENDPOINT / LLM_MODEL (compatibles OpenAI ou Mistral).
 * - Les taxonomies (listes fermées) et libellés FR sont ici (édition facile, par défaut
 *   calibrés immobilier — surchargeable par bot via bot.json plus tard).
 * - bundle() assemble le tableau de config attendu par les librairies métier.
 */
class Verifier extends BaseConfig
{
    /** Intentions (liste FERMÉE). @var string[] */
    public array $intents = [
        'demande_information', 'demande_visuel', 'demande_contact', 'salutation',
        'hors_sujet', 'autre', 'indetermine',
    ];

    /** Sujets / thèmes (liste FERMÉE). @var string[] */
    public array $topics = [
        'caracteristiques_batiment', 'architecture_projet', 'services_amenites',
        'accessibilite_pmr', 'environnement_certifications', 'transport_acces',
        'localisation_adresse', 'contact_commercial', 'visuels_media', 'autre', 'indetermine',
    ];

    /** Qualité (liste FERMÉE). @var string[] */
    public array $qualityLabels = ['coherente', 'partielle', 'non_reponse', 'hors_sujet', 'indetermine'];

    /** Ancrage / fidélité à la base (liste FERMÉE). @var string[] */
    public array $groundingLabels = ['fondee', 'partielle', 'non_fondee', 'hors_base', 'indetermine'];

    /** Libellés FR par groupe. @var array<string,array<string,string>> */
    public array $labels = [
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
        'grounding' => [
            'fondee'      => 'Réponse fondée',
            'partielle'   => 'Partiellement fondée',
            'non_fondee'  => 'Non fondée',
            'hors_base'   => 'Hors base',
            'indetermine' => 'Non évalué',
        ],
    ];

    /** Règles factuelles de non-réponse (PHP pur). @var array<string,mixed> */
    public array $nonResponseRules = [
        'min_length'       => 15,
        'fallback_phrases' => [
            'je ne sais pas', 'je ne peux pas', 'je n\'ai pas compris',
            'pouvez-vous reformuler', 'desole', 'aucune information',
            'contactez le support', 'je ne suis pas en mesure',
        ],
    ];

    /** Affichage / rapport. @var array<string,mixed> */
    public array $report = [
        'app_name' => 'Rapport de vérification conversationnelle',
        'subtitle' => 'Fidélité des réponses du chatbot à sa base de connaissance',
        'top_n'    => 10,
        'best_n'   => 5,
        'locale'   => 'fr_FR',
    ];

    /** Traçabilité / document d'audit. @var array<string,mixed> */
    public array $trace = [
        'response_excerpt_chars'  => 400,
        'raw_label_chars'         => 60,
        'registry_question_chars' => 90,
        'registry_reponse_chars'  => 110,
        'registry_chunk'          => 100,
        'refs_inline_max'         => 30,
        'refs_report_max'         => 6,
        'max_anchors'             => 3000,
        'codes' => [
            'intents' => [
                'demande_information' => 'INF', 'demande_visuel' => 'VIS', 'demande_contact' => 'CON',
                'salutation' => 'SAL', 'hors_sujet' => 'HSJ', 'autre' => 'AUT', 'indetermine' => 'IND',
            ],
            'topics' => [
                'caracteristiques_batiment' => 'BAT', 'architecture_projet' => 'ARC', 'services_amenites' => 'SRV',
                'accessibilite_pmr' => 'PMR', 'environnement_certifications' => 'ENV', 'transport_acces' => 'TRA',
                'localisation_adresse' => 'LOC', 'contact_commercial' => 'COM', 'visuels_media' => 'MED',
                'autre' => 'AUT', 'indetermine' => 'IND',
            ],
            'quality' => [
                'coherente' => 'OK', 'partielle' => 'PART', 'non_reponse' => 'NR', 'hors_sujet' => 'HS', 'indetermine' => 'IND',
            ],
            'grounding' => [
                'fondee' => 'FND', 'partielle' => 'PFD', 'non_fondee' => 'NFD', 'hors_base' => 'HB', 'indetermine' => 'IND',
            ],
        ],
    ];

    /** Base de connaissance (lecture, comparaison). @var array<string,mixed> */
    public array $base = [
        'text_ext'             => ['txt', 'md'],
        'image_ext'            => ['png', 'jpg', 'jpeg', 'webp', 'gif'],
        'max_files'            => 2000,
        'max_total_bytes'      => 50 * 1024 * 1024,   // borne de sécurité du dossier base
        'context_chars'        => 14000,              // taille max de la base injectée dans le prompt (toute la base, tronquée si énorme)
        'grounding_batch_size' => 12,                 // réponses jugées par appel (base + N réponses)
        'excerpt_chars'        => 600,                // extrait de base affiché pour une réponse non fondée
    ];

    /**
     * Assemble le tableau de configuration complet attendu par les librairies métier.
     * La partie 'ai' (fournisseur LLM) vient de l'environnement — jamais en dur.
     *
     * @return array<string,mixed>
     */
    public function bundle(): array
    {
        return [
            'ai' => [
                'api_key'         => (string) (env('LLM_API_KEY') ?: env('MISTRAL_API_KEY') ?: ''),
                'endpoint'        => (string) (env('LLM_ENDPOINT') ?: 'https://api.mistral.ai/v1/chat/completions'),
                'model'           => (string) (env('LLM_MODEL') ?: 'mistral-small-latest'),
                'timeout'         => (int) (env('LLM_TIMEOUT') ?: 45),
                'max_retries'     => 3,
                'retry_delay'     => 2,
                'temperature'     => 0,
                'batch_size'      => 20,
                'max_field_chars' => 1200,
            ],
            'intents'            => $this->intents,
            'topics'             => $this->topics,
            'quality_labels'     => $this->qualityLabels,
            'grounding_labels'   => $this->groundingLabels,
            'labels'             => $this->labels,
            'non_response_rules' => $this->nonResponseRules,
            'report'             => $this->report,
            'trace'              => $this->trace,
            'base'               => $this->base,
        ];
    }
}
