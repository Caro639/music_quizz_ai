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
        try {
            // On encode la recherche pour l'URL (ex: "daniel balavoine l aziza")
            $query = urlencode($artist . ' ' . $title);

            $response = $this->httpClient->request('GET', "https://api.deezer.com/search?q={$query}");
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
