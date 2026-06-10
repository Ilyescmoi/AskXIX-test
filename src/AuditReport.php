<?php

/**
 * AuditReport — produit la traçabilité du rapport : un CSV ligne-par-ligne et les
 * données du DOCUMENT DE TRAÇABILITÉ (PDF) qui justifie CHAQUE chiffre du rapport
 * principal et explique COMMENT le raisonnement a fonctionné.
 *
 * Chaîne de preuve : chiffre du rapport → §3/§4 (formule + refs #NNN) →
 * §9 registre complet (texte de chaque message) → CSV de traçabilité (texte
 * intégral + étiquettes brutes + lots IA) → fichier source (scellé par SHA-256).
 *
 * Objectif : prouver qu'aucune information du rapport n'est inventée.
 *  - les chiffres sont recalculables depuis le CSV de traçabilité ;
 *  - les non-réponses indiquent la règle exacte qui les a déclenchées ;
 *  - chaque étiquette IA est rattachée à son message source, son lot d'appel
 *    et sa valeur brute quand elle a été corrigée (liste fermée).
 */
class AuditReport
{
    /**
     * @param array<string,mixed> $config Configuration complète (config.php)
     * @param Stats $stats Service de stats (réutilisé pour la règle de non-réponse)
     */
    public function __construct(private array $config, private Stats $stats) {}

    /**
     * Construit la table de traçabilité par message : source + règle + étiquettes
     * + provenance IA (lot, statut, valeur brute si corrigée).
     *
     * @param array<int,array<string,mixed>> $rows
     * @param array<int,array{intent:string,topic:string}> $labels
     * @param array<int,array{quality:string,hallucination:bool}> $quality
     * @param RunTrace|null $trace
     * @return array<int,array<string,mixed>>
     */
    public function rows(array $rows, array $labels, array $quality, ?RunTrace $trace = null): array
    {
        $aiOff = $trace !== null && !$trace->aiEnabled();
        $out = [];
        foreach ($rows as $r) {
            $id = (int) $r['id'];
            $reason = $this->stats->nonResponseReason((string) $r['reponse']);
            $prov = $trace?->messageProvenance($id) ?? [];
            $provC = $prov['classification'] ?? null;
            $provQ = $prov['qualite'] ?? null;

            $out[] = [
                'id'            => $id,
                'date'          => (string) $r['date_raw'],
                'date_obj'      => $r['date'], // ?DateTimeImmutable (affichage court)
                'user_id'       => (string) $r['user_id'],
                'question'      => (string) $r['question'],
                'reponse'       => (string) $r['reponse'],
                'rep_len'       => Text::length((string) $r['reponse']),
                'erreur_source' => (string) $r['erreur'],
                'non_reponse'   => $reason !== null,
                'regle'         => $reason ?? '',
                'intent_key'    => $labels[$id]['intent'] ?? 'indetermine',
                'topic_key'     => $labels[$id]['topic'] ?? 'indetermine',
                'quality_key'   => $quality[$id]['quality'] ?? 'indetermine',
                'intent'        => $this->label('intents', $labels[$id]['intent'] ?? 'indetermine'),
                'topic'         => $this->label('topics', $labels[$id]['topic'] ?? 'indetermine'),
                'quality'       => $this->label('quality', $quality[$id]['quality'] ?? 'indetermine'),
                'hallucination' => !empty($quality[$id]['hallucination']),
                'lot_c'         => $provC['lot'] ?? null,
                'lot_q'         => $provQ['lot'] ?? null,
                'statut_c'      => $provC['statut'] ?? ($aiOff ? 'ia_desactivee' : ''),
                'statut_q'      => $provQ['statut'] ?? ($aiOff ? 'ia_desactivee' : ''),
                'brut_c'        => $provC['brut'] ?? '',
                'brut_q'        => $provQ['brut'] ?? '',
            ];
        }
        return $out;
    }

