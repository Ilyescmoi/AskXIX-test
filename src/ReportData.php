<?php

/**
 * ReportData — fusionne les statistiques factuelles (Stats) et les étiquettes IA
 * agrégées, et produit des analyses décisionnelles (synthèse, lacunes par sujet,
 * engagement) destinées à une lecture rapide par une équipe commerciale.
 *
 * RAPPEL CRITIQUE : tous les pourcentages et toutes les phrases de synthèse sont
 * calculés ICI, en PHP, par comptage. L'IA ne fournit JAMAIS un chiffre ni une phrase.
 *
 * TRAÇABILITÉ : chaque structure produite porte les ids des messages sources
 * ('ids', 'no_answer_ids', 'id'…). Ce sont CES listes que le document de
 * traçabilité réutilise telles quelles — un seul comptage, aucune divergence
 * possible entre le rapport et sa justification.
 */
class ReportData
{
    /** @param array<string,mixed> $config Configuration complète (config.php) */
    public function __construct(private array $config) {}

    /**
     * @param array<int,array<string,mixed>> $rows
     * @param array<string,mixed> $stats
     * @param array<int,array{intent:string,topic:string}> $labels
     * @param array<int,array{quality:string,hallucination:bool}> $quality
     * @param RunTrace|null $trace Journal d'exécution (signale les lots IA en échec)
     * @param array<string,mixed> $sourceMeta Identité du fichier source (file, bytes, sha256, token)
     * @return array<string,mixed>
     */
    public function build(
        array $rows,
        array $stats,
        array $labels,
        array $quality,
        bool $aiEnabled,
        ?RunTrace $trace = null,
        array $sourceMeta = []
    ): array {
        $total = max(1, count($rows));

        $intentAgg  = $this->aggregate($labels, 'intent', 'intents', $this->config['intents'], $total);
        $topicAgg   = $this->aggregate($labels, 'topic', 'topics', $this->config['topics'], $total);
        $qualityAgg = $this->aggregate($quality, 'quality', 'quality', $this->config['quality_labels'], $total);

        // Hallucinations : comptage PHP sur le booléen renvoyé par l'IA, ids conservés.
        $hallucinationIds = [];
        foreach ($quality as $id => $q) {
            if (!empty($q['hallucination'])) {
                $hallucinationIds[] = (int) $id;
            }
        }
        $hallucination = [
            'count' => count($hallucinationIds),
            'rate'  => round(100 * count($hallucinationIds) / $total, 1),
            'ids'   => $hallucinationIds,
        ];

        // Analyse croisée : non-réponse (factuelle) par sujet (étiquette IA) = lacunes d'info.
        $gaps = $this->nonResponseByTopic($rows, $labels, $stats['non_response']['ids']);

        $engagement = [
            'unique'         => (int) $stats['unique_users'],
            'returning'      => (int) ($stats['returning_users'] ?? 0),
            'single'         => (int) ($stats['single_question_users'] ?? 0),
            'avg_per_user'   => $stats['messages_per_user'],
            'returning_rate' => $stats['unique_users'] > 0
                ? round(100 * ($stats['returning_users'] ?? 0) / $stats['unique_users'], 1)
                : 0.0,
        ];

        // Problèmes IA remontés par la trace (lots en échec = messages non analysés).
        $aiIssues = $this->aiIssues($trace);

        $highlights = $this->buildHighlights($stats, $topicAgg, $intentAgg, $hallucination, $gaps, $aiEnabled, $aiIssues);

        // Largeur des références #NNN (ids 1-based contigus -> le max est le dernier).
        $maxId = 0;
        foreach ($rows as $r) {
            $maxId = max($maxId, (int) $r['id']);
        }

        return [
            'generated_at'  => (new DateTimeImmutable())->format('d/m/Y H:i'),
            'app_name'      => $this->config['report']['app_name'],
            'subtitle'      => $this->config['report']['subtitle'] ?? '',
            'ai_enabled'    => $aiEnabled,
            'ref_width'     => Text::refWidth($maxId),
            'refs_max'      => (int) ($this->config['trace']['refs_report_max'] ?? 6),
            'source'        => $sourceMeta, // ['file','bytes','sha256','token']
            'stats'         => $stats,
            'engagement'    => $engagement,
            'highlights'    => $highlights,
            'intents'       => $intentAgg,
            'topics'        => $topicAgg,
            'quality'       => $qualityAgg,
            'hallucination' => $hallucination,
            'gaps'          => $gaps,
            'ai_issues'     => $aiIssues,
            'best_n'        => (int) ($this->config['report']['best_n'] ?? 5),
            'top_questions' => $this->topQuestions($rows, $labels),
            'unanswered'    => $this->unanswered($rows, $labels, $stats['non_response']),
            // « Meilleures réponses » : on EXCLUT les non-réponses factuelles (une
            // formule de repli jugée « cohérente » par l'IA ne doit jamais être un exemple).
            'best_answers'  => $this->bestAnswers($rows, $quality, $stats['non_response']['ids']),
            // Nb total d'éligibles (le rapport n'en montre que best_n).
            'best_answers_eligible' => $this->countBestEligible($rows, $quality, $stats['non_response']['ids']),
        ];
    }

