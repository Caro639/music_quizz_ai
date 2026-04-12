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
}
