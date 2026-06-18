<?php

namespace App\Controllers;

use App\Libraries\AuditReport;
use App\Libraries\BaseLoader;
use App\Libraries\Classifier;
use App\Libraries\CsvLoader;
use App\Libraries\GroundingJudge;
use App\Libraries\ImageChecker;
use App\Libraries\LlmClient;
use App\Libraries\PdfReport;
use App\Libraries\QualityJudge;
use App\Libraries\ReportData;
use App\Libraries\RunTrace;
use App\Libraries\Stats;

/**
 * ReportController — génère le rapport de vérification d'un bot.
 *
 * Routes :
 *   GET  /rapport/<bot>?key=…&format=pdf|json&ai=0|1
 *   POST /rapport/<bot>?key=…&format=pdf|json&ai=0|1   (conversations dans la requête)
 *
 * Conversations : soit envoyées dans l'appel (upload « file » multipart ou corps
 * brut CSV), soit lues sur le serveur (writable/bots/<bot>/conversations.csv).
 * La base de connaissance reste celle du bot configurée côté serveur. L'outil
 * vérifie la fidélité des réponses puis renvoie le PDF (ou un JSON).
 */
class ReportController extends BaseController
{
    public function generate(string $bot)
    {
        $config = config('Verifier')->bundle();

        $botId  = $this->safeBot($bot);
        if ($botId === '') {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Bot invalide.']);
        }
        $botDir = WRITEPATH . 'bots/' . $botId;

        // Source des conversations : upload API (fichier/corps) sinon fichier disque du bot.
        [$csvPath, $sourceName, $cleanupTmp] = $this->resolveCsv($botDir);
        if ($csvPath === null) {
            if (!is_dir($botDir)) {
                return $this->response->setStatusCode(404)->setJSON([
                    'error' => "Bot inconnu : « {$bot} ». Crée writable/bots/{$botId}/ ou envoie le CSV dans la requête.",
                ]);
            }
            return $this->response->setStatusCode(422)->setJSON([
                'error' => "Conversations introuvables pour « {$botId} » : dépose conversations.csv, ou envoie le CSV (champ « file » ou corps de requête en POST).",
            ]);
        }

        @set_time_limit(0);
        @ini_set('memory_limit', '1024M');

        // --- IA : activée si une clé est configurée (désactivable via ?ai=0) ---
        $aiEnabled = $config['ai']['api_key'] !== '' && $this->request->getGet('ai') !== '0';
        if (!$aiEnabled) {
            $config['ai']['api_key'] = '';
        }

        // --- Identité du fichier source (scellé) + journal d'exécution ---
        $sourceFile = [
            'file'   => $sourceName,
            'bytes'  => (int) @filesize($csvPath),
            'sha256' => (string) @hash_file('sha256', $csvPath),
        ];
        $trace = new RunTrace($config['trace'] ?? []);
        $trace->setAiEnabled($aiEnabled);
        $trace->setAiParams($config['ai']);
        $trace->setSource($sourceFile['file'], $sourceFile['bytes'], $sourceFile['sha256']);

        // --- Conversations ---
        try {
            $loaded = (new CsvLoader())->load($csvPath);
        } catch (\Throwable $e) {
            if ($cleanupTmp) {
                @unlink($csvPath);
            }
            return $this->response->setStatusCode(422)
                ->setJSON(['error' => 'CSV invalide : ' . $e->getMessage()]);
        }
        if ($cleanupTmp) {
            @unlink($csvPath);
        }
        $rows = $loaded['rows'];
        $trace->setCsv($loaded['mapping'], count($rows), $loaded['delimiter'], $loaded['skipped_line_numbers']);

        // --- Stats factuelles (PHP) ---
        $statsService = new Stats($config);
        $stats = $statsService->compute($rows);

        // --- Étiquetage IA (sujets/intentions + qualité) ---
        $client  = new LlmClient($config['ai'], $trace);
        $labels  = (new Classifier($client, $config, APPPATH . 'Prompts/intent.txt', $trace))->classify($rows);
        $quality = (new QualityJudge($client, $config, APPPATH . 'Prompts/quality.txt', $trace))->judge($rows);

        // --- Base de connaissance du bot + vérification de fidélité ---
        $base = (new BaseLoader($config))->load($botDir . '/base');
        $baseEnabled = $base['context'] !== '' || $base['images'] !== [];
        $trace->setBase($base['sha256'], $base['doc_count'], $base['image_count']);

        $grounding = null;
        $images = null;
        if ($baseEnabled) {
            $grounding = (new GroundingJudge($client, $config, APPPATH . 'Prompts/grounding.txt', $trace))
                ->judge($rows, $base['context']);
            $checker = new ImageChecker();
            $images = [];
            foreach ($rows as $r) {
                $images[$r['id']] = $checker->check((string) $r['reponse'], $base['images']);
            }
        }

        // --- Agrégation + rendu ---
        $token   = date('Ymd-His');
        $outDir  = WRITEPATH . 'reports/' . $botId;
        $sourceMeta = $sourceFile + ['token' => $token, 'bot' => $botId];

        $data = (new ReportData($config))->build(
            $rows, $stats, $labels, $quality, $aiEnabled, $trace, $sourceMeta, $grounding, $images, $base
        );
        (new PdfReport($config, APPPATH . 'Views/report.php'))->generate($data, $outDir, $token);

        $audit = new AuditReport($config, $statsService);
        $auditRows = $audit->rows($rows, $labels, $quality, $trace, $grounding, $images);
        $traceCsv = $outDir . '/tracabilite-' . $token . '.csv';
        $audit->writeCsv($auditRows, $traceCsv);
        $auditData = $audit->buildPdfData($auditRows, $stats, $data, $trace, $sourceMeta, basename($traceCsv), $base);
        $aiSummary = $trace->summary();
        unset($rows, $loaded, $labels, $quality, $auditRows, $trace);
        (new PdfReport($config, APPPATH . 'Views/audit.php', 'audit', ['packTableData' => true]))
            ->generate($auditData, $outDir, $token);

        // --- Résumé JSON ---
        $brokenImages = 0;
        if ($images !== null) {
            foreach ($images as $im) {
                $brokenImages += count($im['missing']);
            }
        }
        $meta = [
            'bot'                => $botId,
            'generated_at'       => $data['generated_at'],
            'messages'           => $stats['total_messages'],
            'non_response_rate'  => $stats['non_response']['rate'],
            'base_enabled'       => $baseEnabled,
            'base_sha256'        => $base['sha256'],
            'broken_images'      => $brokenImages,
            'ai_enabled'         => $aiEnabled,
            'ai_batches'         => $aiSummary['batches'],
            'token'              => $token,
            'files'              => [
                'rapport' => "rapport-{$token}.pdf",
                'audit'   => "audit-{$token}.pdf",
                'csv'     => "tracabilite-{$token}.csv",
            ],
            'download'           => [
                'rapport' => "telecharger/{$botId}/rapport",
                'audit'   => "telecharger/{$botId}/audit",
                'csv'     => "telecharger/{$botId}/csv",
            ],
        ];
        if (isset($data['grounding'])) {
            foreach ($data['grounding'] as $g) {
                $meta['grounding'][$g['key']] = $g['count'];
            }
        }
        file_put_contents($outDir . '/meta-' . $token . '.json', json_encode($meta, JSON_UNESCAPED_UNICODE));

        if ($this->request->getGet('format') === 'json') {
            return $this->response->setJSON($meta);
        }
        return $this->response->download($outDir . '/rapport-' . $token . '.pdf', null)
            ->setFileName("rapport-{$botId}-{$token}.pdf");
    }