    /**
     * Compte chaque étiquette et renvoie effectifs + pourcentages + libellé lisible
     * + ids des messages sources de chaque ligne (preuve recalculable).
     *
     * @param array<int,array<string,mixed>> $byId map id message => étiquettes
     * @param string   $field   champ à agréger (intent|topic|quality)
     * @param string   $group   groupe de libellés (intents|topics|quality)
     * @param string[] $allowed univers fermé des étiquettes possibles
     * @return array<int,array{key:string,name:string,count:int,percent:float,ids:int[]}>
     */
    private function aggregate(array $byId, string $field, string $group, array $allowed, int $total): array
    {
        $counts = array_fill_keys($allowed, 0);
        $idsByLabel = array_fill_keys($allowed, []);
        foreach ($byId as $id => $entry) {
            $v = (string) ($entry[$field] ?? 'indetermine');
            if (!array_key_exists($v, $counts)) {
                $v = 'indetermine';
            }
            $counts[$v]++;
            $idsByLabel[$v][] = (int) $id;
        }

        $out = [];
        foreach ($counts as $key => $count) {
            if ($count === 0) {
                continue;
            }
            $out[] = [
                'key'     => $key,
                'name'    => $this->labelFor($group, $key),
                'count'   => $count,
                'percent' => round(100 * $count / $total, 1),
                'ids'     => $idsByLabel[$key],
            ];
        }
        // Tri par effectif, mais les catégories fourre-tout (autre/indéterminé) en dernier.
        usort($out, fn($a, $b) =>
            [$this->isCatchAll($a['key']), -$a['count']] <=> [$this->isCatchAll($b['key']), -$b['count']]);
        return $out;
    }

    /**
     * Croise les non-réponses FACTUELLES avec le sujet (IA) de chaque message.
     * Met en évidence les thèmes où l'assistant échoue le plus à répondre.
     *
     * @param array<int,array<string,mixed>> $rows
     * @param array<int,array{intent:string,topic:string}> $labels
     * @param int[] $nonRespIds
     * @return array<int,array{key:string,name:string,count:int,no_answer:int,rate:float,no_answer_ids:int[]}>
     */
    private function nonResponseByTopic(array $rows, array $labels, array $nonRespIds): array
    {
        $nonSet = array_flip($nonRespIds);

        $perTopic = [];
        foreach ($rows as $r) {
            $t = $labels[$r['id']]['topic'] ?? 'indetermine';
            if (!isset($perTopic[$t])) {
                $perTopic[$t] = ['count' => 0, 'no_answer' => 0, 'no_answer_ids' => []];
            }
            $perTopic[$t]['count']++;
            if (isset($nonSet[$r['id']])) {
                $perTopic[$t]['no_answer']++;
                $perTopic[$t]['no_answer_ids'][] = (int) $r['id'];
            }
        }

        $out = [];
        foreach ($perTopic as $key => $d) {
            $out[] = [
                'key'           => $key,
                'name'          => $this->labelFor('topics', $key),
                'count'         => $d['count'],
                'no_answer'     => $d['no_answer'],
                'rate'          => $d['count'] > 0 ? round(100 * $d['no_answer'] / $d['count'], 1) : 0.0,
                'no_answer_ids' => $d['no_answer_ids'],
            ];
        }
        // Tri : sujets réels d'abord (fourre-tout en dernier), puis par non-réponses et taux.
        usort($out, fn($a, $b) =>
            [$this->isCatchAll($a['key']), -$a['no_answer'], -$a['rate']]
            <=> [$this->isCatchAll($b['key']), -$b['no_answer'], -$b['rate']]);
        return $out;
    }