    /**
     * Écrit le CSV de traçabilité (séparateur ';' + BOM UTF-8 pour Excel français).
     * 1 message = 1 ligne ; la colonne 'ref' est la référence #NNN commune aux PDF.
     *
     * @param array<int,array<string,mixed>> $auditRows Sortie de rows()
     */
    public function writeCsv(array $auditRows, string $path): string
    {
        $fh = fopen($path, 'w');
        if ($fh === false) {
            throw new RuntimeException("Impossible d'écrire le CSV d'audit : $path");
        }
        fwrite($fh, "\xEF\xBB\xBF"); // BOM -> accents corrects dans Excel

        $width = Text::refWidth($auditRows === [] ? 0 : (int) end($auditRows)['id']);

        fputcsv($fh, [
            'ref', 'id', 'date', 'user_id', 'question', 'reponse', 'longueur_reponse',
            'erreur_source', 'non_reponse', 'regle_non_reponse', 'intention', 'sujet',
            'qualite_estimee', 'hallucination_estimee',
            'lot_classification', 'statut_ia_classification', 'brut_ia_classification',
            'lot_qualite', 'statut_ia_qualite', 'brut_ia_qualite',
        ], ';', '"', '');

        foreach ($auditRows as $a) {
            fputcsv($fh, [
                Text::ref($a['id'], $width), $a['id'], $this->csvSafe($a['date']), $this->csvSafe($a['user_id']),
                $this->csvSafe($this->flatten($a['question'])), $this->csvSafe($this->flatten($a['reponse'])), $a['rep_len'],
                $this->csvSafe($this->flatten($a['erreur_source'])),
                $a['non_reponse'] ? 'oui' : 'non', $a['regle'], $a['intent'], $a['topic'],
                $a['quality'], $a['hallucination'] ? 'oui' : 'non',
                $a['lot_c'] ?? '', $a['statut_c'], $this->csvSafe($this->flatten($a['brut_c'])),
                $a['lot_q'] ?? '', $a['statut_q'], $this->csvSafe($this->flatten($a['brut_q'])),
            ], ';', '"', '');
        }
        fclose($fh);
        return $path;
    }

    /**
     * Neutralise l'injection de formule tableur (OWASP) : une cellule commençant
     * par = + - @ (ou tab/CR) serait ÉVALUÉE par Excel/LibreOffice à l'ouverture.
     * Le préfixe apostrophe la force en texte ; il est documenté en §8 du PDF.
     */
    private function csvSafe(string $s): string
    {
        return ($s !== '' && in_array($s[0], ['=', '+', '-', '@', "\t", "\r"], true)) ? "'" . $s : $s;
    }

