# formationClaude

Application d'authentification complète (API Symfony + frontend React), réalisée pendant une formation à **Claude Code**.

## Objectif du projet

Ce dépôt sert de fil rouge pour apprendre à développer avec Claude Code sur un vrai projet : contrat d'API, backend, frontend, tests, Docker, puis intégration GitHub.

Le produit lui-même est un système d'authentification complet :

- **Inscription** avec envoi d'un **email de confirmation** (lien valable 24 h, à usage unique) ;
- **Login** qui renvoie un **JWT** (access token de 15 min) ;
- **Refresh token** stocké dans un cookie `HttpOnly` et changé à chaque utilisation (rotation) ;
- **Logout** qui révoque le refresh token.

Le contrat d'API, source de vérité entre backend et frontend, est dans [docs/api-contract.md](docs/api-contract.md).

## Stack technique

| Partie | Technologies |
|---|---|
| Backend | PHP 8.2, Symfony 7.4, API Platform 4.4, lexik/jwt-authentication-bundle, gesdinet/jwt-refresh-token-bundle |
| Frontend | React 19, Vite 6, React Router |
| Base de données | PostgreSQL 16 |
| Emails | Symfony Mailer + [Mailpit](https://mailpit.axllent.org/) (boîte de réception locale) |
| Environnement | Docker Compose |

## Démarrage rapide

### Prérequis

- [Docker Desktop](https://www.docker.com/products/docker-desktop/), **démarré**, avec Docker Compose **2.20 ou plus** (vérifier avec `docker compose version`)
- [Git](https://git-scm.com/downloads)
- Ports libres : `8000`, `5173`, `5433`, `8025`, `1025`

Pour **lancer l'application**, rien d'autre à installer : PHP, Composer, Node et PostgreSQL tournent dans les conteneurs.

Pour **développer avec Claude Code**, le hook et le skill de vérification s'exécutent sur votre machine (voir [Développer avec Claude Code](#développer-avec-claude-code)). Il faut alors aussi :

- [Node.js](https://nodejs.org/) 20 ou plus ;
- [PHP](https://www.php.net/downloads) 8.2, avec les extensions `pdo_sqlite` (les tests tournent sur SQLite), `openssl` et `mbstring` activées dans `php.ini` ;
- [Composer](https://getcomposer.org/), puis `composer install` dans `backend/` et `npm install` dans `frontend/`.

### Installer et lancer

```bash
git clone https://github.com/houssem113/formationClaude.git
cd formationClaude
docker compose up -d --build
```

**C'est la seule commande à lancer.** Elle marche depuis la racine ou un sous-dossier comme `backend/` ou `frontend/` : Docker Compose remonte jusqu'au `compose.yaml` racine. Seule exception : dans `docker/`, c'est `docker/compose.yaml` qui est lu directement, ce qui donne la même stack. Forme équivalente, utilisable depuis n'importe où : `docker compose -f <chemin du dépôt>/docker/compose.yaml up -d --build`.

Le premier démarrage prend quelques minutes (téléchargement des images, `composer install`, `npm install`). Tout le reste est automatique :

- création de la base de données et application des migrations ;
- génération des clés JWT ;
- création de `backend/.env.local` avec des secrets aléatoires (fichier non versionné).

Vérifier que tout est prêt (le service `backend` doit être `healthy`) :

```bash
docker compose ps
```

### Accéder à l'application

| Service | Adresse | Rôle |
|---|---|---|
| **Application** | http://localhost:5173 | Frontend React : inscription, login, page protégée |
| **API + Swagger** | http://localhost:8000/api | Documentation interactive de tous les endpoints |
| **Mailpit** | http://localhost:8025 | Boîte de réception locale : les emails de confirmation arrivent ici |
| PostgreSQL | `localhost:5433` | Base `app`, utilisateur `app`, mot de passe `app` |

> Ouvrez l'application sur **`localhost`**, pas sur `127.0.0.1` : le cookie de refresh (`SameSite=Strict`) exige que le frontend et l'API soient sur le même site.

### Essayer le parcours complet

1. Sur http://localhost:5173, créez un compte.
2. Ouvrez http://localhost:8025 et cliquez sur le lien de l'email « Confirmez votre adresse email ».
3. Connectez-vous : vous arrivez sur la page protégée.
4. Déconnectez-vous.

Par défaut, **aucun email ne sort de la machine** : tout est capturé par Mailpit. Pour recevoir les emails dans une vraie boîte (Gmail…), voir [docker/README.md](docker/README.md#emails).

## Commandes utiles

Ces commandes se lancent depuis le dépôt (racine ou sous-dossier). En dehors du dépôt, ajouter `-f <chemin du dépôt>/docker/compose.yaml` juste après `docker compose`.

```bash
# Arrêter
docker compose down

# Tout remettre à zéro (supprime la base et les dépendances installées dans les conteneurs)
docker compose down -v

# Suivre les logs du backend
docker compose logs -f backend

# Lancer une commande Symfony
docker compose exec backend php bin/console debug:router

# Tests backend (PHPUnit, sur SQLite : ils ne touchent jamais la base de développement)
docker compose exec backend php bin/phpunit

# Lint du frontend
docker compose exec frontend npm run lint
```

## Dépannage

| Problème | Solution |
|---|---|
| `port is already allocated` | Un autre programme utilise le port (un `symfony serve`, un `npm run dev` lancé à la main, un PostgreSQL local…). Arrêtez-le puis relancez `docker compose up -d --build`. |
| `failed to connect to the docker API` | Docker Desktop n'est pas démarré. |
| Connecté, mais déconnecté à chaque rechargement | Vous êtes sur `127.0.0.1` : utilisez http://localhost:5173. |
| Aucun email dans Mailpit | Un vrai service d'envoi est peut-être configuré dans `backend/.env.local` (`MAILER_DSN`). |
| Inscription refusée avec une erreur 429 | Limite de 3 inscriptions par minute et par IP : attendez une minute. |
| Le backend ne devient pas `healthy` | `docker compose logs backend` pour voir l'erreur. |

## Structure du dépôt

```
formationClaude/
├── backend/          API Symfony + API Platform (entités, sécurité JWT, emails, tests PHPUnit)
├── frontend/         Application React + Vite
├── docker/           Environnement de développement (compose.yaml, Dockerfile, entrypoint)
├── docs/             Contrat d'API
├── compose.yaml      Raccourci vers docker/compose.yaml (pour lancer depuis la racine)
├── .claude/          Configuration Claude Code : permissions, hook, skill, agents
├── .github/          Workflows GitHub Actions avec Claude
└── CLAUDE.md         Instructions du projet pour Claude Code
```

## Documentation

- [docs/api-contract.md](docs/api-contract.md) : contrat d'API (endpoints, corps, réponses, codes d'erreur)
- http://localhost:8000/api : Swagger, même contrat en version interactive
- [docker/README.md](docker/README.md) : détail de l'environnement Docker, configuration des emails réels
- [CLAUDE.md](CLAUDE.md) : règles du projet pour Claude Code

## Développer avec Claude Code

Le dépôt contient de quoi travailler avec [Claude Code](https://code.claude.com/docs). Le hook et le skill s'exécutent sur votre machine, pas dans Docker : ils demandent Node.js, PHP et les dépendances installées localement (voir [Prérequis](#prérequis)).

| Élément | Emplacement | Rôle |
|---|---|---|
| Instructions du projet | [CLAUDE.md](CLAUDE.md) | Règles communes, lancement, secrets |
| Permissions | [.claude/settings.json](.claude/settings.json) | Commandes autorisées, à confirmer, ou interdites |
| Hook Stop | [.claude/hooks/verify-on-stop.mjs](.claude/hooks/verify-on-stop.mjs) | Lance PHPUnit et/ou lint + build quand le code a changé, et bloque en cas d'échec |
| Skill `/verify` | [.claude/skills/verify/](.claude/skills/verify/SKILL.md) | Teste le parcours complet : inscription, email, confirmation, login, refresh, logout |
| Agents | [.claude/agents/](.claude/agents/) | `backend-dev` et `frontend-dev`, chacun limité à sa partie |
| GitHub Actions | [.github/workflows/](.github/workflows/) | Claude relit chaque pull request, et répond quand on écrit `@claude` dans une issue ou une PR |

Pour tester le parcours complet sur la stack lancée :

```bash
node .claude/skills/verify/e2e-auth.mjs
```
