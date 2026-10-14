# Environnement Docker

Tout l'environnement de développement (base de données, mails, backend, frontend) se lance avec **une seule commande**, depuis la racine du dépôt :

```powershell
docker compose up -d --build
```

Le `compose.yaml` de la racine se contente d'inclure [compose.yaml](compose.yaml) de ce dossier. Toutes les commandes `docker compose …` ci-dessous se lancent donc depuis la racine, `backend/` ou `frontend/`. Dans ce dossier `docker/`, c'est ce fichier qui est lu directement : on obtient la même stack, puisque les deux fichiers portent le même `name:`. L'`include` demande Docker Compose 2.20 ou plus. Avec une version plus ancienne, utiliser la forme longue `docker compose -f docker/compose.yaml …`, qui fonctionne partout.

Prérequis : Docker Desktop démarré. Les ports 8000, 5173, 5433, 8025 et 1025 doivent être libres ; arrêter au besoin un `symfony serve` ou un `npm run dev` lancé à la main.

Le premier démarrage prend quelques minutes (images, `composer install`, `npm install`). Les suivants sont rapides.

## Services

| Service | URL | Rôle |
|---|---|---|
| frontend | http://localhost:5173 | React + Vite (rechargement à chaud) |
| backend | http://localhost:8000/api | API Symfony ; **Swagger** sur http://localhost:8000/api |
| mailpit | http://localhost:8025 | Boîte mail de développement : les emails de confirmation y arrivent |
| database | `localhost:5433` (utilisateur `app`, mot de passe `app`, base `app`) | PostgreSQL 16 |

Ouvrir le frontend sur **http://localhost:5173**, pas sur `127.0.0.1` : le cookie de refresh (`SameSite=Strict`) exige que le front et l'API (`localhost:8000`) soient sur le même site.

## Emails

Par défaut, **aucun email ne sort de la machine** : ils sont tous capturés par Mailpit, sur http://localhost:8025. C'est là qu'on clique le lien de confirmation.

Pour les recevoir dans une **vraie boîte** (ici Gmail, pour la formation) :

1. Activer la validation en deux étapes sur le compte Google, puis créer un **mot de passe d'application** sur https://myaccount.google.com/apppasswords (16 caractères).
2. Ajouter dans `backend/.env.local`, un fichier secret et non versionné :
   ```dotenv
   # @ de l'adresse encodé en %40 ; mot de passe d'application sans espaces
   MAILER_DSN=smtp://moncompte%40gmail.com:motdepasseappli@smtp.gmail.com:587
   # Gmail impose comme expéditeur l'adresse du compte
   MAILER_FROM=moncompte@gmail.com
   ```
3. Redémarrer le backend, puis envoyer un email de test :
   ```powershell
   docker compose restart backend
   docker compose exec backend php bin/console mailer:test moncompte@gmail.com
   ```
   Si l'email n'apparaît pas, regarder aussi dans les spams.

Pour revenir à Mailpit, supprimer ces deux lignes de `.env.local` et redémarrer le backend.
Tout autre service SMTP fonctionne de la même façon (Brevo, Mailtrap…), en changeant seulement `MAILER_DSN`.

## Ce qui est automatique

Au démarrage du conteneur backend ([backend/docker-entrypoint.sh](backend/docker-entrypoint.sh)) :

1. Si `backend/.env.local` n'existe pas, il est créé avec un `APP_SECRET` et un `JWT_PASSPHRASE` aléatoires, et `backend/.env.test.local` avec la même passphrase. Ces fichiers ne sont pas versionnés.
2. `composer install`.
3. Les clés JWT (`backend/config/jwt/*.pem`) sont générées si elles manquent.
4. La base est créée si besoin, puis les migrations Doctrine sont appliquées.

Le frontend fait `npm install` puis lance Vite. Son `node_modules` vit dans un volume Docker, distinct de celui de Windows.

## Commandes utiles

```powershell
# État des services
docker compose ps

# Logs (suivre le backend)
docker compose logs -f backend

# Commande Symfony dans le conteneur
docker compose exec backend php bin/console debug:router

# Tests backend (SQLite, ils ne touchent jamais la base PostgreSQL et n'envoient aucun email)
docker compose exec backend php bin/phpunit

# Nouvelle migration après modification d'une entité
docker compose exec backend php bin/console doctrine:migrations:diff

# Arrêter
docker compose down

# Tout remettre à zéro (supprime la base, vendor et node_modules des conteneurs)
docker compose down -v
```

## Fichiers

| Fichier | Rôle |
|---|---|
| [compose.yaml](compose.yaml) | Définition des 4 services et des volumes |
| [backend/Dockerfile](backend/Dockerfile) | PHP 8.2 + extensions (pdo_pgsql, intl, opcache, zip) + Composer |
| [backend/docker-entrypoint.sh](backend/docker-entrypoint.sh) | Préparation automatique du backend (voir ci-dessus) |
| [backend/router.php](backend/router.php) | Routeur du serveur PHP intégré : fichiers statiques de `public/`, sinon Symfony |

Le frontend utilise directement l'image `node:22-alpine`. La variable `VITE_USE_POLLING`, lue par `frontend/vite.config.js`, active la détection des modifications de fichiers faites sous Windows.