    /**
     * Synthétise les anomalies IA visibles dans la trace, VENTILÉES PAR PHASE :
     * un lot de classification en échec laisse le sujet/l'intention « Non classé »
     * mais ne dit RIEN de la qualité (et réciproquement) — fusionner les deux
     * rendrait le rapport mensonger.
     *
     * @return array{failed_classification:int[],failed_quality:int[],corrected:int,missing:int}
     */
    private function aiIssues(?RunTrace $trace): array
    {
        if ($trace === null) {
            return ['failed_classification' => [], 'failed_quality' => [], 'corrected' => 0, 'missing' => 0];
        }
        $summary = $trace->summary();
        return [
            'failed_classification' => $trace->failedBatchIds('classification'),
            'failed_quality'        => $trace->failedBatchIds('qualite'),
            'corrected'             => (int) $summary['corrected'],
            'missing'               => (int) $summary['missing'],
        ];
    }

    /**
     * Construit la synthèse « En bref » : des phrases courtes générées à partir des
     * chiffres (déterministe). Chaque point porte un type info|good|warning|risk,
     * les ids des messages sources et la section du document de traçabilité où
     * vérifier le chiffre.
     *
     * @param array{failed_ids:int[],corrected:int,missing:int} $aiIssues
     * @return array<int,array{type:string,label:string,text:string,ids:int[],annexe:string}>
     */
    private function buildHighlights(
        array $stats,
        array $topicAgg,
        array $intentAgg,
        array $hallucination,
        array $gaps,
        bool $aiEnabled,
        array $aiIssues
    ): array {
        $h = [];
        $nonRate = (float) $stats['non_response']['rate'];

        if ($aiEnabled) {
            // 1. Sujet le plus demandé (on ignore "autre"/"indetermine" — et on le DIT,
            //    sinon le point contredit visuellement le tableau des sujets).
            foreach ($topicAgg as $row) {
                if (in_array($row['key'], ['autre', 'indetermine'], true)) {
                    continue;
                }
                $h[] = ['type' => 'info', 'label' => 'Sujet métier le plus demandé',
                        'text' => "{$row['name']} — {$row['percent']} % des questions ({$row['count']} messages), "
                                . "hors catégories génériques « Autres sujets » et « Non classé ».",
                        'ids' => $row['ids'], 'annexe' => '§4'];
                break;
            }

            // 2. Principale lacune d'information (sujet RÉEL avec le plus de non-réponses).
            foreach ($gaps as $g) {
                if ($g['no_answer'] <= 0 || $this->isCatchAll($g['key'])) {
                    continue;
                }
                $h[] = ['type' => 'warning', 'label' => 'Principale lacune',
                        'text' => "« {$g['name']} » : {$g['no_answer']} question(s) sans réponse ({$g['rate']} %).",
                        'ids' => $g['no_answer_ids'], 'annexe' => '§5'];
                break;
            }
        }

        // 3. Taux global de non-réponse (factuel, toujours présent).
        $type = $nonRate >= 20 ? 'risk' : ($nonRate >= 10 ? 'warning' : 'good');
        $h[] = ['type' => $type, 'label' => 'Taux de non-réponse',
                'text' => "{$nonRate} % des messages restent sans réponse exploitable "
                        . "({$stats['non_response']['count']} sur {$stats['total_messages']}).",
                'ids' => $stats['non_response']['ids'], 'annexe' => '§5'];

        if ($aiEnabled) {
            // 4. Risque d'hallucination.
            if ($hallucination['count'] > 0) {
                $h[] = ['type' => 'risk', 'label' => 'Réponses à surveiller',
                        'text' => "{$hallucination['count']} réponse(s) potentiellement inventée(s) (estimé).",
                        'ids' => $hallucination['ids'], 'annexe' => '§6'];
            }

            // 5. Hors-sujet notable.
            $hs = $this->findRow($intentAgg, 'hors_sujet');
            if ($hs !== null && $hs['percent'] >= 5) {
                $h[] = ['type' => 'info', 'label' => 'Hors sujet',
                        'text' => "{$hs['percent']} % des questions sont hors sujet.",
                        'ids' => $hs['ids'], 'annexe' => '§4'];
            }

            // 6. Demande de visuels (signal commercial : intérêt fort).
            $vis = $this->findRow($intentAgg, 'demande_visuel');
            if ($vis !== null && $vis['percent'] >= 5) {
                $h[] = ['type' => 'good', 'label' => 'Appétence visuels',
                        'text' => "{$vis['percent']} % des échanges réclament un visuel (perspective, brochure).",
                        'ids' => $vis['ids'], 'annexe' => '§4'];
            }

            // 7. Lots IA en échec, par phase : un échec de classification laisse
            //    sujet/intention « Non classé », un échec de qualité laisse la
            //    qualité « Non évalué » — sans rien dire de l'autre phase.
            if ($aiIssues['failed_classification'] !== []) {
                $n = count($aiIssues['failed_classification']);
                $h[] = ['type' => 'risk', 'label' => 'Classification incomplète',
                        'text' => "$n message(s) sans sujet ni intention (lot de classification en échec) : "
                                . "comptés « Non classé » dans les tableaux Sujets et Intentions.",
                        'ids' => $aiIssues['failed_classification'], 'annexe' => '§7'];
            }
            if ($aiIssues['failed_quality'] !== []) {
                $n = count($aiIssues['failed_quality']);
                $h[] = ['type' => 'risk', 'label' => 'Jugement qualité incomplet',
                        'text' => "$n message(s) sans jugement de qualité (lot qualité en échec) : "
                                . "comptés « Non évalué » dans le tableau Qualité.",
                        'ids' => $aiIssues['failed_quality'], 'annexe' => '§7'];
            }
        }

        return $h;
    }

