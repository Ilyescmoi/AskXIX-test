<?php
namespace App\Libraries;


/**
 * Text — petites fonctions utilitaires de normalisation de texte (sans IA).
 * Utilisées pour comparer/regrouper des chaînes de façon déterministe.
 */
class Text
{
    /** Retire les accents/diacritiques d'une chaîne UTF-8. */
    public static function stripAccents(string $s): string
    {
        if (function_exists('transliterator_transliterate')) {
            $t = transliterator_transliterate('Any-Latin; Latin-ASCII', $s);
            if (is_string($t)) {
                return $t;
            }
        }
        // Repli sans extension intl.
        $map = [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a', 'å' => 'a',
            'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'í' => 'i', 'ì' => 'i',
            'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'õ' => 'o', 'ò' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u',
            'ÿ' => 'y', 'ñ' => 'n', 'œ' => 'oe', 'æ' => 'ae',
        ];
        return strtr(mb_strtolower($s, 'UTF-8'), $map);
    }

    /** Normalisation pour comparaison/regroupement : minuscules, sans accents, espaces compactés. */
    public static function normalize(string $s): string
    {
        $s = self::stripAccents($s);
        $s = mb_strtolower($s, 'UTF-8');
        $s = preg_replace('/\s+/', ' ', $s);
        return trim($s);
    }

    /** Longueur en caractères (multibyte). */
    public static function length(string $s): int
    {
        return mb_strlen(trim($s), 'UTF-8');
    }

    /** Tronque une chaîne à $max caractères (ajoute "…" si coupée). $max <= 0 => pas de coupe. */
    public static function truncate(string $s, int $max): string
    {
        $s = trim($s);
        if ($max <= 0 || mb_strlen($s, 'UTF-8') <= $max) {
            return $s;
        }
        return mb_substr($s, 0, $max, 'UTF-8') . '…';
    }

    /**
     * Référence lisible d'un message : #007 (zéro-paddée à $width chiffres).
     * C'est LA notation commune au rapport, au document de traçabilité et au CSV.
     */
    public static function ref(int $id, int $width = 3): string
    {
        return '#' . str_pad((string) $id, max(1, $width), '0', STR_PAD_LEFT);
    }

    /** Largeur de référence pour un id maximal donné (au moins 3 chiffres). */
    public static function refWidth(int $maxId): int
    {
        return max(3, strlen((string) max(0, $maxId)));
    }

    /**
     * Compacte une liste d'ids en plages lisibles : "#001–#004, #007, #012–#019".
     * Coupe après $maxSegments plages et signale le reste : "… (+N autres)".
     *
     * @param int[] $ids
     */
    public static function refRanges(array $ids, int $width = 3, int $maxSegments = 30): string
    {
        if ($ids === []) {
            return '—';
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids);

        // Fusion des ids consécutifs en segments [debut, fin].
        $segments = [];
        $from = $prev = $ids[0];
        foreach (array_slice($ids, 1) as $id) {
            if ($id === $prev + 1) {
                $prev = $id;
                continue;
            }
            $segments[] = [$from, $prev];
            $from = $prev = $id;
        }
        $segments[] = [$from, $prev];

        $shown = array_slice($segments, 0, max(1, $maxSegments));
        $parts = [];
        $shownCount = 0;
        foreach ($shown as [$a, $b]) {
            $parts[] = $a === $b
                ? self::ref($a, $width)
                : self::ref($a, $width) . '–' . self::ref($b, $width);
            $shownCount += $b - $a + 1;
        }

        $rest = count($ids) - $shownCount;
        return implode(', ', $parts) . ($rest > 0 ? ' … (+' . $rest . ' autres)' : '');
    }
}