    /**
     * Télécharge le dernier document généré pour un bot, sans régénérer.
     *
     * @param string $bot  identifiant du bot
     * @param string $type rapport | audit | csv
     */
    public function download(string $bot, string $type)
    {
        $botId = $this->safeBot($bot);
        $map = [
            'rapport' => ['rapport-', '.pdf'],
            'audit'   => ['audit-', '.pdf'],
            'csv'     => ['tracabilite-', '.csv'],
            'trace'   => ['tracabilite-', '.csv'],
        ];
        $key = strtolower($type);
        if ($botId === '' || ! isset($map[$key])) {
            return $this->response->setStatusCode(404)
                ->setJSON(['error' => 'Document inconnu. Types disponibles : rapport, audit, csv.']);
        }

        [$prefix, $ext] = $map[$key];
        $files = glob(WRITEPATH . 'reports/' . $botId . '/' . $prefix . '*' . $ext);
        if ($files === false || $files === []) {
            return $this->response->setStatusCode(404)->setJSON([
                'error' => "Aucun document « {$key} » pour « {$botId} ». Génère d'abord un rapport via /rapport/{$botId}.",
            ]);
        }

        sort($files); // le token est horodaté (AAAAMMJJ-HHMMSS) → ordre chronologique
        $latest = (string) end($files);

        return $this->response->download($latest, null)->setFileName(basename($latest));
    }

    /**
     * Résout la source CSV des conversations.
     *
     * Priorité : fichier uploadé (multipart) > corps brut de la requête (POST/PUT)
     * > fichier disque du bot (writable/bots/<bot>/conversations.csv).
     *
     * @return array{0:?string,1:string,2:bool} [chemin à charger, nom source affiché, fichier temporaire à supprimer ?]
     */
    private function resolveCsv(string $botDir): array
    {
        // 1) Upload multipart — champs acceptés : « file », « csv », « conversations ».
        foreach (['file', 'csv', 'conversations'] as $field) {
            $up = $this->request->getFile($field);
            if ($up !== null && $up->isValid() && ! $up->hasMoved()) {
                return [$up->getTempName(), basename($up->getClientName() ?: 'upload.csv'), false];
            }
        }

        // 2) Corps brut de la requête (text/csv, application/octet-stream, etc.), hors JSON.
        $method = strtoupper($this->request->getMethod());
        if (in_array($method, ['POST', 'PUT'], true)) {
            $raw   = (string) $this->request->getBody();
            $ctype = strtolower($this->request->getHeaderLine('Content-Type'));
            if ($raw !== '' && strpos($ctype, 'application/json') === false
                && strpos($ctype, 'multipart/form-data') === false) {
                $tmp = tempnam(WRITEPATH . 'uploads', 'csv_');
                if ($tmp !== false && @file_put_contents($tmp, $raw) !== false) {
                    $name = $this->request->getHeaderLine('X-Filename') ?: 'upload.csv';
                    return [$tmp, basename($name), true];
                }
            }
        }

        // 3) Fichier disque du bot (comportement historique du GET).
        $disk = $botDir . '/conversations.csv';
        if (is_file($disk)) {
            return [$disk, basename($disk), false];
        }

        return [null, '', false];
    }

    /** Identifiant de bot sûr (pas de traversée de chemin). */
    private function safeBot(string $bot): string
    {
        return (string) preg_replace('/[^A-Za-z0-9._-]/', '_', $bot);
    }
}
