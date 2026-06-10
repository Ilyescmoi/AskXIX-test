<?php

/**
 * CsvLoader — lecture, détection de séparateur, mapping de colonnes et
 * normalisation d'un CSV de messages de chatbot.
 *
 * Aucune logique IA ici : uniquement de la lecture défensive de fichier.
 */
class CsvLoader
{
    /**
     * Alias de noms de colonnes acceptés -> champ canonique interne.
     * La comparaison se fait via Text (minuscules, sans accents, non-alphanum -> "_").
     */
    private const COLUMN_ALIASES = [
        'date'     => ['date', 'datetime', 'timestamp', 'created_at', 'createdat', 'horodatage', 'time', 'jour'],
        'user_id'  => ['user_id', 'userid', 'user', 'utilisateur', 'id_utilisateur', 'session_id', 'session', 'client_id', 'distinctid', 'distinct_id'],
        'question' => ['question', 'message', 'user_message', 'input', 'demande', 'prompt', 'query', 'texte'],
        'reponse'  => ['reponse', 'response', 'answer', 'bot_message', 'bot_response', 'output', 'reply'],
        'erreur'   => ['erreur', 'error', 'is_error', 'has_error', 'statut_erreur'],
    ];

    /**
     * Suffixes "bruit" tolérés en fin de nom de colonne (exports type "*_event").
     * Ex. "question_event" est reconnu comme "question", "created_at_event" comme "created_at".
     */
    private const NOISE_SUFFIXES = ['_event', '_evt'];

    private const DELIMITERS = [',', ';', "\t", '|'];

    /**
     * Charge un CSV et renvoie les lignes normalisées.
     *
     * Les ids sont 1-based : l'id N correspond à la N-ième ligne de DONNÉES
     * (= ligne N+1 du fichier si aucune ligne vide n'a été ignorée ; le nombre
     * de lignes ignorées est renvoyé pour que la traçabilité reste exacte).
     * La référence #NNN des rapports se retrouve donc dans le fichier source.
     *
     * @return array{rows: array<int,array<string,mixed>>, mapping: array<string,?string>, delimiter: string, skipped_lines: int}
     */
    public function load(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException("Fichier CSV introuvable ou illisible : $path");
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException("Impossible d'ouvrir le CSV : $path");
        }

