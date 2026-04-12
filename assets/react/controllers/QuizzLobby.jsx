import React, { useState, useEffect } from "react";

export default function QuizzLobby({ gameId, mercureUrl, token }) {
  const [isLoading, setIsLoading] = useState(false);
  const [status, setStatus] = useState("waiting");
  const [playlist, setPlaylist] = useState([]);

  useEffect(() => {
    // On s'abonne aux annonces de Symfony pour CETTE partie

    const hubUrl = new URL("/.well-known/mercure", window.location.origin);
    hubUrl.searchParams.append("topic", mercureUrl);
    const eventSource = new EventSource(hubUrl);

    eventSource.onmessage = (event) => {
      const data = JSON.parse(event.data);
      console.log("Annonce reçue de Symfony :", data);

      if (data.status === "playing") {
        if (data.playlist) setPlaylist(data.playlist); // Les autres joueurs reçoivent la playlist via Mercure
        setStatus("playing");
      }
    };

    eventSource.onerror = () => console.error("Connexion Mercure perdue.");

    return () => eventSource.close(); // On ferme la connexion si on quitte la page
  }, [mercureUrl]);

  const handleStartGame = async () => {
    setIsLoading(true); // On lance l'animation de chargement

    try {
      const response = await fetch(`/api/game/${gameId}/start`, {
        method: "POST",
      });

      if (response.ok) {
        const data = await response.json();
        setPlaylist(data.playlist); // L'hôte reçoit la playlist via le fetch
        setStatus("playing");
      }
    } catch (error) {
      console.error("Erreur lors de l'appel à Mistral :", error);
    } finally {
      setIsLoading(false);
    }
  };

  if (status === "playing") {
    return (
      <div className='glass-card animate-fade-in'>
        <h2 className='text-neon'>🎶 Le Quiz commence ! 🎶</h2>
        {/* TODO: passer playlist au composant QuizPlayer */}
        <pre className='text-xs'>{JSON.stringify(playlist, null, 2)}</pre>
      </div>
    );
  }

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
            {status === "playing" ? "Jeu lancé !" : "Générer le quiz avec l'IA"}
          </button>
        </>
      )}
    </div>
  );
}
