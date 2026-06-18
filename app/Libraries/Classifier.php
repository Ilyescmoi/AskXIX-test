<?php
namespace App\Libraries;

use RuntimeException;
use Throwable;

/**
 * Classifier — attribue à chaque question une INTENTION et un SUJET,
 * UNIQUEMENT parmi les listes fermées de config.php.
 *
 * Toute valeur hors liste, manquante ou douteuse est ramenée à "indetermine".
 * Le traitement se fait par lots (batch) pour limiter le nombre d'appels.
 */
class Classifier
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
            throw new RuntimeException("Prompt de classification introuvable : $promptPath");
        }
        $this->promptTemplate = $tpl;
        $this->promptFile = basename($promptPath);
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array{intent:string,topic:string}> indexé par id de ligne
     */
    public function classify(array $rows): array
    {
        $intents = $this->config['intents'];
        $topics  = $this->config['topics'];

        // Valeur de repli par défaut pour TOUTES les lignes (anti-hallucination).
        $out = [];
        foreach ($rows as $r) {
            $out[$r['id']] = ['intent' => 'indetermine', 'topic' => 'indetermine'];
        }

        // Sans clé API -> on reste sur "indetermine" partout (mode factuel pur).
        if (!$this->client->isConfigured()) {
            return $out;
        }

        $system = 'Tu classes uniquement d\'après le texte fourni. Tu n\'utilises aucune '
                . 'connaissance extérieure. Si tu n\'es pas sûr, réponds "indetermine". '
                . 'Réponds en JSON valide uniquement, sans commentaire.';
        $this->trace?->registerComponent('classification', $system, $this->promptTemplate, $this->promptFile);

        $batchSize = max(1, (int) $this->config['ai']['batch_size']);
        $maxChars  = (int) ($this->config['ai']['max_field_chars'] ?? 0);
        foreach (array_chunk($rows, $batchSize) as $batch) {
            $items = [];
            $sentIds = [];
            foreach ($batch as $r) {
                $items[] = ['id' => $r['id'], 'question' => Text::truncate($r['question'], $maxChars)];
                $sentIds[] = (int) $r['id'];
            }

            $prompt = $this->buildPrompt($intents, $topics, $items);
            $this->trace?->beginBatch('classification', $sentIds, $prompt);

            try {
                $raw = $this->client->chat($system, $prompt);
                $results = $this->parseResults($raw);
            } catch (Throwable $e) {
                // En cas d'échec d'un lot, on garde "indetermine" pour ce lot et on continue —
                // mais l'échec n'est plus silencieux : il est journalisé avec sa raison.
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
                $rawIntent = is_string($res['intent'] ?? null) ? trim($res['intent']) : '';
                $rawTopic  = is_string($res['topic'] ?? null) ? trim($res['topic']) : '';
                $out[$id] = [
                    'intent' => $this->validateLabel($rawIntent, $intents),
                    'topic'  => $this->validateLabel($rawTopic, $topics),
                ];
                $perId[$id] = [
                    'brut'    => ['intention' => $rawIntent, 'sujet' => $rawTopic],
                    'retenu'  => $out[$id],
                    'corrige' => $out[$id]['intent'] !== $rawIntent || $out[$id]['topic'] !== $rawTopic,
                ];
            }
            $missing = array_values(array_diff($sentIds, array_keys($perId)));
            $this->trace?->endBatch($raw, $perId, $missing, $unknown);
        }

        return $out;
    }

    /**
     * Injecte les listes fermées et les items dans le gabarit de prompt.
     *
     * @param string[] $intents
     * @param string[] $topics
     * @param array<int,array<string,mixed>> $items
     */
    private function buildPrompt(array $intents, array $topics, array $items): string
    {
        return strtr($this->promptTemplate, [
            '{INTENTS}' => '- ' . implode("\n- ", $intents),
            '{TOPICS}'  => '- ' . implode("\n- ", $topics),
            '{ITEMS}'   => (string) json_encode($items, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        ]);
    }
}
