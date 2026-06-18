<?php

namespace App\Libraries;

/**
 * ImageChecker — vérifie, en PHP pur (AUCUN appel réseau), que les images citées
 * dans une réponse de chatbot existent bien dans la base du bot.
 *
 * On extrait les références d'images (markdown ![..](url), URLs, chemins) et on
 * compare leur NOM DE FICHIER à l'index d'images de la base. Une image citée mais
 * absente de l'index = lien cassé / image inventée → signalée et traçable.
 */
class ImageChecker
{
    /**
     * @param array<string,string> $imageIndex nom normalisé => chemin (sortie de BaseLoader)
     * @return array{cited:string[],missing:array<int,array{url:string,fichier:string}>}
     */
    public function check(string $reponse, array $imageIndex): array
    {
        $urls = [];
        // 1. Images markdown : ![alt](url)
        if (preg_match_all('~!\[[^\]]*\]\(([^)\s]+)~u', $reponse, $m)) {
            $urls = array_merge($urls, $m[1]);
        }
        // 2. URLs / chemins se terminant par une extension d'image.
        if (preg_match_all('~[\w./:%-]+\.(?:png|jpe?g|webp|gif)~iu', $reponse, $m2)) {
            $urls = array_merge($urls, $m2[0]);
        }
        $urls = array_values(array_unique(array_map('trim', $urls)));

        $cited = [];
        $missing = [];
        $seenMissing = [];
        foreach ($urls as $u) {
            $name = $this->basenameOf($u);
            if ($name === '') {
                continue;
            }
            $cited[] = $u;
            if (!isset($imageIndex[$name]) && !isset($seenMissing[$name])) {
                $seenMissing[$name] = true;
                $missing[] = ['url' => $u, 'fichier' => $name];
            }
        }
        return ['cited' => $cited, 'missing' => $missing];
    }

    /** Nom de fichier normalisé d'une URL/chemin (sans query/fragment, minuscule, sans accents). */
    private function basenameOf(string $u): string
    {
        $u = (string) preg_replace('~[?#].*$~', '', $u);
        $pos = strrpos($u, '/');
        $name = $pos !== false ? substr($u, $pos + 1) : $u;
        return Text::stripAccents(strtolower(trim($name)));
    }
}
