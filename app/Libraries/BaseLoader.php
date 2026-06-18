<?php

namespace App\Libraries;

/**
 * BaseLoader — lit la base de connaissance d'un bot (dossier de fichiers texte +
 * images) et la prépare pour la COMPARAISON avec les réponses du chatbot.
 *
 * Approche « la plus simple » (sans RAG) : la base étant modeste (quelques fichiers
 * texte), on la concatène ENTIÈREMENT pour la fournir comme contexte au modèle.
 * Les images sont indexées par nom de fichier (vérif d'existence, sans réseau).
 * La base est SCELLÉE par une empreinte SHA-256 agrégée (preuve de la version usée).
 *
 * Aucune IA ici : lecture défensive de fichiers uniquement.
 */
class BaseLoader
{
    /** @param array<string,mixed> $config Configuration complète (clé 'base') */
    public function __construct(private array $config) {}

    /**
     * @return array{context:string, context_full_chars:int, truncated:bool,
     *   images:array<string,string>, files:array<int,array<string,mixed>>,
     *   sha256:string, doc_count:int, image_count:int, bytes:int}
     */
    public function load(string $dir): array
    {
        $cfg          = $this->config['base'];
        $textExt      = array_map('strtolower', $cfg['text_ext']);
        $imageExt     = array_map('strtolower', $cfg['image_ext']);
        $maxFiles     = (int) $cfg['max_files'];
        $maxBytes     = (int) $cfg['max_total_bytes'];
        $contextChars = (int) $cfg['context_chars'];

        $empty = [
            'context' => '', 'context_full_chars' => 0, 'truncated' => false,
            'images' => [], 'files' => [], 'sha256' => '', 'doc_count' => 0,
            'image_count' => 0, 'bytes' => 0,
        ];
        if ($dir === '' || !is_dir($dir)) {
            return $empty;
        }
        $dir = rtrim($dir, '/');

        // Collecte déterministe des fichiers (triée), bornée en nombre et en taille.
        $paths = [];
        try {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $f) {
                if ($f->isFile()) {
                    $paths[] = $f->getPathname();
                }
            }
        } catch (\Throwable $e) {
            return $empty;
        }
        sort($paths);

        $context = '';
        $images = [];
        $files = [];
        $docCount = 0;
        $totalBytes = 0;
        $count = 0;

        foreach ($paths as $path) {
            if (++$count > $maxFiles) {
                break;
            }
            $size = (int) filesize($path);
            if ($totalBytes + $size > $maxBytes) {
                break;
            }
            $totalBytes += $size;

            $rel = ltrim(substr($path, strlen($dir)), '/');
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $sha = (string) hash_file('sha256', $path);

            if (in_array($ext, $textExt, true)) {
                $txt = $this->cleanText((string) @file_get_contents($path));
                $context .= "\n\n### " . $rel . "\n" . $txt;
                $docCount++;
                $files[] = ['path' => $rel, 'bytes' => $size, 'sha256' => $sha, 'kind' => 'text'];
            } elseif (in_array($ext, $imageExt, true)) {
                $key = $this->normName(basename($path));
                $images[$key] = $rel;
                $files[] = ['path' => $rel, 'bytes' => $size, 'sha256' => $sha, 'kind' => 'image'];
            }
        }

        $context = trim($context);
        $fullChars = mb_strlen($context, 'UTF-8');
        $truncated = $contextChars > 0 && $fullChars > $contextChars;
        if ($truncated) {
            $context = mb_substr($context, 0, $contextChars, 'UTF-8');
        }

        // Empreinte agrégée scellée (ordre déterministe par chemin relatif).
        $seal = [];
        foreach ($files as $f) {
            $seal[] = $f['path'] . ':' . $f['bytes'] . ':' . $f['sha256'];
        }
        $sha256 = $files === [] ? '' : hash('sha256', implode("\n", $seal));

        return [
            'context'            => $context,
            'context_full_chars' => $fullChars,
            'truncated'          => $truncated,
            'images'             => $images,
            'files'              => $files,
            'sha256'             => $sha256,
            'doc_count'          => $docCount,
            'image_count'        => count($images),
            'bytes'              => $totalBytes,
        ];
    }

    /** Nom de fichier normalisé pour comparaison (minuscule, sans accents). */
    private function normName(string $name): string
    {
        return Text::stripAccents(strtolower(trim($name)));
    }

    /**
     * Assainit un texte source : remplace les séquences UTF-8 invalides (sinon
     * preg/htmlspecialchars videraient tout le champ en silence) et neutralise
     * les caractères de contrôle. Même esprit que CsvLoader::cleanText().
     */
    private function cleanText(string $s): string
    {
        if ($s !== '' && !mb_check_encoding($s, 'UTF-8')) {
            $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8');
        }
        return (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', ' ', $s);
    }
}