    /**
     * Prépare TOUTES les données du document de traçabilité (templates/audit.php).
     *
     * @param array<int,array<string,mixed>> $auditRows  Sortie de rows() (pas de double calcul)
     * @param array<string,mixed>            $stats      Sortie de Stats::compute()
     * @param array<string,mixed>            $reportData Sortie de ReportData::build() (agrégats AVEC ids)
     * @param RunTrace|null                  $trace
     * @param array<string,mixed>            $sourceMeta ['file','bytes','sha256','token']
     * @return array<string,mixed>
     */
    public function buildPdfData(
        array $auditRows,
        array $stats,
        array $reportData,
        ?RunTrace $trace,
        array $sourceMeta,
        string $csvName
    ): array {
        $total = count($auditRows);
        $traceCfg = $this->config['trace'] ?? [];
        $refWidth = (int) ($reportData['ref_width'] ?? 3);
        $aiEnabled = (bool) ($reportData['ai_enabled'] ?? false);

        // --- Éléments signalés (non-réponses, hallucinations, corrections IA) ---
        $nonResponses = [];
        $hallucinations = [];
        $corrections = [];
        foreach ($auditRows as $a) {
            if ($a['non_reponse']) {
                $nonResponses[] = [
                    'id' => $a['id'], 'date' => $a['date'], 'question' => $a['question'],
                    'reponse' => $a['reponse'], 'regle' => $a['regle'],
                ];
            }
            if ($a['hallucination']) {
                $hallucinations[] = [
                    'id' => $a['id'], 'date' => $a['date'], 'question' => $a['question'],
                    'reponse' => $a['reponse'], 'lot' => $a['lot_q'],
                ];
            }
            // Divergences brut IA -> valeur retenue (preuve que rien n'est accepté hors liste).
            if (in_array($a['statut_c'], ['corrige', 'absent_reponse'], true)) {
                $corrections[] = ['id' => $a['id'], 'phase' => 'classification', 'lot' => $a['lot_c'],
                                  'motif' => $a['statut_c'], 'brut' => $a['brut_c'],
                                  'retenu' => $a['intent'] . ' · ' . $a['topic']];
            }
            if (in_array($a['statut_q'], ['corrige', 'absent_reponse'], true)) {
                $corrections[] = ['id' => $a['id'], 'phase' => 'qualité', 'lot' => $a['lot_q'],
                                  'motif' => $a['statut_q'], 'brut' => $a['brut_q'],
                                  'retenu' => $a['quality'] . ($a['hallucination'] ? ' · hallucination=oui' : '')];
            }
        }

        $csvCtx = $trace?->context()['csv'] ?? null;

        return [
            'generated_at'   => (new DateTimeImmutable())->format('d/m/Y H:i'),
            'title'          => 'Document de traçabilité',
            'subtitle'       => $this->config['report']['app_name'],
            'token'          => (string) ($sourceMeta['token'] ?? ''),
            'ai_enabled'     => $aiEnabled,
            'ai_params'      => $this->aiParams(),
            'source'         => $sourceMeta,
            'csv_info'       => [
                'mapping'              => $csvCtx['mapping'] ?? [],
                'delimiter'            => $csvCtx['delimiter'] ?? '',
                'skipped_lines'        => (int) ($csvCtx['skipped_lines'] ?? 0),
                'skipped_line_numbers' => $csvCtx['skipped_line_numbers'] ?? [],
            ],
            'period'         => $stats['period'],
            'total_messages' => $total,
            'ref_width'      => $refWidth,
            'anchors_enabled' => $total <= (int) ($traceCfg['max_anchors'] ?? 3000),
            'refs_inline_max' => (int) ($traceCfg['refs_inline_max'] ?? 30),
            'pipeline'       => $this->pipeline($total, $aiEnabled),
            'methodology'    => $this->methodology($stats, $reportData, $total, $hallucinations),
            'aggregates'     => [
                'topics'  => $reportData['topics'],
                'intents' => $reportData['intents'],
                'quality' => $reportData['quality'],
                'gaps'    => $reportData['gaps'],
            ],
            'non_responses'  => $nonResponses,
            'hallucinations' => $hallucinations,
            'ai'             => [
                'enabled'     => $aiEnabled,
                'summary'     => $trace?->summary() ?? [],
                'components'  => $trace?->components() ?? [],
                'batches'     => $trace?->batches() ?? [],
                'corrections' => $corrections,
            ],
            'taxonomies'     => [
                'intents' => $this->config['intents'],
                'topics'  => $this->config['topics'],
                'quality' => $this->config['quality_labels'],
            ],
            'registry_groups' => $this->registryGroups($auditRows, $traceCfg, $refWidth),
            'codes_legend'    => $this->codesLegend(),
            'csv_name'        => $csvName,
        ];
    }

    /** Paramètres IA affichables (sans clé API). @return array<string,mixed> */
    private function aiParams(): array
    {
        $ai = $this->config['ai'];
        return [
            'model'           => (string) $ai['model'],
            'endpoint_host'   => (string) (parse_url((string) $ai['endpoint'], PHP_URL_HOST) ?: $ai['endpoint']),
            'temperature'     => $ai['temperature'],
            'batch_size'      => (int) $ai['batch_size'],
            'max_field_chars' => (int) ($ai['max_field_chars'] ?? 0),
            'timeout'         => (int) $ai['timeout'],
            'max_retries'     => (int) $ai['max_retries'],
            'retry_delay'     => (int) $ai['retry_delay'],
        ];
    }

    /**
     * Chaîne de traitement : les 6 étapes du pipeline, avec où vérifier chacune.
     *
     * @return array<int,array{step:string,in:string,out:string,nature:string,verif:string}>
     */
    private function pipeline(int $total, bool $aiEnabled): array
    {
        $iaNature = $aiEnabled ? 'Estimé (IA, listes fermées)' : 'Désactivé (tout « Non classé »)';
        return [
            ['step' => '1. Lecture du fichier CSV',
             'in' => 'Fichier source', 'out' => "$total messages normalisés, références #",
             'nature' => 'Déterministe', 'verif' => '§2'],
            ['step' => '2. Statistiques factuelles',
             'in' => 'Les ' . $total . ' messages', 'out' => 'Volumétrie, période, activité, non-réponses (règles)',
             'nature' => 'Déterministe', 'verif' => '§3 et §5'],
            ['step' => '3. Classification IA (intention + sujet)',
             'in' => 'Questions, par lots', 'out' => 'Une étiquette par liste fermée, par message',
             'nature' => $iaNature, 'verif' => '§7'],
            ['step' => '4. Jugement qualité IA',
             'in' => 'Paires question/réponse, par lots', 'out' => 'Étiquette qualité + signal hallucination',
             'nature' => $iaNature, 'verif' => '§7'],
            ['step' => '5. Agrégation des étiquettes',
             'in' => 'Étiquettes par message', 'out' => 'Effectifs et pourcentages (comptage)',
             'nature' => 'Déterministe', 'verif' => '§4'],
            ['step' => '6. Rendu des documents',
             'in' => 'Données calculées', 'out' => 'Rapport PDF + ce document + CSV de traçabilité',
             'nature' => 'Déterministe', 'verif' => '—'],
        ];
    }