        // Détection du séparateur sur la première ligne (BOM UTF-8 retiré).
        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);
            throw new RuntimeException('Le CSV est vide.');
        }
        $delimiter = $this->detectDelimiter($this->stripBom($firstLine));

        // On repositionne le pointeur pour relire proprement l'en-tête via fgetcsv.
        rewind($handle);
        $header = fgetcsv($handle, null, $delimiter, '"', '');
        if ($header === false || $header === [null]) {
            fclose($handle);
            throw new RuntimeException('En-tête CSV illisible.');
        }
        $header[0] = $this->stripBom((string) $header[0]);

        $colIndex = $this->mapColumns($header);
        if ($colIndex['question'] === null) {
            fclose($handle);
            throw new RuntimeException(
                'Colonne "question" introuvable. Colonnes détectées : ' . implode(', ', $header)
            );
        }

        $rows = [];
        $id = 0;
        $fileLine = 1; // ligne 1 = en-tête
        $skippedLines = [];
        while (($record = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
            $fileLine++;
            // Ligne entièrement vide (y compris ";;;") -> ignorée, mais tracée
            // avec son numéro de ligne fichier pour que #NNN reste vérifiable.
            if ($this->isBlankRecord($record)) {
                $skippedLines[] = $fileLine;
                continue;
            }
            $rows[] = $this->normalizeRow(++$id, $record, $colIndex);
        }
        fclose($handle);

        if ($rows === []) {
            throw new RuntimeException('Aucune ligne de données exploitable dans le CSV.');
        }

        // Mapping lisible (champ -> nom de colonne d'origine) pour information/traçabilité.
        $mapping = [];
        foreach ($colIndex as $field => $i) {
            $mapping[$field] = $i === null ? null : (string) $header[$i];
        }

        return [
            'rows'                 => $rows,
            'mapping'              => $mapping,
            'delimiter'            => $delimiter,
            'skipped_lines'        => count($skippedLines),
            'skipped_line_numbers' => $skippedLines, // numéros de ligne FICHIER (en-tête = 1)
        ];
    }

    /** Choisit le séparateur le plus fréquent sur la ligne d'en-tête. */
    private function detectDelimiter(string $line): string
    {
        $best = ',';
        $bestCount = -1;
        foreach (self::DELIMITERS as $d) {
            $count = substr_count($line, $d);
            if ($count > $bestCount) {
                $bestCount = $count;
                $best = $d;
            }
        }
        return $best;
    }

    /**
     * Associe chaque champ canonique à l'index de colonne correspondant (ou null).
     *
     * @param array<int,?string> $header
     * @return array<string,?int>
     */
    private function mapColumns(array $header): array
    {
        // Deux index : correspondance exacte (prioritaire) et correspondance après
        // retrait d'un suffixe bruit (ex. "_event"), pour rester souple sans faux positifs.
        $canonToIndex = [];
        $strippedToIndex = [];
        foreach ($header as $i => $name) {
            $canon = $this->canonKey((string) $name);
            $canonToIndex[$canon] ??= $i;
            $stripped = $this->stripNoiseSuffix($canon);
            if ($stripped !== $canon) {
                $strippedToIndex[$stripped] ??= $i;
            }
        }

        $map = [];
        foreach (self::COLUMN_ALIASES as $field => $aliases) {
            $map[$field] = null;
            foreach ($aliases as $alias) {
                $ck = $this->canonKey($alias);
                if (array_key_exists($ck, $canonToIndex)) {
                    $map[$field] = $canonToIndex[$ck];
                    break;
                }
                if (array_key_exists($ck, $strippedToIndex)) {
                    $map[$field] = $strippedToIndex[$ck];
                    break;
                }
            }
        }
        return $map;
    }

    /** Retire un suffixe "bruit" (ex. "_event") en fin de clé canonique, le cas échéant. */
    private function stripNoiseSuffix(string $canon): string
    {
        foreach (self::NOISE_SUFFIXES as $suffix) {
            if (str_ends_with($canon, $suffix)) {
                return substr($canon, 0, -strlen($suffix));
            }
        }
        return $canon;
    }

    /** Vrai si tous les champs de l'enregistrement sont vides (ligne sans donnée). */
    private function isBlankRecord(array $record): bool
    {
        foreach ($record as $v) {
            if (trim((string) $v) !== '') {
                return false;
            }
        }
        return true;
    }

    /**
     * Transforme un enregistrement brut en ligne normalisée exploitable.
     *
     * @param array<int,?string> $record
     * @param array<string,?int> $colIndex
     * @return array<string,mixed>
     */
    private function normalizeRow(int $id, array $record, array $colIndex): array
    {
        $get = function (?int $i) use ($record): string {
            if ($i === null || !array_key_exists($i, $record) || $record[$i] === null) {
                return '';
            }
            return $this->cleanText(trim((string) $record[$i]));
        };

        $dateRaw = $get($colIndex['date']);
        [$date, $hasTime] = $this->parseDate($dateRaw);

        return [
            'id'            => $id,
            'date_raw'      => $dateRaw,
            'date'          => $date,    // ?DateTimeImmutable (champs absents = 00:00:00)
            'date_has_time' => $hasTime, // false si la date du fichier n'a pas d'heure
            'user_id'       => $get($colIndex['user_id']),
            'question'      => $get($colIndex['question']),
            'reponse'       => $get($colIndex['reponse']),
            'erreur'        => $get($colIndex['erreur']),
        ];
    }

    /**
     * Assainit un texte source : remplace les séquences UTF-8 invalides (au lieu de
     * laisser preg_replace/htmlspecialchars vider TOUT le champ en aval — la
     * traçabilité exige de ne jamais perdre un texte en silence) et neutralise les
     * caractères de contrôle (sauf tab/saut de ligne, compactés plus loin).
     */
    private function cleanText(string $s): string
    {
        if ($s !== '' && !mb_check_encoding($s, 'UTF-8')) {
            // Les octets invalides deviennent le caractère de substitution mbstring
            // ('?' par défaut, ou U+FFFD selon mbstring.substitute_character) — le
            // texte reste présent, jamais vidé en silence.
            $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8');
        }
        return (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', ' ', $s);
    }

    /**
     * Tente de parser une date selon plusieurs formats courants.
     * Le suffixe '|' force les champs absents à zéro (sans lui, PHP les remplit
     * avec l'heure COURANTE : une "heure de pic" serait alors inventée).
     *
     * @return array{0:?DateTimeImmutable,1:bool} [date, l'heure vient-elle du fichier ?]
     */
    private function parseDate(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [null, false];
        }
        $formats = [
            'Y-m-d H:i:s|' => true, 'Y-m-d\TH:i:s|' => true, 'Y-m-d H:i|' => true, 'Y-m-d|' => false,
            'd/m/Y H:i:s|' => true, 'd/m/Y H:i|' => true, 'd/m/Y|' => false,
            'm/d/Y H:i:s|' => true, 'm/d/Y|' => false,
        ];
        foreach ($formats as $f => $hasTime) {
            $dt = DateTimeImmutable::createFromFormat($f, $raw);
            if ($dt instanceof DateTimeImmutable) {
                return [$dt, $hasTime];
            }
        }
        $ts = strtotime($raw);
        if ($ts === false) {
            return [null, false];
        }
        // Repli strtotime : on ne sait l'heure présente que si la chaîne en contient une.
        return [(new DateTimeImmutable())->setTimestamp($ts), str_contains($raw, ':')];
    }

    /** Clé canonique pour comparer des noms de colonnes. */
    private function canonKey(string $s): string
    {
        $s = Text::stripAccents($s);
        $s = strtolower(trim($s));
        $s = preg_replace('/[^a-z0-9]+/', '_', $s);
        return trim((string) $s, '_');
    }

    /** Retire un éventuel BOM UTF-8 en tête de chaîne. */
    private function stripBom(string $s): string
    {
        return (string) preg_replace('/^\xEF\xBB\xBF/', '', $s);
    }
}
