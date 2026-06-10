<?php

/**
 * LabelParsing — outils communs aux classifieurs IA (Classifier, QualityJudge) :
 * extraction défensive du JSON et validation stricte contre une liste fermée.
 *
 * Centralise la barrière anti-hallucination : toute valeur hors liste devient "indetermine".
 */
trait LabelParsing
{
    /**
     * Extrait le tableau "results" d'une réponse JSON, sinon [].
     *
     * @return array<int,array<string,mixed>>
     */
    private function parseResults(string $raw): array
    {
        $data = json_decode($this->stripFences($raw), true);
        if (is_array($data) && isset($data['results']) && is_array($data['results'])) {
            return $data['results'];
        }
        return [];
    }

    /** Garde la valeur uniquement si elle figure dans la liste fermée, sinon "indetermine". */
    private function validateLabel(mixed $value, array $allowed): string
    {
        $v = is_string($value) ? trim($value) : '';
        return in_array($v, $allowed, true) ? $v : 'indetermine';
    }

    /**
     * Interprète un id renvoyé par l'IA : seuls les ENTIERS exacts sont acceptés
     * (int, float entière, ou chaîne de chiffres). "2.7" serait sinon casté vers
     * le message 2 et écraserait une étiquette légitime.
     */
    private function parseAiId(mixed $raw): ?int
    {
        if (is_int($raw)) {
            return $raw;
        }
        if (is_float($raw) && is_finite($raw) && floor($raw) === $raw) {
            return (int) $raw;
        }
        if (is_string($raw) && ctype_digit(trim($raw))) {
            return (int) trim($raw);
        }
        return null;
    }

    /** Retire d'éventuelles balises Markdown (```json ... ```) autour du JSON. */
    private function stripFences(string $s): string
    {
        $s = trim($s);
        $s = (string) preg_replace('/^```[a-zA-Z]*\s*/', '', $s);
        $s = (string) preg_replace('/\s*```$/', '', $s);
        return trim($s);
    }
}
