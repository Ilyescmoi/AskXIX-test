<?php
namespace App\Libraries;

use DateTimeImmutable;

/**
 * Stats — calculs FACTUELS et DÉTERMINISTES à partir des lignes du CSV.
 *
 * Aucune IA n'intervient ici. Tous les chiffres "durs" du rapport proviennent
 * d'ici (le reste vient de l'agrégation d'étiquettes dans ReportData), jamais du modèle.
 */
class Stats
{
    /** @param array<string,mixed> $config Configuration complète (config.php) */
    public function __construct(private array $config) {}

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    public function compute(array $rows): array
    {
        $total = count($rows);

        // --- Utilisateurs uniques + volume par utilisateur ---
        $users = [];
        foreach ($rows as $r) {
            $u = $r['user_id'] !== '' ? $r['user_id'] : '(inconnu)';
            $users[$u] = ($users[$u] ?? 0) + 1;
        }
        $uniqueUsers = count($users);

        // Engagement : utilisateurs revenus (plus d'une question) vs une seule question.
        $returningUsers = 0;
        $singleQuestionUsers = 0;
        foreach ($users as $count) {
            if ($count > 1) {
                $returningUsers++;
            } else {
                $singleQuestionUsers++;
            }
        }

        // --- Période couverte ---
        $minDate = null;
        $maxDate = null;
        foreach ($rows as $r) {
            $d = $r['date'];
            if (!$d instanceof DateTimeImmutable) {
                continue;
            }
            if ($minDate === null || $d < $minDate) {
                $minDate = $d;
            }
            if ($maxDate === null || $d > $maxDate) {
                $maxDate = $d;
            }
        }
        $spanDays = ($minDate && $maxDate) ? ($minDate->diff($maxDate)->days + 1) : null;

        // --- Répartitions temporelles ---
        $byDay = [];                      // 'Y-m-d' => count
        $byHour = array_fill(0, 24, 0);   // 0..23  => count (lignes AVEC heure uniquement)
        $byWeekday = array_fill(1, 7, 0); // 1=lundi .. 7=dimanche
        $datedRows = 0;
        $timedRows = 0;                   // lignes dont l'heure provient du fichier
        $undatedIds = [];                 // ids sans date exploitable (exclus des répartitions)
        $noTimeIds = [];                  // ids datés mais sans heure (exclus du profil horaire)
        foreach ($rows as $r) {
            $d = $r['date'];
            if (!$d instanceof DateTimeImmutable) {
                $undatedIds[] = (int) $r['id'];
                continue;
            }
            $datedRows++;
            $day = $d->format('Y-m-d');
            $byDay[$day] = ($byDay[$day] ?? 0) + 1;
            $byWeekday[(int) $d->format('N')]++;
            // L'heure n'est comptée QUE si elle figure dans le fichier : sinon on
            // inventerait un pic à minuit (ou à l'heure de génération).
            if (!empty($r['date_has_time'])) {
                $timedRows++;
                $byHour[(int) $d->format('G')]++;
            } else {
                $noTimeIds[] = (int) $r['id'];
            }
        }
        ksort($byDay);

        // --- Pics d'activité (tie-break : PREMIER maximum dans l'ordre chronologique) ---
        $peakHour = $this->peak($byHour, $timedRows > 0);
        $peakDay  = $this->peak($byDay, $byDay !== []);

        // --- Non-réponse par RÈGLES (factuel, sans IA) ---
        $nonResponse = $this->countNonResponses($rows);

        // --- Erreurs explicites (colonne erreur si présente) ---
        $errors = 0;
        $errorIds = [];
        foreach ($rows as $r) {
            if ($this->isError((string) $r['erreur'])) {
                $errors++;
                $errorIds[] = (int) $r['id'];
            }
        }

        return [
            'total_messages'        => $total,
            'unique_users'          => $uniqueUsers,
            'messages_per_user'     => $uniqueUsers > 0 ? round($total / $uniqueUsers, 2) : 0.0,
            'max_messages_user'     => $users ? max($users) : 0,
            'returning_users'       => $returningUsers,
            'single_question_users' => $singleQuestionUsers,
            'dated_rows'        => $datedRows,
            'period' => [
                'start'     => $minDate?->format('Y-m-d H:i'),
                'end'       => $maxDate?->format('Y-m-d H:i'),
                'span_days' => $spanDays,
            ],
            'by_day'       => $byDay,
            'by_hour'      => $byHour,
            'by_weekday'   => $byWeekday,
            'timed_rows'   => $timedRows,
            'peak_hour'    => $peakHour,    // ['key'=>int,'count'=>int,'ties'=>int] ou null
            'peak_day'     => $peakDay,     // ['key'=>'Y-m-d','count'=>int,'ties'=>int] ou null
            'non_response' => $nonResponse, // ['count'=>int, 'rate'=>float, 'ids'=>int[], 'reasons'=>...]
            'errors'       => $errors,
            'error_ids'    => $errorIds,    // ids des lignes en erreur explicite
            'undated_ids'  => $undatedIds,  // ids sans date exploitable
            'no_time_ids'  => $noTimeIds,   // ids datés mais sans heure (hors profil horaire)
        ];
    }

