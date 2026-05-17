# Music Quizz AI 🎵

Application de blind test musical généré par l'IA (Mistral), avec synchronisation en temps réel via Mercure.

**Stack :** Symfony 7.4 · React · Webpack Encore · PostgreSQL · Mercure

---

## Prérequis

- PHP 8.2+
- Node.js & npm
- [Symfony CLI](https://symfony.com/download)
- Docker Desktop (pour PostgreSQL)
- Composer

---

## Installation (première fois)

### 1. Cloner le projet et installer les dépendances

```bash
git clone https://github.com/Caro639/music_quizz_ai.git
cd music_quizz_ai
composer install
npm install
```

### 2. Configurer les variables d'environnement

Copier le fichier `.env` en `.env.local` et adapter les valeurs :

```bash
cp .env .env.local
```

Variables importantes dans `.env.local` :

```dotenv
# Clé API Mistral AI
MISTRAL_API_KEY=ta_cle_api_mistral

# Base de données (Docker)
DATABASE_URL="postgresql://app:!ChangeMe!@127.0.0.1:5432/app?serverVersion=16&charset=utf8"
```

### 3. Télécharger le binaire Mercure

Le binaire `mercure.exe` n'est pas versionné. Tu dois le télécharger manuellement :

1. Aller sur [github.com/dunglas/mercure/releases](https://github.com/dunglas/mercure/releases)
2. Télécharger `mercure_Windows_x86_64.zip` (v0.24.x)
3. Extraire **uniquement** `mercure.exe` dans `bin/mercure/`

### 4. Générer le certificat TLS pour Mercure

Mercure tourne sur `https://localhost:3000`. Son certificat doit être signé par le CA de la CLI Symfony (déjà approuvé par le navigateur).

> **Prérequis :** avoir lancé `symfony server:ca:install` au moins une fois.

```powershell
# Générer la clé privée
openssl genrsa -out bin/mercure/mercure.key 2048

# Créer le fichier de config (localhost.cnf)
# (déjà présent dans bin/mercure/localhost.cnf)

# Créer la CSR
openssl req -new -key bin/mercure/mercure.key -out bin/mercure/mercure.csr -config bin/mercure/localhost.cnf

# Signer avec le CA Symfony
openssl x509 -req `
  -in bin/mercure/mercure.csr `
  -CA "$env:APPDATA\symfony-cli\certs\rootCA.pem" `
  -CAkey "$env:APPDATA\symfony-cli\certs\rootCA-key.pem" `
  -CAcreateserial `
  -out bin/mercure/mercure.crt `
  -days 365 -sha256 `
  -extfile bin/mercure/localhost.cnf -extensions req_ext
```

### 5. Lancer les migrations

```bash
# Démarrer la base de données Docker
docker compose up -d

# Appliquer les migrations
php bin/console doctrine:migrations:migrate
```

---

## Lancer le projet

**3 terminaux sont nécessaires à chaque démarrage :**

### Terminal 1 — Hub Mercure (temps réel)

```powershell
.\start-mercure.ps1
```

> Lance le hub Mercure sur `https://localhost:3000`.  
> Laisser ce terminal ouvert. `Ctrl+C` pour arrêter.

### Terminal 2 — Serveur Symfony

```bash
symfony server:start
```

> Lance le serveur PHP sur `https://127.0.0.1:8000`.

### Terminal 3 — Assets JavaScript

```bash
npm run dev
# ou en mode watch (recompile automatiquement à chaque modification)
npm run watch
```

---

## Accéder à l'application

| URL                                          | Description               |
| -------------------------------------------- | ------------------------- |
| `https://127.0.0.1:8000`                     | Application principale    |
| `https://127.0.0.1:8000/game/create`         | Créer une nouvelle partie |
| `https://localhost:3000/.well-known/mercure` | Hub Mercure (SSE)         |

---

## Architecture Mercure

La CLI Symfony ne démarre **pas** automatiquement le hub Mercure sur Windows. Il faut le lancer manuellement via `start-mercure.ps1`.

```
Navigateur  ──SSE──►  https://localhost:3000/.well-known/mercure  (hub Mercure)
                                        ▲
Symfony  ──publish──►  HubInterface::publish()  ──►  MERCURE_URL (localhost:3000)
```

| Variable             | Valeur                                       | Rôle                                      |
| -------------------- | -------------------------------------------- | ----------------------------------------- |
| `MERCURE_URL`        | `https://localhost:3000/.well-known/mercure` | Utilisée par Symfony pour publier         |
| `MERCURE_PUBLIC_URL` | `https://localhost:3000/.well-known/mercure` | Utilisée par le navigateur pour s'abonner |
| `MERCURE_JWT_SECRET` | `!ChangeThisMercureHubJWTSecretKey!`         | Clé de signature des tokens JWT           |

---

## Commandes utiles

```bash
# Vider le cache Symfony
php bin/console cache:clear

# Créer une migration après modification d'une entité
php bin/console make:migration
php bin/console doctrine:migrations:migrate

# Vérifier les routes
php bin/console debug:router

# Lancer les tests
php bin/phpunit
```
