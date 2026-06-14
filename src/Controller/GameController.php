<?php

namespace App\Controller;

use App\Entity\Game;
use App\Entity\Player;
use App\Repository\GameRepository;
use App\Service\DeezerService;
use App\Service\MistralAiService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Attribute\Route;

final class GameController extends AbstractController
{

    #[Route('/', name: 'app_home')]
    public function home(GameRepository $repository): Response
    {

        return $this->render('game/index.html.twig', [
            'waitingGames' => $repository->findWaitingGames(),
        ]);
    }

    #[Route('/game/join', name: 'app_game_join', methods: ['POST'])]
    public function join(Request $request, GameRepository $repository, EntityManagerInterface $manager, HubInterface $hub): Response
    {
        $token = strtoupper(trim($request->request->get('token', '')));

        if (!$token) {
            return $this->redirectToRoute('app_home');
        }

        $game = $repository->findOneByToken($token);

        if (!$game) {
            $this->addFlash('error', "Aucune partie trouvée avec le code « $token ».");
            return $this->redirectToRoute('app_home');
        }

        // On crée le joueur
        $nickname = trim($request->request->get('nickname', 'Joueur'));
        $player = new Player();
        $player->setNickname($nickname);
        $player->setScore(0);
        $player->setGame($game);

        $manager->persist($player);
        $manager->flush();

        // 💡 IMPORTANT : On stocke l'ID du joueur en session pour que le téléphone s'en souvienne
        $request->getSession()->set('player_id', $player->getId());

        $update = new Update(
            "https://quiz-ia.com/game/{$game->getId()}",
            json_encode([
                'action' => 'player_joined',
                'nickname' => $player->getNickname()
            ])
        );
        $hub->publish($update);
        // On le redirige vers la page du salon
        return $this->redirectToRoute('app_game_show', ['id' => $game->getId()]);

    }

    #[Route('/game/create', name: 'app_game_create')]
    public function create(Request $request, EntityManagerInterface $manager): Response
    {
        $nickname = (string) $request->request->get('nickname', 'Hôte');

        $game = new Game();

        // On génère un token court de 6 caractères pour le partage
        $token = bin2hex(random_bytes(3));

        $game->setToken(strtoupper($token));
        $game->setStatus('waiting');
        $game->setScores([]);
        $game->setAnswers([]);

        $manager->persist($game);

        // 2. Création du joueur Hôte
        $player = new Player();
        $player->setNickname($nickname);
        $player->setGame($game);
        $player->setScore(0);
        $manager->persist($player);

        $manager->flush();

        $request->getSession()->set('player_id', $player->getId());


        return $this->redirectToRoute('app_game_show', ['id' => $game->getId()]);
    }

    #[Route('/game/{id}', name: 'app_game_show')]
    public function show(Game $game, Request $request): Response
    {
        $topicUrl = "https://quiz-ia.com/game/{$game->getId()}";

        $playerId = $request->getSession()->get('player_id');

        return $this->render('game/show.html.twig', [
            'game' => $game,
            'isHost' => ($playerId === null), // S'il n'a pas d'ID joueur en session, c'est l'hôte sur son PC !
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

        $playlist = $ai->generateQuiz('Années 90 Françaises');

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


    #[Route('/api/game/{id}/guess', name: 'api_game_guess', methods: ['POST'])]
    public function validateAnswer(
        Game $game,
        Request $request,
        MistralAiService $ai,
        EntityManagerInterface $em,
        HubInterface $hub,
    ): JsonResponse {

        $session = $request->getSession();
        $scores = $session->get('quiz_score', 0);

        $body = json_decode($request->getContent(), true);
        $songIndex = (int) ($body['songIndex'] ?? 0);
        $answer = trim($body['answer'] ?? '');
        $playerName = trim($body['playerName'] ?? 'Joueur');

        $songs = $game->getAnswers();

        if (!isset($songs[$songIndex])) {
            return $this->json(['error' => 'Index de chanson invalide'], 400);
        }

        $song = $songs[$songIndex];

        // 1. Validation via Mistral
        $isCorrect = $ai->validateAnswer($answer, $song['title'], $song['artist']);

        if ($isCorrect) {
            $scores++;
            $session->set('quiz_score', $scores);
        }


        // 2. Mettre à jour le score du joueur
        $scores = $game->getScores();
        if (!isset($scores[$playerName])) {
            $scores[$playerName] = 0;
        }
        if ($isCorrect) {
            $scores[$playerName]++;
        }
        $game->setScores($scores);

        $topic = "https://quiz-ia.com/game/{$game->getId()}";

        // Broadcast score_update
        $hub->publish(new Update($topic, json_encode([
            'type' => 'score_update',
            'scores' => $scores,
        ])));


        $em->flush();

        return $this->json([
            'isCorrect' => $isCorrect,
            'currentScore' => $scores[$playerName],
            // 'points' => $pointsGagnes,
            // 'correctAnswer' => "$correctTitle - $correctArtist"
        ]);
    }
}
