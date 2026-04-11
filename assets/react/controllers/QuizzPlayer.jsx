import React, { useState, useEffect } from "react";

export default function QuizPlayer({ gameId, mercureUrl }) {
  const [status, setStatus] = useState("waiting");
  const [guess, setGuess] = useState("");

  useEffect(() => {
    // On s'abonne aux mises à jour en temps réel via Mercure
    const eventSource = new EventSource(mercureUrl);
    eventSource.onmessage = (event) => {
      const data = JSON.parse(event.data);
      setStatus(data.status);
      // Si une nouvelle musique commence, on la joue...
    };
    return () => eventSource.close();
  }, [mercureUrl]);

  const submitGuess = async () => {
    await fetch(`/api/game/${gameId}/guess`, {
      method: "POST",
      body: JSON.stringify({ answer: guess }),
    });
    setGuess("");
  };

  return (
    <div className='p-4 border rounded shadow'>
      <h2>Statut : {status}</h2>
      <input
        value={guess}
        onChange={(e) => setGuess(e.target.value)}
        placeholder='Devine le titre !'
        className='border p-2'
      />
      <button onClick={submitGuess} className='bg-blue-500 text-white p-2 ml-2'>
        Envoyer
      </button>
    </div>
  );
}