    /**
     * Méthodologie : chaque chiffre du rapport, sa formule, sa nature et ses sources.
     *
     * @param array<int,array<string,mixed>> $hallucinations
     * @return array<int,array<string,mixed>>
     */
    private function methodology(array $stats, array $reportData, int $total, array $hallucinations): array
    {
        $minLen = (int) $this->config['non_response_rules']['min_length'];
        $eng = $reportData['engagement'];
        $undated = $stats['undated_ids'] ?? [];

        $m = [
            ['indicator' => 'Messages analysés', 'value' => (string) $total,
             'formula' => 'Nombre de lignes de données du fichier source.',
             'nature' => 'fact', 'ids' => null, 'ids_note' => 'Tous — registre §9.'],
            ['indicator' => 'Visiteurs uniques', 'value' => (string) $stats['unique_users'],
             'formula' => 'Nombre de valeurs distinctes de la colonne identifiant (user_id).',
             'nature' => 'fact', 'ids' => null, 'ids_note' => 'Colonne « Visiteur » du registre §9.'],
            ['indicator' => 'Questions par visiteur', 'value' => (string) $stats['messages_per_user'],
             'formula' => $total . ' messages ÷ ' . $stats['unique_users'] . ' visiteurs.',
             'nature' => 'fact', 'ids' => null, 'ids_note' => '—'],
            ['indicator' => 'Visiteurs revenus', 'value' => $eng['returning'] . ' (' . $eng['returning_rate'] . ' %)',
             'formula' => 'Visiteurs ayant posé plus d\'une question : ' . $eng['returning'] . ' ÷ ' . $stats['unique_users'] . '.',
             'nature' => 'fact', 'ids' => null, 'ids_note' => '—'],
            ['indicator' => 'Période couverte',
             'value' => ($stats['period']['start'] ?? 'n/d') . ' → ' . ($stats['period']['end'] ?? 'n/d'),
             'formula' => 'Date minimale et maximale de la colonne date.'
                        . ($undated !== [] ? ' ' . count($undated) . ' message(s) sans date exploitable, exclus des répartitions temporelles.' : ''),
             'nature' => 'fact', 'ids' => $undated !== [] ? $undated : null,
             'ids_note' => $undated !== [] ? 'Messages sans date :' : '—'],
            ['indicator' => 'Jours couverts',
             'value' => $stats['period']['span_days'] !== null ? (string) $stats['period']['span_days'] : 'n/d',
             'formula' => 'Nombre de jours calendaires entre la première et la dernière date (bornes incluses).',
             'nature' => 'fact', 'ids' => null, 'ids_note' => '—'],
            ['indicator' => 'Heure de pic',
             'value' => $this->peakLabel($stats['peak_hour'], static fn($k) => str_pad((string) $k, 2, '0', STR_PAD_LEFT) . 'h'),
             'formula' => 'Heure (0–23) totalisant le plus de messages parmi les ' . (int) $stats['timed_rows']
                        . ' messages dont le fichier précise l\'heure ; en cas d\'égalité, la première heure de la journée est retenue (le nombre d\'ex æquo est alors indiqué).'
                        . (($noTime = $stats['no_time_ids'] ?? []) !== [] ? ' ' . count($noTime) . ' message(s) datés sans heure, exclus de ce calcul.' : ''),
             'nature' => 'fact', 'ids' => $noTime !== [] ? $noTime : null,
             'ids_note' => $noTime !== [] ? 'Messages datés sans heure :' : '—'],
            ['indicator' => 'Jour le plus actif',
             'value' => $this->peakLabel($stats['peak_day'], static fn($k) => (new DateTimeImmutable((string) $k))->format('d/m/Y')),
             'formula' => 'Jour calendaire totalisant le plus de messages datés ; en cas d\'égalité, le premier jour (ordre chronologique) est retenu (le nombre d\'ex æquo est alors indiqué).',
             'nature' => 'fact', 'ids' => null, 'ids_note' => 'Colonne « Date » du registre §9.'],
            ['indicator' => 'Taux de non-réponse', 'value' => $stats['non_response']['rate'] . ' %',
             'formula' => $stats['non_response']['count'] . ' ÷ ' . $total
                        . ' messages. Règles : réponse vide, < ' . $minLen . ' caractères, ou formule de repli (détail §5).',
             'nature' => 'fact', 'ids' => $stats['non_response']['ids'], 'ids_note' => ''],
            ['indicator' => '« Exemples de réponses pertinentes »',
             'value' => 'liste (rapport)',
             'formula' => 'Les ' . (int) ($this->config['report']['best_n'] ?? 5) . ' premières réponses, dans l\'ordre du fichier, '
                        . 'étiquetées « Réponse pertinente » par l\'IA, sans signal d\'hallucination et qui ne sont PAS des non-réponses (§5). '
                        . 'Aucun classement de mérite : liste complète des éligibles dans le CSV (qualite_estimee = « Réponse pertinente », '
                        . 'hallucination_estimee = non, non_reponse = non).',
             'nature' => 'estim', 'ids' => null, 'ids_note' => 'Réfs affichées sur chaque carte du rapport.'],
        ];

        if (($stats['errors'] ?? 0) > 0) {
            $m[] = ['indicator' => 'Erreurs explicites', 'value' => (string) $stats['errors'],
                    'formula' => 'Lignes dont la colonne erreur vaut vrai (1/true/oui).',
                    'nature' => 'fact', 'ids' => $stats['error_ids'] ?? [], 'ids_note' => ''];
        }

        $m[] = ['indicator' => 'Hallucinations estimées', 'value' => (string) count($hallucinations),
                'formula' => 'Nombre d\'étiquettes « hallucination = oui » renvoyées par l\'IA, comptées en PHP (détail §6, à vérifier humainement).',
                'nature' => 'estim', 'ids' => array_column($hallucinations, 'id'), 'ids_note' => ''];

        return $m;
    }

