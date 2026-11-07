<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class MistralAiService
{
    private string $apiKey;
    private HttpClientInterface $httpClient;

    public function __construct(HttpClientInterface $httpClient, string $mistralApiKey)
    {
        $this->httpClient = $httpClient;
        $this->apiKey = $mistralApiKey;
    }

    public function generateQuiz(string $theme, int $count = 5, array $exclude = []): array
    {
        $excludeText = empty($exclude)
            ? ''
            : " Ne propose aucune des chansons suivantes, déjà utilisées : " . implode(', ', $exclude) . '.';

        $data = $this->requestWithRetry([
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => 'mistral-small-latest',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Tu es un expert musical. Réponds uniquement en JSON.'
                    ],
                    [
                        'role' => 'user',
                        'content' => "Génère $count chansons connues et populaires pour un quiz sur le thème '$theme',
                        pour être sûr de les retrouver facilement sur Deezer.$excludeText
                        Format: {\"songs\": [{\"title\": \"...\", \"artist\": \"...\"}]}"
                    ]
                ],
                'response_format' => ['type' => 'json_object']
            ],
        ]);

        $content = $data['choices'][0]['message']['content'];

        return json_decode($content, true)['songs'] ?? [];
    }

    /**
     * Appelle Mistral en réessayant après une pause si l'API répond 429 (limite de débit).
     */
    private function requestWithRetry(array $options, int $maxRetries = 3): array
    {
        for ($try = 0; ; $try++) {
            $response = $this->httpClient->request('POST', 'https://api.mistral.ai/v1/chat/completions', $options);

            if ($response->getStatusCode() !== 429 || $try >= $maxRetries) {
                return $response->toArray();
            }

            $retryAfter = (int) ($response->getHeaders(false)['retry-after'][0] ?? 0);
            sleep(max(1, min($retryAfter ?: 2 ** ($try + 1), 10)));
        }
    }

    public function validateAnswer(string $answer, string $title, string $artist): bool
    {
        $data = $this->requestWithRetry([
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => 'mistral-small-latest',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Tu es un validateur de quiz musical. Réponds uniquement en JSON.',
                    ],
                    [
                        'role' => 'user',
                        'content' => "La chanson est \"$title\" de \"$artist\". Le joueur a répondu \"$answer\".
                        Est-ce que sa réponse montre qu'il a trouvé le titre OU l'artiste ?
                        Sois indulgent avec l'orthographe et les fautes de frappe,
                        mais ne considère pas les réponses qui ne sont pas proches du titre ou de l'artiste comme correctes.
                        Réponds strictement au format JSON : {\"correct\": true} ou {\"correct\": false}.",
                    ],
                ],
                'response_format' => ['type' => 'json_object'],
            ],
        ]);

        $content = $data['choices'][0]['message']['content'] ?? '{}';

        // parsing JSON strict au lieu d'une recherche de sous-chaîne "OUI" (fausse positive sur ex. "éblOUIssant")
        return (bool) (json_decode($content, true)['correct'] ?? false);
    }


}
