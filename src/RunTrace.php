<?php

/**
 * RunTrace — journal de bord d'une génération de rapport.
 *
 * Collecteur PASSIF : il enregistre le contexte d'exécution, chaque appel IA
 * (lot par lot, tentative HTTP par tentative HTTP) et la provenance de chaque
 * étiquette (valeur brute renvoyée par le modèle vs valeur retenue après
 * validation en liste fermée). C'est lui qui permet au document de traçabilité
 * de répondre à « comment a fonctionné le raisonnement, sur quels messages ».
 *
 * Garanties :
 *  - ne stocke JAMAIS le texte des messages (seulement des ids) ni la clé API ;
 *  - ne lève JAMAIS d'exception (un appel hors séquence est un no-op) ;
 *  - trace absente (null) acceptée partout : le pipeline reste inchangé.
 */
class RunTrace
{
    /** @var array<string,mixed> Contexte d'exécution (source, CSV, paramètres IA). */
    private array $context = ['ai_enabled' => false];

    /** @var array<string,array{system:string,template:string,prompt_file:string}> Prompts complets par phase. */
    private array $components = [];

    /** @var array<int,array<string,mixed>> Lots IA dans l'ordre chronologique. */
    private array $batches = [];

    /** @var array<int,array<string,array<string,mixed>>> id message => phase => provenance. */
    private array $byMessage = [];

    /** @var int|null Index du lot actuellement ouvert dans $batches, sinon null. */
    private ?int $openBatch = null;

    /** @var array<string,int> Compteur de lots par phase (numérotation 1..N). */
    private array $batchCounter = [];

    /** @param array<string,mixed> $traceConfig Sous-tableau 'trace' de config.php */
    public function __construct(private array $traceConfig = []) {}

    // ------------------------------------------------------------------
    // Contexte global (alimenté par generate.php)
    // ------------------------------------------------------------------

    public function setAiEnabled(bool $on): void
    {
        $this->context['ai_enabled'] = $on;
    }

    public function aiEnabled(): bool
    {
        return (bool) $this->context['ai_enabled'];
    }

    /** Identité du fichier source analysé (preuve que tous les documents parlent du même fichier). */
    public function setSource(string $fileName, int $bytes, string $sha256): void
    {
        $this->context['source'] = ['file' => $fileName, 'bytes' => $bytes, 'sha256' => $sha256];
    }

    /**
     * Résultat de la lecture CSV : mapping colonnes, volumétrie, séparateur.
     *
     * @param array<string,?string> $mapping champ canonique => colonne d'origine (ou null)
     * @param int[] $skippedLineNumbers numéros de ligne FICHIER des lignes vides ignorées
     */
    public function setCsv(array $mapping, int $rowCount, string $delimiter, array $skippedLineNumbers = []): void
    {
        $this->context['csv'] = [
            'mapping'              => $mapping,
            'rows'                 => $rowCount,
            'delimiter'            => $delimiter,
            'skipped_lines'        => count($skippedLineNumbers),
            'skipped_line_numbers' => $skippedLineNumbers,
        ];
    }

    /**
     * Paramètres IA SANS la clé API (modèle, endpoint, température, lot, retries).
     *
     * @param array<string,mixed> $aiConfig Sous-tableau 'ai' de config.php
     */
    public function setAiParams(array $aiConfig): void
    {
        unset($aiConfig['api_key']);
        $this->context['ai'] = $aiConfig;
    }

    /** @return array<string,mixed> */
    public function context(): array
    {
        return $this->context;
    }

    // ------------------------------------------------------------------
    // Cycle de vie d'un lot IA (Classifier / QualityJudge / MistralClient)
    // ------------------------------------------------------------------

    /** Enregistre UNE fois le prompt système + le gabarit complet d'une phase. */
    public function registerComponent(string $phase, string $system, string $template, string $promptFile): void
    {
        $this->components[$phase] = [
            'system'      => $system,
            'template'    => $template,
            'prompt_file' => $promptFile,
        ];
    }

    /**
     * Ouvre un lot : la phase, les ids exacts envoyés et le prompt rendu.
     * Un lot resté ouvert (refus 4xx avant endBatch/failBatch) est auto-clos en échec.
     *
     * @param int[] $ids
     */
    public function beginBatch(string $phase, array $ids, string $prompt): void
    {
        if ($this->openBatch !== null) {
            $this->failBatch('lot précédent resté ouvert (clôture automatique)');
        }
        $this->batchCounter[$phase] = ($this->batchCounter[$phase] ?? 0) + 1;
        $this->batches[] = [
            'phase'        => $phase,
            'index'        => $this->batchCounter[$phase],
            'ids'          => array_map('intval', $ids),
            'prompt_chars' => mb_strlen($prompt, 'UTF-8'),
            'prompt_hash'  => substr(hash('sha256', $prompt), 0, 12),
            'attempts'     => [],
            'duration_ms'  => 0,
            'status'       => 'ouvert',
            'error'        => '',
            'response_excerpt' => '',
            'response_chars'   => 0,
            'returned'     => 0,
            'applied'      => 0,
            'corrected'    => 0,
            'missing_ids'  => [],
            'unknown_ids'  => [],
        ];
        $this->openBatch = array_key_last($this->batches);
    }

    /**
     * Une tentative HTTP du lot ouvert (notifiée par MistralClient).
     * $issue : succes | reessai_reseau | reessai_http | reponse_invalide | refus.
     */
    public function httpAttempt(int $attempt, int $httpCode, float $durationMs, string $curlError, string $issue): void
    {
        if ($this->openBatch === null) {
            return;
        }
        $this->batches[$this->openBatch]['attempts'][] = [
            'n'           => $attempt,
            'http'        => $httpCode,
            'ms'          => (int) round($durationMs),
            'erreur_curl' => $curlError,
            'issue'       => $issue,
        ];
        $this->batches[$this->openBatch]['duration_ms'] += (int) round($durationMs);
    }

