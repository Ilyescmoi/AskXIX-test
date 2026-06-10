<?php
/**
 * download.php — sert un fichier généré (rapport, annexe ou CSV) en téléchargement.
 * Sécurité : seul un nom de fichier au format attendu, présent dans storage/reports,
 * est accepté (aucun chemin arbitraire — pas de path traversal possible).
 */

$dir = __DIR__ . '/storage/reports';
$file = (string) ($_GET['f'] ?? '');

// Nom de fichier strict : <type>-AAAAMMJJ-HHMMSS.<ext>
if (!preg_match('/^(rapport|audit|tracabilite)-\d{8}-\d{6}\.(pdf|csv)$/', $file)) {
    http_response_code(400);
    exit('Nom de fichier invalide.');
}

$path = $dir . '/' . $file;
if (!is_file($path)) {
    http_response_code(404);
    exit('Fichier introuvable (il a peut-être été nettoyé).');
}

$isCsv = str_ends_with($file, '.csv');
header('Content-Type: ' . ($isCsv ? 'text/csv; charset=utf-8' : 'application/pdf'));
header('Content-Disposition: attachment; filename="' . $file . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: no-store');
readfile($path);
