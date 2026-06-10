<?php
/**
 * generate.php — contrôleur "un clic".
 * Orchestre : CSV -> Stats factuelles -> classification/jugement IA (batch) ->
 * agrégation en pourcentages -> PDF, puis renvoie le PDF en téléchargement.
 *
 * En cas d'erreur, redirige vers index.php avec un message lisible.
 */

require __DIR__ . '/bootstrap.php';
$config = require __DIR__ . '/config.php';

// La classification IA enchaîne de nombreux appels réseau par lots : le traitement
// peut dépasser le max_execution_time par défaut (30 s). On lève la limite pour cette
// tâche longue. Chaque appel cURL reste borné par 'timeout' + 'max_retries' (pas de boucle infinie).
@set_time_limit(0);
// Le registre complet du document de traçabilité + mPDF demandent de la marge sur de gros CSV.
@ini_set('memory_limit', '1024M');
ignore_user_abort(true);

try {
    // --- 1. Détermination de la source CSV (upload ou jeu d'exemple) ---
    $csvSource = resolveCsvPath($config);

    // --- 2. Activation IA : case cochée ET clé présente, sinon mode factuel pur ---
    $aiEnabled = isset($_POST['use_ai']) && $config['ai']['api_key'] !== '';
    if (!$aiEnabled) {
        $config['ai']['api_key'] = ''; // neutralise tout appel réseau
    }

    // Identité du fichier source, calculée UNE fois (affichée dans les deux PDF).
    $sourceFile = [
        'file'   => basename($csvSource),
        'bytes'  => (int) filesize($csvSource),
        'sha256' => (string) hash_file('sha256', $csvSource),
    ];

    // --- 2b. Journal d'exécution (traçabilité) : contexte + identité du fichier source ---
    $trace = new RunTrace($config['trace'] ?? []);
    $trace->setAiEnabled($aiEnabled);
    $trace->setAiParams($config['ai']);
    $trace->setSource($sourceFile['file'], $sourceFile['bytes'], $sourceFile['sha256']);

    // --- 3. Lecture + normalisation du CSV ---
    $loaded = (new CsvLoader())->load($csvSource);
    $rows = $loaded['rows'];
    $trace->setCsv($loaded['mapping'], count($rows), $loaded['delimiter'], $loaded['skipped_line_numbers']);

    // --- 4. Calculs FACTUELS (zéro IA) ---
    $statsService = new Stats($config);
    $stats = $statsService->compute($rows);

    // --- 5. Étiquetage IA en listes fermées (batch + retry, chaque lot journalisé) ---
    $client = new MistralClient($config['ai'], $trace);
    $labels  = (new Classifier($client, $config, __DIR__ . '/prompts/intent.txt', $trace))->classify($rows);
    $quality = (new QualityJudge($client, $config, __DIR__ . '/prompts/quality.txt', $trace))->judge($rows);

    // Jeton commun aux 3 fichiers d'une même analyse (même horodatage).
    $token = date('Ymd-His');
    $outDir = __DIR__ . '/storage/reports';

    $sourceMeta = $sourceFile + ['token' => $token];

    // --- 6. Fusion + agrégation en pourcentages (PHP), ids sources inclus ---
    $data = (new ReportData($config))->build($rows, $stats, $labels, $quality, $aiEnabled, $trace, $sourceMeta);

    // --- 7. Rapport principal ---
    (new PdfReport($config, __DIR__ . '/templates/report.php'))
        ->generate($data, $outDir, $token);

    // --- 7b. Document de traçabilité (PDF) + CSV ligne-par-ligne (justification) ---
    $audit = new AuditReport($config, $statsService);
    $auditRows = $audit->rows($rows, $labels, $quality, $trace);
    $traceCsv = $outDir . '/tracabilite-' . $token . '.csv';
    $audit->writeCsv($auditRows, $traceCsv);
    $auditData = $audit->buildPdfData($auditRows, $stats, $data, $trace, $sourceMeta, basename($traceCsv));
    $aiSummary = $trace->summary();
    // Libère les textes complets avant le rendu mPDF du registre (mémoire).
    unset($rows, $loaded, $labels, $quality, $auditRows, $trace);
    (new PdfReport($config, __DIR__ . '/templates/audit.php', 'audit', ['packTableData' => true]))
        ->generate($auditData, $outDir, $token);

    // --- 7c. Petit résumé pour la page de résultats ---
    // Sujet n°1 HORS catégories génériques (« Autres sujets »/« Non classé ») —
    // même règle que le point « En bref » du rapport, pour rester cohérent.
    $topTopic = null;
    foreach ($data['topics'] as $t) {
        if (!in_array($t['key'], ['autre', 'indetermine'], true)) {
            $topTopic = $t;
            break;
        }
    }
    file_put_contents($outDir . '/meta-' . $token . '.json', json_encode([
        'generated_at'      => $data['generated_at'],
        'messages'          => $stats['total_messages'],
        'users'             => $stats['unique_users'],
        'non_response_rate' => $stats['non_response']['rate'],
        'top_topic'         => $topTopic['name'] ?? '—',
        'top_topic_pct'     => $topTopic['percent'] ?? 0,
        'ai_enabled'        => $aiEnabled,
        'ai_batches'        => $aiSummary['batches'],
        'ai_http_calls'     => $aiSummary['http_calls'],
        'source_sha256'     => $sourceMeta['sha256'],
    ], JSON_UNESCAPED_UNICODE));

    // --- 8. Post/Redirect/Get vers la page de téléchargement ---
    header('Location: result.php?t=' . $token);
    exit;

} catch (Throwable $e) {
    header('Location: index.php?error=' . urlencode($e->getMessage()));
    exit;
}

/**
 * Renvoie le chemin du CSV à analyser : jeu d'exemple, fichier uploadé, ou erreur.
 *
 * @param array<string,mixed> $config
 */
function resolveCsvPath(array $config): string
{
    // Bouton "jeu d'exemple".
    if (($_POST['sample'] ?? '') === '1') {
        $sample = __DIR__ . '/sample/messages.csv';
        if (!is_file($sample)) {
            throw new RuntimeException("Jeu d'exemple introuvable.");
        }
        return $sample;
    }

    // Upload utilisateur.
    if (!empty($_FILES['csv']['name'])) {
        $f = $_FILES['csv'];
        if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException("Échec de l'upload (code " . (int) $f['error'] . ').');
        }
        $ext = strtolower(pathinfo((string) $f['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'txt'], true)) {
            throw new RuntimeException('Le fichier doit être un .csv.');
        }
        if ((int) $f['size'] > 10 * 1024 * 1024) {
            throw new RuntimeException('Fichier trop volumineux (max 10 Mo).');
        }

        $dir = __DIR__ . '/storage/uploads';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException("Impossible de créer le dossier d'upload.");
        }
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $f['name']);
        $dest = $dir . '/' . date('Ymd-His') . '-' . $safeName;
        if (!move_uploaded_file((string) $f['tmp_name'], $dest)) {
            throw new RuntimeException("Impossible d'enregistrer le fichier uploadé.");
        }
        return $dest;
    }

    throw new RuntimeException('Aucun fichier CSV fourni. Choisis un fichier ou utilise le jeu d\'exemple.');
}