    /**
     * Clôt un lot réussi : réponse brute + détail par id (brut / retenu / corrigé).
     *
     * @param array<int,array{brut:array<string,string>,retenu:array<string,mixed>,corrige:bool}> $perId
     * @param int[] $missingIds ids envoyés mais absents de la réponse (restés "indetermine")
     * @param array<int,mixed> $unknownIds ids renvoyés par l'IA mais inconnus du lot (rejetés)
     */
    public function endBatch(string $rawResponse, array $perId, array $missingIds, array $unknownIds): void
    {
        if ($this->openBatch === null) {
            return;
        }
        $b = &$this->batches[$this->openBatch];
        $excerptLen = (int) ($this->traceConfig['response_excerpt_chars'] ?? 400);
        $corrected = 0;
        foreach ($perId as $id => $d) {
            if (!empty($d['corrige'])) {
                $corrected++;
            }
            $this->byMessage[(int) $id][$b['phase']] = [
                'lot'    => $b['index'],
                'statut' => !empty($d['corrige']) ? 'corrige' : 'ok',
                'brut'   => !empty($d['corrige']) ? $this->compactRaw($d['brut']) : '',
            ];
        }
        foreach ($missingIds as $id) {
            $this->byMessage[(int) $id][$b['phase']] = ['lot' => $b['index'], 'statut' => 'absent_reponse', 'brut' => ''];
        }
        $b['status']           = 'ok';
        $b['response_chars']   = mb_strlen($rawResponse, 'UTF-8');
        $b['response_excerpt'] = $this->cleanExcerpt($rawResponse, $excerptLen);
        $b['returned']         = count($perId) + count($unknownIds);
        $b['applied']          = count($perId);
        $b['corrected']        = $corrected;
        $b['missing_ids']      = array_map('intval', $missingIds);
        $b['unknown_ids']      = array_map(static fn($v) => Text::truncate((string) (is_scalar($v) ? $v : '?'), 20), $unknownIds);
        unset($b);
        $this->openBatch = null;
    }

    /** Clôt le lot ouvert en échec : tous ses ids restent « indetermine », avec la raison. */
    public function failBatch(string $reason): void
    {
        if ($this->openBatch === null) {
            return;
        }
        $b = &$this->batches[$this->openBatch];
        $b['status'] = 'echec';
        $b['error']  = Text::truncate($reason, 300);
        foreach ($b['ids'] as $id) {
            $this->byMessage[$id][$b['phase']] = ['lot' => $b['index'], 'statut' => 'lot_echec', 'brut' => ''];
        }
        unset($b);
        $this->openBatch = null;
    }

    // ------------------------------------------------------------------
    // Lecture (AuditReport, ReportData, templates)
    // ------------------------------------------------------------------

    /** @return array<string,array{system:string,template:string,prompt_file:string}> */
    public function components(): array
    {
        return $this->components;
    }

    /** @return array<int,array<string,mixed>> */
    public function batches(): array
    {
        return $this->batches;
    }

    /**
     * Provenance d'un message : ['classification' => [...], 'qualite' => [...]] ou [].
     * Statuts possibles : ok | corrige | absent_reponse | lot_echec (et, par
     * convention en aval, ia_desactivee quand aucun appel n'a eu lieu).
     *
     * @return array<string,array<string,mixed>>
     */
    public function messageProvenance(int $id): array
    {
        return $this->byMessage[$id] ?? [];
    }

    /** Ids touchés par un lot en échec, par phase. @return int[] */
    public function failedBatchIds(string $phase): array
    {
        $ids = [];
        foreach ($this->batches as $b) {
            if ($b['phase'] === $phase && $b['status'] === 'echec') {
                $ids = array_merge($ids, $b['ids']);
            }
        }
        return $ids;
    }

    /** Totaux pour la synthèse du journal IA. @return array<string,mixed> */
    public function summary(): array
    {
        $s = [
            'batches'      => count($this->batches),
            'batches_ok'   => 0,
            'batches_ko'   => 0,
            'http_calls'   => 0,
            'retries'      => 0,
            'duration_ms'  => 0,
            'corrected'    => 0,
            'missing'      => 0,
            'unknown'      => 0,
        ];
        foreach ($this->batches as $b) {
            $s[$b['status'] === 'ok' ? 'batches_ok' : 'batches_ko']++;
            $s['http_calls']  += count($b['attempts']);
            $s['retries']     += max(0, count($b['attempts']) - 1);
            $s['duration_ms'] += $b['duration_ms'];
            $s['corrected']   += $b['corrected'];
            $s['missing']     += count($b['missing_ids']);
            $s['unknown']     += count($b['unknown_ids']);
        }
        return $s;
    }

    // ------------------------------------------------------------------
    // Internes
    // ------------------------------------------------------------------

    /** Concatène les valeurs brutes d'un message en chaîne courte ("intent / topic"). */
    private function compactRaw(array $raw): string
    {
        $max = (int) ($this->traceConfig['raw_label_chars'] ?? 60);
        $parts = [];
        foreach ($raw as $field => $value) {
            $parts[] = $field . '=' . Text::truncate((string) $value, $max);
        }
        return implode(' · ', $parts);
    }

    /** Nettoie un extrait de réponse brute pour insertion sûre dans le PDF. */
    private function cleanExcerpt(string $raw, int $max): string
    {
        $s = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', ' ', $raw);
        $s = trim((string) preg_replace('/\s+/u', ' ', $s));
        return Text::truncate($s, $max);
    }
}