    /** @return array{key:string,name:string,count:int,percent:float,ids:int[]}|null */
    private function findRow(array $agg, string $key): ?array
    {
        foreach ($agg as $row) {
            if ($row['key'] === $key) {
                return $row;
            }
        }
        return null;
    }

    /**
     * Top des questions les plus fréquentes (regroupement normalisé), avec leur sujet
     * et les ids de TOUTES les occurrences regroupées.
     *
     * @param array<int,array<string,mixed>> $rows
     * @param array<int,array{intent:string,topic:string}> $labels
     * @return array<int,array{question:string,count:int,topic:string,ids:int[]}>
     */
    private function topQuestions(array $rows, array $labels): array
    {
        $groups = [];
        foreach ($rows as $r) {
            $q = trim((string) $r['question']);
            if ($q === '') {
                continue;
            }
            $key = Text::normalize($q);
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'question' => $q,
                    'count'    => 0,
                    'topic'    => $this->labelFor('topics', $labels[$r['id']]['topic'] ?? 'indetermine'),
                    'ids'      => [],
                ];
            }
            $groups[$key]['count']++;
            $groups[$key]['ids'][] = (int) $r['id'];
        }
        $list = array_values($groups);
        usort($list, static fn($a, $b) => $b['count'] <=> $a['count']);
        return array_slice($list, 0, (int) $this->config['report']['top_n']);
    }

    /**
     * Questions sans réponse (règles factuelles), enrichies de leur sujet, de leur id
     * et de la règle exacte qui les a déclenchées.
     *
     * @param array<int,array<string,mixed>> $rows
     * @param array<int,array{intent:string,topic:string}> $labels
     * @param array{count:int,rate:float,ids:int[],reasons:array<int,string>} $nonResponse
     * @return array<int,array{id:int,question:string,reponse:string,topic:string,regle:string,date:string}>
     */
    private function unanswered(array $rows, array $labels, array $nonResponse): array
    {
        $byId = $this->indexById($rows);
        $list = [];
        foreach ($nonResponse['ids'] as $id) {
            if (!isset($byId[$id])) {
                continue;
            }
            $q = trim((string) $byId[$id]['question']);
            if ($q === '') {
                continue;
            }
            $list[] = [
                'id'       => (int) $id,
                'question' => $q,
                'reponse'  => trim((string) $byId[$id]['reponse']),
                'topic'    => $this->labelFor('topics', $labels[$id]['topic'] ?? 'indetermine'),
                'regle'    => $nonResponse['reasons'][$id] ?? '',
                'date'     => (string) $byId[$id]['date_raw'],
            ];
            if (count($list) >= (int) $this->config['report']['top_n']) {
                break;
            }
        }
        return $list;
    }

    /**
     * Meilleures réponses : qualité "coherente", sans hallucination (étiquettes IA)
     * ET qui ne sont pas des non-réponses factuelles (cohérence avec les règles).
     *
     * @param array<int,array<string,mixed>> $rows
     * @param array<int,array{quality:string,hallucination:bool}> $quality
     * @param int[] $nonResponseIds
     * @return array<int,array{id:int,question:string,reponse:string}>
     */
    private function bestAnswers(array $rows, array $quality, array $nonResponseIds): array
    {
        $byId = $this->indexById($rows);
        $nonSet = array_flip($nonResponseIds);
        $best_n = (int) ($this->config['report']['best_n'] ?? 5);
        $list = [];
        foreach ($quality as $id => $q) {
            if (!$this->isBestEligible($id, $q, $byId, $nonSet)) {
                continue;
            }
            $list[] = [
                'id'       => (int) $id,
                'question' => trim((string) $byId[$id]['question']),
                'reponse'  => trim((string) $byId[$id]['reponse']),
            ];
            if (count($list) >= $best_n) {
                break;
            }
        }
        return $list;
    }

    /**
     * Nombre total de réponses éligibles « meilleures réponses » (même critère
     * que bestAnswers, sans le plafond) — affiché dans le rapport pour que la
     * sélection soit honnête (« les N premières des X éligibles »).
     *
     * @param int[] $nonResponseIds
     */
    private function countBestEligible(array $rows, array $quality, array $nonResponseIds): int
    {
        $byId = $this->indexById($rows);
        $nonSet = array_flip($nonResponseIds);
        $n = 0;
        foreach ($quality as $id => $q) {
            if ($this->isBestEligible($id, $q, $byId, $nonSet)) {
                $n++;
            }
        }
        return $n;
    }

    /**
     * Critère unique d'éligibilité aux « réponses pertinentes » (partagé pour
     * garantir que le compte et la liste affichée utilisent la MÊME règle).
     *
     * @param array<int,array<string,mixed>> $byId
     * @param array<int,int> $nonSet ids de non-réponse (set)
     */
    private function isBestEligible(int|string $id, array $q, array $byId, array $nonSet): bool
    {
        if (($q['quality'] ?? '') !== 'coherente' || !empty($q['hallucination'])) {
            return false;
        }
        if (isset($nonSet[$id]) || !isset($byId[$id])) {
            return false;
        }
        return trim((string) $byId[$id]['question']) !== ''
            && trim((string) $byId[$id]['reponse']) !== '';
    }

    /** Catégorie "fourre-tout" (à reléguer en fin de classement). */
    private function isCatchAll(string $key): bool
    {
        return in_array($key, ['autre', 'indetermine'], true);
    }

    /** Traduit une clé technique en libellé lisible via config['labels']. */
    private function labelFor(string $group, string $key): string
    {
        $map = $this->config['labels'][$group] ?? [];
        return $map[$key] ?? ucfirst(str_replace('_', ' ', $key));
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private function indexById(array $rows): array
    {
        $byId = [];
        foreach ($rows as $r) {
            $byId[$r['id']] = $r;
        }
        return $byId;
    }
}
