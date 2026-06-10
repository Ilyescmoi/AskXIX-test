<?php
/**
 * bootstrap.php — point d'amorçage commun.
 * - Charge l'autoload Composer (mPDF).
 * - Enregistre un autoloader simple pour les classes applicatives de src/.
 */

$autoload = __DIR__ . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    exit('Dépendances manquantes. Lance "composer install" à la racine du projet.');
}
require $autoload;

// Autoload des classes applicatives (sans namespace) : NomDeClasse → src/NomDeClasse.php
spl_autoload_register(static function (string $class): void {
    $file = __DIR__ . '/src/' . $class . '.php';
    if (is_file($file)) {
        require $file;
    }
});
