<?php
namespace App\Libraries;

use RuntimeException;
use Throwable;

/**
 * QualityJudge — évalue la qualité de chaque réponse (liste fermée) et un booléen
 * d'hallucination, UNIQUEMENT d'après la paire question/réponse fournie.
 *
 * Comme le Classifier : sortie restreinte à des étiquettes fermées, repli "indetermine".
 */
class QualityJudge
{
    use LabelParsing;

    private string $promptTemplate;

    private string $promptFile;

    /**
     * @param array<string,mixed> $config Configuration complète (config.php)
     * @param RunTrace|null $trace Journal d'exécution (lots, corrections, échecs)
     */
    public function __construct(
        private LlmClient $client,
        private array $config,
        string $promptPath,
        private ?RunTrace $trace = null
    ) {
        $tpl = @file_get_contents($promptPath);
        if ($tpl === false) {
            throw new RuntimeException("Prompt de qualité introuvable : $promptPath");
        }
        $this->promptTemplate = $tpl;
        $this->promptFile = basename($promptPath);
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array{quality:string,hallucination:bool}> indexé par id de ligne
     */
    public function judge(array $rows): array
    {
        $labels = $this->config['quality_labels'];

        // Repli par défaut pour toutes les lignes.
        $out = [];
        foreach ($rows as $r) {
            $out[$r['id']] = ['quality' => 'indetermine', 'hallucination' => false];
        }

        if (!$this->client->isConfigured()) {
            return $out;
        }

        $system = 'Tu juges uniquement d\'après la question et la réponse fournies. '
                . 'Tu n\'utilises aucune connaissance extérieure. Si tu n\'es pas sûr, '
                . 'réponds "indetermine". Réponds en JSON valide uniquement, sans commentaire.';
        $this->trace?->registerComponent('qualite', $system, $this->promptTemplate, $this->promptFile);

        $batchSize = max(1, (int) $this->config['ai']['batch_size']);
        $maxChars  = (int) ($this->config['ai']['max_field_chars'] ?? 0);
        foreach (array_chunk($rows, $batchSize) as $batch) {
            $items = [];
            $sentIds = [];
            foreach ($batch as $r) {
                $items[] = [
                    'id'      => $r['id'],
                    'question' => Text::truncate($r['question'], $maxChars),
                    'reponse' => Text::truncate($r['reponse'], $maxChars),
                ];
                $sentIds[] = (int) $r['id'];
            }

            $prompt = $this->buildPrompt($labels, $items);
            $this->trace?->beginBatch('qualite', $sentIds, $prompt);

            try {
                $raw = $this->client->chat($system, $prompt);
                $results = $this->parseResults($raw);
            } catch (Throwable $e) {
                // Lot en échec : étiquettes "indetermine" conservées, échec journalisé.
                $this->trace?->failBatch($e->getMessage());
                continue;
            }

            // Rapprochement envoyés/reçus : brut vs validé, ids manquants, ids inconnus.
            $perId = [];
            $unknown = [];
            foreach ($results as $res) {
                $id = $this->parseAiId($res['id'] ?? null);
                if ($id === null || !isset($out[$id]) || !in_array($id, $sentIds, true)) {
                    $unknown[] = $res['id'] ?? '?';
                    continue;
                }
                if (isset($perId[$id])) {
                    // Id dupliqué dans la réponse : le premier gagne, le doublon est rejeté (tracé).
                    $unknown[] = ($res['id'] ?? '?') . ' (doublon)';
                    continue;
                }
                $rawQuality = is_string($res['quality'] ?? null) ? trim($res['quality']) : '';
                $rawHalluc  = $res['hallucination'] ?? false;
                $out[$id] = [
                    'quality'       => $this->validateLabel($rawQuality, $labels),
                    // On n'accepte le booléen QUE s'il vaut explicitement true.
                    'hallucination' => $rawHalluc === true,
                ];
                $perId[$id] = [
                    'brut'    => [
                        'qualite'       => $rawQuality,
                        'hallucination' => is_bool($rawHalluc) ? ($rawHalluc ? 'true' : 'false') : Text::truncate(json_encode($rawHalluc) ?: '?', 20),
                    ],
                    'retenu'  => $out[$id],
                    'corrige' => $out[$id]['quality'] !== $rawQuality || ($rawHalluc !== true && $rawHalluc !== false),
                ];
            }
            $missing = array_values(array_diff($sentIds, array_keys($perId)));
            $this->trace?->endBatch($raw, $perId, $missing, $unknown);
        }

        return $out;
    }

    /**
     * @param string[] $labels
     * @param array<int,array<string,mixed>> $items
     */
    private function buildPrompt(array $labels, array $items): string
    {
        return strtr($this->promptTemplate, [
            '{QUALITY_LABELS}' => '- ' . implode("\n- ", $labels),
            '{ITEMS}'          => (string) json_encode($items, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        ]);
    }
}
