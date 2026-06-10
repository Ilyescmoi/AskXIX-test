<?php
/**
 * Chargeur minimal de fichier .env (AUCUNE dépendance externe).
 *
 * - Ne remplace JAMAIS une variable déjà définie dans l'environnement réel
 *   (l'env système / la ligne de commande restent prioritaires).
 * - Supporte : KEY=VALUE, lignes vides, commentaires (#), guillemets optionnels.
 */
function load_env(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }

        $key = trim(substr($line, 0, $pos));
        $val = trim(substr($line, $pos + 1));

        // Retire d'éventuels guillemets simples/doubles encadrants.
        if (strlen($val) >= 2 && ($val[0] === '"' || $val[0] === "'") && $val[-1] === $val[0]) {
            $val = substr($val, 1, -1);
        }

        // Clé invalide ou déjà présente dans l'environnement réel -> on ne touche pas.
        if ($key === '' || getenv($key) !== false) {
            continue;
        }

        putenv("$key=$val");
        $_ENV[$key] = $val;
        $_SERVER[$key] = $val;
    }
}
