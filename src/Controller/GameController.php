<?php

namespace App\Controller;

use App\Entity\Game;
use App\Service\MistralAiService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\DeezerService;

final class GameController extends AbstractController
{
    #[Route('/game/create', name: 'app_game_create')]
    public function create(EntityManagerInterface $manager): Response
    {
        $game = new Game();

        // On génère un token court de 6 caractères pour le partage
        $token = bin2hex(random_bytes(3));

        $game->setToken(strtoupper($token));
        $game->setStatus('waiting');
        $game->setScores([]);
        $game->setAnswers([]);

        $manager->persist($game);
        $manager->flush();

        return $this->redirectToRoute('app_game_show', ['id' => $game->getId()]);
    }

    #[Route('/game/{id}', name: 'app_game_show')]
    public function show(Game $game): Response
    {
        $topicUrl = "https://quiz-ia.com/game/{$game->getId()}";

        return $this->render('game/show.html.twig', [
            'game' => $game,
            'mercureUrl' => $topicUrl,
            'mercureHubUrl' => getenv('MERCURE_PUBLIC_URL') ?: 'https://localhost:3000/.well-known/mercure',
        ]);
    }

    #[Route('/api/game/{id}/start', name: 'api_game_start', methods: ['POST'])]
    public function start(
        Game $game,
        MistralAiService $ai,
        EntityManagerInterface $manager,
        HubInterface $hub,
        DeezerService $deezer,
    ): JsonResponse {

        $playlist = $ai->generateQuiz('Années 2000 rap français');
        // $playlist = $ai->generateQuiz('Années 90 Françaises');

        //
        $finalPlaylist = [];
        foreach ($playlist as $track) {
            $previewUrl = $deezer->getPreviewUrl($track['title'], $track['artist']);

            $finalPlaylist[] = [
                'title' => $track['title'],
                'artist' => $track['artist'],
                'preview_url' => $previewUrl // On ajoute le MP3 !
            ];
        }

        $game->setStatus('playing');
        $game->setAnswers($finalPlaylist);

        $manager->flush();

        // --- LE PUSH MERCURE ---
        // On crée une mise à jour liée à l'ID de cette partie
        $update = new Update(
            "https://quiz-ia.com/game/{$game->getId()}", // Le "Topic" (l'ID unique)
            json_encode([
                'status' => 'playing',
                'playlist' => $finalPlaylist,
                'message' => 'L\'IA a généré le quiz !'
            ])
        );

        $hub->publish($update);

        return $this->json([
            'message' => 'Le quiz a été généré par Mistral !',
            'playlist' => $finalPlaylist,
            'status' => 'success'
        ]);
    }
}
