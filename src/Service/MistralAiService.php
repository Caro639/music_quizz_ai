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

    public function generateQuiz(string $theme): array
    {
        $response = $this->httpClient->request('POST', 'https://api.mistral.ai/v1/chat/completions', [
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
                        'content' => "Génère 5 chansons pour un quiz sur le thème '$theme'. Format: {\"songs\": [{\"title\": \"...\", \"artist\": \"...\"}]}"
                    ]
                ],
                'response_format' => ['type' => 'json_object']
            ],
        ]);

        $data = $response->toArray();
        $content = $data['choices'][0]['message']['content'];

        return json_decode($content, true)['songs'] ?? [];
    }

    public function validateAnswer(string $answer, string $title, string $artist): bool
    {
        $response = $this->httpClient->request('POST', 'https://api.mistral.ai/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => 'mistral-small-latest',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Tu es un validateur de quiz musical. Réponds UNIQUEMENT par OUI ou NON, sans ponctuation ni explication.',
                    ],
                    [
                        'role' => 'user',
                        'content' => "La chanson est \"$title\" de \"$artist\". Le joueur a répondu \"$answer\".
                        Est-ce que sa réponse montre qu'il a trouvé le titre OU l'artiste ?
                        Sois indulgent avec l'orthographe et les fautes de frappe,
                        mais ne considère pas les réponses qui ne sont pas proches du titre ou de l'artiste comme correctes.",
                    ],
                ],
            ],
        ]);

        $data = $response->toArray();
        $content = $data['choices'][0]['message']['content'] ?? '';

        return str_contains(strtoupper($content), 'OUI');
    }

}