    /**
     * Découpe le registre complet en groupes (1 table par groupe — indispensable
     * pour la mémoire mPDF) avec lignes pré-formatées pour l'affichage dense.
     *
     * @param array<int,array<string,mixed>> $auditRows
     * @param array<string,mixed> $traceCfg
     * @return array<int,array{from:int,to:int,anchor:string,rows:array<int,array<string,mixed>>}>
     */
    private function registryGroups(array $auditRows, array $traceCfg, int $refWidth): array
    {
        $chunk = max(10, (int) ($traceCfg['registry_chunk'] ?? 100));
        $qMax  = (int) ($traceCfg['registry_question_chars'] ?? 90);
        $rMax  = (int) ($traceCfg['registry_reponse_chars'] ?? 110);
        $codes = $traceCfg['codes'] ?? [];

        $groups = [];
        foreach (array_chunk($auditRows, $chunk) as $chunkRows) {
            $rows = [];
            foreach ($chunkRows as $a) {
                $rows[] = [
                    'id'       => $a['id'],
                    'ref'      => Text::ref($a['id'], $refWidth),
                    'date'     => $this->shortDate($a),
                    'user'     => Text::truncate($a['user_id'], 10),
                    'question' => $this->displayText($a['question'], $qMax),
                    'reponse'  => $this->displayText($a['reponse'], $rMax),
                    'rep_len'  => $a['rep_len'],
                    'nr'       => $this->nrCode($a),
                    'intent'   => $codes['intents'][$a['intent_key']] ?? '?',
                    'topic'    => $codes['topics'][$a['topic_key']] ?? '?',
                    'quality'  => $codes['quality'][$a['quality_key']] ?? '?',
                    'halluc'   => $a['hallucination'],
                    'lot_c'    => $a['lot_c'],
                    'lot_q'    => $a['lot_q'],
                    'statut'   => $this->iaStatusCode($a['statut_c'], $a['statut_q']),
                ];
            }
            $first = $chunkRows[0]['id'];
            $last  = $chunkRows[count($chunkRows) - 1]['id'];
            $groups[] = ['from' => $first, 'to' => $last, 'anchor' => 'reg-' . $first, 'rows' => $rows];
        }
        return $groups;
    }

