<?php

namespace App\Service;

use OpenAI\Client;

class AiValidator
{
    public function __construct(private Client $client)
    {
    }

    public function isAnswerCorrect(string $userAnswer, string $realTitle): bool
    {
        $prompt = "L'utilisateur a répondu '$userAnswer' pour la chanson '$realTitle'.
                   Réponds uniquement par OUI ou NON si c'est la bonne réponse (accepte les petites fautes).";

        $result = $this->client->chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        return str_contains(strtoupper($result->choices[0]->message->content), 'OUI');
    }
}
