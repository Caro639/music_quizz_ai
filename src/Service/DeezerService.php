<?php

// src/Service/DeezerService.php
namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class DeezerService
{
    public function __construct(
        private HttpClientInterface $httpClient
    ) {
    }

    public function getPreviewUrl(string $title, string $artist): ?string
    {
        // plusieurs formulations pour maximiser les chances de trouver le titre sur Deezer
        $queries = [
            $artist . ' ' . $title,
            $title . ' ' . $artist,
            $title,
        ];

        foreach ($queries as $query) {
            $preview = $this->search($query);
            if ($preview) {
                return $preview;
            }
        }

        return null;
    }

    private function search(string $query): ?string
    {
        try {
            $response = $this->httpClient->request('GET', 'https://api.deezer.com/search?q=' . urlencode($query));
            $data = $response->toArray();

            // Si Deezer trouve des résultats, on prend le premier et on retourne le preview MP3
            if (!empty($data['data'])) {
                return $data['data'][0]['preview'] ?? null;
            }
        } catch (\Exception $e) {
            // En cas de pépin avec l'API, on gère l'erreur proprement
            return null;
        }

        return null;
    }
}