    /** Légende complète des codes courts (registre §9). @return array<string,array<string,string>> */
    private function codesLegend(): array
    {
        $codes = $this->config['trace']['codes'] ?? [];
        $legend = [];
        foreach (['intents', 'topics', 'quality'] as $group) {
            foreach ($codes[$group] ?? [] as $key => $code) {
                $legend[$group][$code] = $this->label($group, $key);
            }
        }
        $legend['nr'] = ['—' => 'Vraie réponse', 'V' => 'Réponse vide', 'C' => 'Trop courte', 'F' => 'Formule de repli'];
        $legend['statut'] = [
            'OK'  => 'Étiquettes IA acceptées mot pour mot',
            'COR' => 'Valeur brute hors liste, corrigée en « Non classé » (§7.4)',
            'ABS' => 'Message absent de la réponse IA → « Non classé » (§7.3)',
            'ECH' => 'Lot IA en échec → « Non classé » (§7.3)',
            'OFF' => 'IA désactivée → « Non classé »',
        ];
        return $legend;
    }

    /** Libellé d'un pic ('peak_hour'/'peak_day' de Stats) avec identité des ex æquo. */
    private function peakLabel(?array $peak, callable $fmt): string
    {
        if ($peak === null) {
            return 'n/d';
        }
        $label = $fmt($peak['key']) . ' (' . $peak['count'] . ' messages)';
        if ($peak['ties'] > 1) {
            $others = array_map($fmt, array_slice($peak['tie_keys'], 1));
            $label .= ' — ex æquo avec ' . implode(', ', $others);
        }
        return $label;
    }

    /** Code court de la règle de non-réponse (V/C/F ou —). */
    private function nrCode(array $a): string
    {
        if (!$a['non_reponse']) {
            return '—';
        }
        $r = (string) $a['regle'];
        if (str_starts_with($r, 'Réponse vide')) {
            return 'V';
        }
        if (str_starts_with($r, 'Réponse trop courte')) {
            return 'C';
        }
        return 'F';
    }

    /** Code combiné du statut IA des deux phases (le plus défavorable l'emporte). */
    private function iaStatusCode(string $statutC, string $statutQ): string
    {
        $rank = ['lot_echec' => 4, 'absent_reponse' => 3, 'corrige' => 2, 'ok' => 1, 'ia_desactivee' => 0, '' => -1];
        $worst = ($rank[$statutC] ?? -1) >= ($rank[$statutQ] ?? -1) ? $statutC : $statutQ;
        return match ($worst) {
            'ok'             => 'OK',
            'corrige'        => 'COR',
            'absent_reponse' => 'ABS',
            'lot_echec'      => 'ECH',
            'ia_desactivee'  => 'OFF',
            default          => '—',
        };
    }

    /** Date courte pour le registre (jj/mm hh:mm), repli sur le brut tronqué. */
    private function shortDate(array $a): string
    {
        if ($a['date_obj'] instanceof DateTimeImmutable) {
            return $a['date_obj']->format('d/m H:i');
        }
        return $a['date'] !== '' ? Text::truncate($a['date'], 14) : '—';
    }

    /**
     * Texte prêt pour le PDF : espaces compactés, URLs remplacées par [lien]
     * (mPDF ne coupe pas les chaînes insécables), troncature honnête (…).
     * Le texte INTÉGRAL reste dans le CSV de traçabilité.
     */
    private function displayText(string $s, int $max): string
    {
        $s = (string) preg_replace('~https?://\S+~u', '[lien]', $s);
        $s = $this->flatten($s);
        return $s === '' ? '' : Text::truncate($s, $max);
    }

    /** Aplati les espaces/retours ligne d'un texte pour garder 1 message = 1 ligne CSV. */
    private function flatten(string $s): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $s));
    }

    private function label(string $group, string $key): string
    {
        return $this->config['labels'][$group][$key] ?? ucfirst(str_replace('_', ' ', $key));
    }
}
