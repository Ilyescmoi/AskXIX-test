<?php

namespace App\Libraries;

use RuntimeException;
use Throwable;

/**
 * GroundingJudge — étiquette la FIDÉLITÉ de chaque réponse à la base de connaissance.
 *
 * Calque exact de QualityJudge (lots, LabelParsing, RunTrace, repli "indetermine"),
 * mais reçoit en plus la BASE ENTIÈRE comme contexte. Pour chaque réponse, le modèle
 * choisit une étiquette dans la liste FERMÉE d'ancrage (fondee/partielle/non_fondee/
 * hors_base/indetermine). Il ne produit AUCUN chiffre : l'agrégation reste en PHP.
 */
class GroundingJudge
{
    use LabelParsing;

    private string $promptTemplate;

    private string $promptFile;

    /**
     * @param array<string,mixed> $config Configuration complète
     * @param RunTrace|null $trace Journal d'exécution (phase "ancrage")
     */
    public function __construct(
        private LlmClient $client,
        private array $config,
        string $promptPath,
        private ?RunTrace $trace = null
    ) {
        $tpl = @file_get_contents($promptPath);
        if ($tpl === false) {
            throw new RuntimeException("Prompt de fidélité introuvable : $promptPath");
        }
        $this->promptTemplate = $tpl;
        $this->promptFile = basename($promptPath);
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @param string $baseContext Base de connaissance entière (texte concaténé)
     * @return array<int,array{ancrage:string}> indexé par id de ligne
     */
    public function judge(array $rows, string $baseContext): array
    {
        $labels = $this->config['grounding_labels'];

        // Repli par défaut pour toutes les lignes.
        $out = [];
        foreach ($rows as $r) {
            $out[$r['id']] = ['ancrage' => 'indetermine'];
        }

        // Sans clé API ou sans base -> tout reste "indetermine" (la vérif d'images, elle,
        // tourne quand même, côté ImageChecker).
        if (!$this->client->isConfigured() || trim($baseContext) === '') {
            return $out;
        }

        $system = 'Tu vérifies si une réponse de chatbot est étayée par la base fournie. '
                . 'Tu juges uniquement d\'après la base, sans connaissance extérieure. '
                . 'Si tu n\'es pas sûr, réponds "indetermine". Aucun chiffre, aucun texte libre, JSON valide uniquement.';
        $this->trace?->registerComponent('ancrage', $system, $this->promptTemplate, $this->promptFile);

        $batchSize = max(1, (int) ($this->config['base']['grounding_batch_size'] ?? 12));
        $maxChars  = (int) ($this->config['ai']['max_field_chars'] ?? 0);
        foreach (array_chunk($rows, $batchSize) as $batch) {
            $items = [];
            $sentIds = [];
            foreach ($batch as $r) {
                $items[] = [
                    'id'       => $r['id'],
                    'question' => Text::truncate($r['question'], $maxChars),
                    'reponse'  => Text::truncate($r['reponse'], $maxChars),
                ];
                $sentIds[] = (int) $r['id'];
            }

            $prompt = $this->buildPrompt($labels, $baseContext, $items);
            $this->trace?->beginBatch('ancrage', $sentIds, $prompt);

            try {
                $raw = $this->client->chat($system, $prompt);
                $results = $this->parseResults($raw);
            } catch (Throwable $e) {
                $this->trace?->failBatch($e->getMessage());
                continue;
            }

            $perId = [];
            $unknown = [];
            foreach ($results as $res) {
                $id = $this->parseAiId($res['id'] ?? null);
                if ($id === null || !isset($out[$id]) || !in_array($id, $sentIds, true)) {
                    $unknown[] = $res['id'] ?? '?';
                    continue;
                }
                if (isset($perId[$id])) {
                    $unknown[] = ($res['id'] ?? '?') . ' (doublon)';
                    continue;
                }
                $rawAnc = is_string($res['ancrage'] ?? null) ? trim($res['ancrage']) : '';
                $out[$id] = ['ancrage' => $this->validateLabel($rawAnc, $labels)];
                $perId[$id] = [
                    'brut'    => ['ancrage' => $rawAnc],
                    'retenu'  => $out[$id],
                    'corrige' => $out[$id]['ancrage'] !== $rawAnc,
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
    private function buildPrompt(array $labels, string $baseContext, array $items): string
    {
        return strtr($this->promptTemplate, [
            '{GROUNDING_LABELS}' => '- ' . implode("\n- ", $labels),
            '{BASE}'             => $baseContext,
            '{ITEMS}'            => (string) json_encode($items, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        ]);
    }
}
