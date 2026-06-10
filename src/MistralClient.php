<?php

/**
 * MistralClient — appel HTTP (cURL) vers une API de chat compatible Mistral/OpenAI.
 *
 * Gère timeout, retries avec backoff et force une sortie JSON stricte.
 * Ne connaît RIEN du métier : il transporte un couple (system, user) et renvoie
 * le texte brut de la réponse. La validation des étiquettes se fait en aval.
 */
class MistralClient
{
    /**
     * @param array<string,mixed> $config Sous-tableau 'ai' de config.php
     * @param RunTrace|null $trace Journal d'exécution (chaque tentative HTTP y est notifiée)
     */
    public function __construct(private array $config, private ?RunTrace $trace = null) {}

    /** Vrai si une clé API est présente (sinon on ne tente aucun appel). */
    public function isConfigured(): bool
    {
        return is_string($this->config['api_key'] ?? null) && $this->config['api_key'] !== '';
    }

    /**
     * Envoie (system + user) et renvoie le contenu texte de la réponse de l'assistant.
     *
     * @throws RuntimeException en cas d'échec après tous les retries.
     */
    public function chat(string $system, string $user): string
    {
        $payload = [
            'model'       => $this->config['model'],
            'temperature' => $this->config['temperature'],
            'messages'    => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $user],
            ],
            // Force une réponse JSON (barrière anti texte libre).
            'response_format' => ['type' => 'json_object'],
        ];

        $maxRetries = max(1, (int) $this->config['max_retries']);
        $lastError = 'inconnue';

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $t0 = hrtime(true);
            [$body, $httpCode, $curlError] = $this->httpPost($payload);
            $ms = (hrtime(true) - $t0) / 1e6; // durée réseau de CETTE tentative (hors backoff)

            if ($curlError !== '') {
                // Erreur réseau (timeout, DNS, etc.) -> transitoire, on retente.
                $lastError = "réseau : $curlError";
                $this->trace?->httpAttempt($attempt, $httpCode, $ms, $curlError, 'reessai_reseau');
            } elseif ($httpCode >= 200 && $httpCode < 300) {
                $content = $this->extractContent($body);
                if ($content !== null) {
                    $this->trace?->httpAttempt($attempt, $httpCode, $ms, '', 'succes');
                    return $content;
                }
                $lastError = 'réponse JSON inattendue';
                $this->trace?->httpAttempt($attempt, $httpCode, $ms, '', 'reponse_invalide');
            } elseif ($httpCode === 429 || $httpCode >= 500) {
                // Limite de débit ou erreur serveur -> transitoire, on retente.
                $lastError = "HTTP $httpCode";
                $this->trace?->httpAttempt($attempt, $httpCode, $ms, '', 'reessai_http');
            } else {
                // 4xx non transitoire (clé invalide, mauvaise requête) -> inutile de retenter.
                $this->trace?->httpAttempt($attempt, $httpCode, $ms, '', 'refus');
                throw new RuntimeException("Appel IA refusé (HTTP $httpCode) : " . substr($body, 0, 200));
            }

            if ($attempt < $maxRetries) {
                sleep((int) $this->config['retry_delay'] * $attempt); // backoff linéaire
            }
        }

        throw new RuntimeException("Appel IA échoué après $maxRetries tentative(s) ($lastError).");
    }

    /**
     * Exécute la requête POST.
     *
     * @param array<string,mixed> $payload
     * @return array{0:string,1:int,2:string} [corps, codeHttp, erreurCurl]
     */
    private function httpPost(array $payload): array
    {
        $ch = curl_init($this->config['endpoint']);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => (int) $this->config['timeout'],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $this->config['api_key'],
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);

        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        return [$body === false ? '' : (string) $body, $httpCode, $err];
    }

    /** Extrait le champ message.content d'une réponse chat-completions. */
    private function extractContent(string $body): ?string
    {
        $data = json_decode($body, true);
        if (!is_array($data)) {
            return null;
        }
        $content = $data['choices'][0]['message']['content'] ?? null;
        return is_string($content) ? $content : null;
    }
}
