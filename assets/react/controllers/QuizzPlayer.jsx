import React, { useState, useEffect } from "react";

/**
 * QuizzPlayer — écran de jeu pour UNE chanson
 *
 * Le joueur doit deviner soit le titre, soit l'artiste de la chanson.
 * Aucun indice n'est affiché — la validation est faite côté Symfony (Mistral AI).
 *
 * Props reçues de QuizzLobby :
 *   - gameId          : identifiant de la partie (UUID)
 *   - playlist        : tableau de chansons [{ title, artist }, ...]
 *   - currentSongIndex: index de la chanson en cours (géré par Mercure via QuizzLobby)
 *   - scores          : { "NomJoueur": points, ... } — mis à jour par Mercure
 */
export default function QuizzPlayer({
  gameId,
  playlist,
  currentSongIndex,
  scores,
}) {
  const [guess, setGuess] = useState("");
  const [answered, setAnswered] = useState(false); // empêche de répondre deux fois
  const [result, setResult] = useState(null); // "correct" | "incorrect" | null
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [playerName] = useState(
    () => sessionStorage.getItem("playerName") ?? "Joueur",
  );

  // Réinitialiser la saisie à chaque nouvelle chanson
  useEffect(() => {
    setGuess("");
    setAnswered(false);
    setResult(null);
  }, [currentSongIndex]);

  const currentSong = playlist[currentSongIndex];
  const totalSongs = playlist.length;

  // Sécurité : la playlist n'est pas encore chargée
  if (!currentSong) {
    return (
      <div className='glass-card text-center'>
        <div className='spinner'></div>
        <p className='mt-4'>Chargement de la question...</p>
      </div>
    );
  }

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (answered || !guess.trim()) return;

    setIsSubmitting(true);
    try {
      // --- ÉTAPE 2 : cette route Symfony n'existe pas encore ---
      // Elle recevra { songIndex, answer, playerName }
      // Elle répondra { correct: true/false }
      // Elle broadcastera via Mercure : next_song + score_update
      const response = await fetch(`/api/game/${gameId}/guess`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          songIndex: currentSongIndex,
          answer: guess.trim(),
          playerName,
        }),
      });

      if (response.ok) {
        const data = await response.json();
        setResult(data.correct ? "correct" : "incorrect");
        setAnswered(true);
      }
    } catch (error) {
      console.error("Erreur lors de l'envoi de la réponse :", error);
    } finally {
      setIsSubmitting(false);
    }
  };

  // Scores triés pour l'affichage
  const sortedScores = Object.entries(scores ?? {}).sort(
    ([, a], [, b]) => b - a,
  );

  return (
    <div className='glass-card animate-fade-in'>
      {/* Progression */}
      <div className='mb-4'>
        <p className='text-sm text-gray-400 mb-1'>
          Question {currentSongIndex + 1} / {totalSongs}
        </p>
        <div className='w-full bg-gray-700 rounded-full h-2'>
          <div
            className='bg-purple-500 h-2 rounded-full transition-all duration-500'
            style={{ width: `${((currentSongIndex + 1) / totalSongs) * 100}%` }}
          />
        </div>
      </div>

      {/* Lecteur audio Deezer */}
      <div className='text-center mb-6'>
        {currentSong.preview_url ? (
          <>
            <audio src={currentSong.preview_url} autoPlay />
          </>
        ) : (
          <p className='text-gray-500 italic'>
            Pas d'extrait disponible pour cette chanson.
          </p>
        )}
      </div>

      {/* Notre magnifique Vinyle animé ! */}
      <div className='vinyl-container'>💿</div>

      {/* Question */}
      <div className='text-center mb-6'>
        <p className='text-3xl mb-2'>🎵</p>
        <p className='text-xl font-bold'>Devine le titre ou l'artiste !</p>
      </div>

      {/* Formulaire de réponse */}
      {!answered ? (
        <form onSubmit={handleSubmit} className='flex gap-2 mb-6'>
          <input
            type='text'
            value={guess}
            onChange={(e) => setGuess(e.target.value)}
            placeholder='Titre ou artiste...'
            className='flex-1 border border-gray-600 bg-gray-800 text-white rounded p-2 focus:outline-none focus:border-purple-400'
            autoFocus
            disabled={isSubmitting}
          />
          <button
            type='submit'
            className='btn-neon'
            disabled={isSubmitting || !guess.trim()}
          >
            {isSubmitting ? "..." : "Valider"}
          </button>
        </form>
      ) : (
        /* Feedback après réponse */
        <div
          className={`text-center p-4 rounded mb-6 ${result === "correct" ? "bg-green-800" : "bg-red-900"}`}
        >
          {result === "correct" ? (
            <p className='text-xl'>✅ Bonne réponse ! +1 point</p>
          ) : (
            <p className='text-xl'>
              ❌ Raté ! C'était : <strong>{currentSong.title}</strong> de{" "}
              <strong>{currentSong.artist}</strong>
            </p>
          )}
          <p className='text-sm text-gray-300 mt-2'>
            En attente du passage à la chanson suivante...
          </p>
        </div>
      )}

      {/* Scores en temps réel */}
      {sortedScores.length > 0 && (
        <div className='border-t border-gray-600 pt-4'>
          <p className='text-sm text-gray-400 mb-2'>🏆 Scores</p>
          <ul>
            {sortedScores.map(([player, score]) => (
              <li key={player} className='flex justify-between text-sm py-1'>
                <span>{player}</span>
                <span className='font-bold'>{score} pts</span>
              </li>
            ))}
          </ul>
        </div>
      )}
    </div>
  );
}
