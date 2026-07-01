import React, { useState, useEffect } from "react";

// On récupère "waitingGames" query bdd game status waiting
export default function LiveLobbiesList({
  waitingGames,
  gameId,
  playerId,
  mercureUrl,
  mercureHubUrl,
  token,
}) {
  const [lobbies, setLobbies] = useState(waitingGames || []);

  const [players, setPlayers] = useState(playerId ? [playerId] : []);

  useEffect(() => {
    if (!mercureUrl) {
      console.error(
        "❌ Erreur : 'mercureUrl' n'est pas fournie au composant LiveLobbiesList !",
      );
      return;
    }

    console.log("🔗 Tentative de connexion Mercure sur :", mercureUrl);

    let eventSource;
    try {
      // Initialisation de la connexion
      eventSource = new EventSource(mercureUrl);

      eventSource.onmessage = (event) => {
        const data = JSON.parse(event.data);
        //     if (data.action === "lobby_created") {
        //       setLobbies((prevLobbies) => [
        //         ...prevLobbies,
        //         {
        //           id: data.gameId,
        //           token: data.token,
        //           host: data.host,
        //           playersCount: 1,
        //           status: "waiting",
        //         },
        //       ]);
        //     }
        //   };

        // envoi de la data de tokenList
        if (data.action === "lobby_created") {
          setLobbies((prevLobbies) => [...prevLobbies, data]);
        }
      };

      eventSource.onerror = (err) => {
        console.error(
          "⚠️ Problème de connexion au Hub Mercure (vérifie que le conteneur/serveur tourne) :",
          err,
        );
      };
    } catch (e) {
      console.error(
        "❌ Impossible de créer l'EventSource. L'URL fournie est :",
        mercureUrl,
        e,
      );
    }

    return () => {
      if (eventSource) eventSource.close();
    };
  }, [mercureUrl]);

  //   copie du token
  const handleSelectToken = (token) => {
    const tokenInput = document.getElementById("token-input");
    if (tokenInput) {
      tokenInput.value = token;

      document.getElementById("nickname")?.focus();
    }
  };

  if (lobbies.length === 0) {
    return (
      <p className='text-muted'>
        Aucun salon ouvert pour le moment. Créez-en un !
      </p>
    );
  }

  return (
    <div
      className='lobby-buttons-grid'
      style={{
        display: "flex",
        gap: "10px",
        flexWrap: "wrap",
        margin: "15px 0",
        justifyContent: "center",
      }}
    >
      {lobbies.map((lobby) => (
        <button
          key={lobby.token}
          type='button'
          className='lobby-select-btn'
          onClick={() => handleSelectToken(lobby.token)}
          style={{
            padding: "10px 15px",
            background: "rgba(255,255,255,0.05)",
            border: "1px solid #00d2ff",
            color: "#fff",
            borderRadius: "5px",
            cursor: "pointer",
          }}
        >
          🎮 Salon : <strong>{lobby.token}</strong>
          <span style={{ fontSize: "0.8rem", opacity: 0.7, marginLeft: "8px" }}>
            👤 {lobby.host} ({lobby.playersCount} joueur
            {lobby.playersCount > 1 ? "s" : ""})
          </span>
        </button>
      ))}
    </div>
  );
}