    /**
     * Maximum d'un comptage, avec règle de départage EXPLICITE (traçable) :
     * en cas d'égalité, la première clé dans l'ordre du tableau (chronologique)
     * est retenue et le nombre d'ex æquo est signalé.
     *
     * @param array<int|string,int> $counts
     * @return array{key:int|string,count:int,ties:int,tie_keys:array<int,int|string>}|null
     */
    private function peak(array $counts, bool $hasData): ?array
    {
        if (!$hasData || $counts === []) {
            return null;
        }
        $max = max($counts);
        if ($max <= 0) {
            return null;
        }
        $keys = array_keys($counts, $max, true);
        return ['key' => $keys[0], 'count' => $max, 'ties' => count($keys), 'tie_keys' => $keys];
    }

    /**
     * Compte les non-réponses selon des règles déterministes.
     * 'reasons' donne, pour chaque id concerné, la règle exacte déclenchée —
     * la même qui figure dans le document de traçabilité (source unique).
     *
     * @param array<int,array<string,mixed>> $rows
     * @return array{count:int,rate:float,ids:int[],reasons:array<int,string>}
     */
    private function countNonResponses(array $rows): array
    {
        $ids = [];
        $reasons = [];
        foreach ($rows as $r) {
            $reason = $this->nonResponseReason((string) $r['reponse']);
            if ($reason !== null) {
                $ids[] = (int) $r['id'];
                $reasons[(int) $r['id']] = $reason;
            }
        }

        $count = count($ids);
        $total = count($rows);
        return [
            'count'   => $count,
            'rate'    => $total > 0 ? round(100 * $count / $total, 1) : 0.0,
            'ids'     => $ids,
            'reasons' => $reasons,
        ];
    }

    /**
     * Renvoie la RÈGLE qui qualifie une réponse de "non-réponse" (texte lisible),
     * ou null si c'est une vraie réponse. Utilisé pour le comptage ET la traçabilité
     * (audit) — même logique, donc résultats cohérents et justifiables.
     */
    public function nonResponseReason(string $reponse): ?string
    {
        $rules = $this->config['non_response_rules'];
        $minLen = (int) $rules['min_length'];
        $rep = trim($reponse);

        if ($rep === '') {
            return 'Réponse vide';
        }
        if (Text::length($rep) < $minLen) {
            return 'Réponse trop courte (< ' . $minLen . ' caractères)';
        }
        $norm = Text::normalize($rep);
        foreach ($rules['fallback_phrases'] as $phrase) {
            $pn = Text::normalize($phrase);
            if ($pn !== '' && str_contains($norm, $pn)) {
                return 'Formule de repli : « ' . $phrase . ' »';
            }
        }
        return null;
    }

    /** Interprète une valeur de colonne "erreur" comme vraie/fausse. */
    private function isError(string $v): bool
    {
        return in_array(Text::normalize($v), ['1', 'true', 'oui', 'yes', 'erreur', 'error'], true);
    }
}
