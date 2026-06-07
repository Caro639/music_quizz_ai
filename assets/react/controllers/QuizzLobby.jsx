import React, { useState, useEffect } from "react";
import QuizzPlayer from "./QuizzPlayer";

export default function QuizzLobby({
  gameId,
  mercureUrl,
  mercureHubUrl,
  token,
}) {
  const [isLoading, setIsLoading] = useState(false);
  const [status, setStatus] = useState("waiting");
  const [playlist, setPlaylist] = useState([]);
  const [currentSongIndex, setCurrentSongIndex] = useState(0);
  const [scores, setScores] = useState({}); // { "PlayerName": 5, ... }

  const [timeLeft, setTimeLeft] = useState(30);

  useEffect(() => {
    const hubUrl = new URL(mercureHubUrl);
    hubUrl.searchParams.append("topic", mercureUrl);
    const eventSource = new EventSource(hubUrl);

    eventSource.onmessage = (event) => {
      const data = JSON.parse(event.data);
      console.log("Annonce reçue de Symfony :", data);

      // Le jeu démarre : tous les joueurs reçoivent la playlist
      if (data.status === "playing") {
        if (data.playlist) setPlaylist(data.playlist);
        setCurrentSongIndex(0);
        setStatus("playing");
        setTimeLeft(30);
      }

      // Symfony dit de passer à la chanson suivante
      if (data.type === "next_song") {
        setCurrentSongIndex(data.currentSongIndex);
      }

      // Symfony met à jour les scores de tous les joueurs
      if (data.type === "score_update") {
        setScores(data.scores);
      }

      // Fin de partie
      if (data.type === "game_over") {
        setStatus("finished");
        setScores(data.scores);
      }
    };

    eventSource.onerror = () => console.error("Connexion Mercure perdue.");
    return () => eventSource.close();
  }, [mercureUrl]);

  // compte à rebours
  useEffect(() => {
    if (status !== "playing" || playlist.length === 0) return;

    if (timeLeft === 0) {
      handleTimeOut();
      return;
    }

    // Décompte toutes les secondes
    const timer = setInterval(() => {
      setTimeLeft((prev) => prev - 1);
    }, 1000);

    return () => clearInterval(timer); // Nettoyage de l'intervalle
  }, [timeLeft, status, playlist]);

  // Action lorsque le temps est écoulé
  const handleTimeOut = () => {
    // alert("Temps écoulé pour cette chanson !");
    // Empêcher les réponses tardives
    // setTimeLeft(0);
    // Passer à la chanson suivante
    // setCurrentSongIndex((prev) => prev + 1);

    goToNextQuestion();
  };

  const goToNextQuestion = () => {
    if (currentSongIndex < playlist.length - 1) {
      setCurrentSongIndex((prev) => prev + 1);
      setTimeLeft(30); // Réinitialiser le temps pour la prochaine question
    } else {
      setStatus("finished");
    }
  };

  const handleStartGame = async () => {
    setIsLoading(true);
    try {
      const response = await fetch(`/api/game/${gameId}/start`, {
        method: "POST",
      });
      if (response.ok) {
        const data = await response.json();
        setPlaylist(data.playlist); // L'hôte reçoit la playlist via le fetch
        setCurrentSongIndex(0);
        setStatus("playing");
        setTimeLeft(30);
      }
    } catch (error) {
      console.error("Erreur lors de l'appel à Mistral :", error);
    } finally {
      setIsLoading(false);
    }
  };

  // --- Écran de jeu ---
  if (status === "playing") {
    return (
      <QuizzPlayer
        gameId={gameId}
        playlist={playlist}
        currentSongIndex={currentSongIndex}
        scores={scores}
        timeLeft={timeLeft}
      />
    );
  }

  // --- Écran de fin ---
  if (status === "finished") {
    const sortedScores = Object.entries(scores).sort(([, a], [, b]) => b - a);
    return (
      <div className='glass-card text-center animate-fade-in'>
        <h2 className='text-neon text-3xl mb-6'>🏆 Résultats finaux</h2>
        <ol className='text-left mb-6'>
          {sortedScores.map(([player, score], i) => (
            <li key={player} className='mb-2 text-lg'>
              {i === 0 ? "🥇" : i === 1 ? "🥈" : "🥉"} {player} — {score} pts
            </li>
          ))}
        </ol>
      </div>
    );
  }

  // --- Salon d'attente ---
  return (
    <div className='glass-card text-center'>
      <h2 className='mb-4 text-2xl font-bold'>Code du salon : {token}</h2>
      {isLoading ? (
        <div className='loading-container'>
          <div className='spinner'></div>
          <p className='mt-4'>Mistral AI prépare ton quiz musical...</p>
        </div>
      ) : (
        <>
          <p className='mb-6'>Prêt à tester ta culture musicale ?</p>
          <button
            className='btn-neon'
            onClick={handleStartGame}
            disabled={status === "playing"}
          >
            Générer le quiz avec l'IA
          </button>
        </>
      )}
    </div>
  );
}
