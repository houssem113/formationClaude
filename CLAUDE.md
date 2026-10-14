# formationClaude

Monorepo : `backend/` (Symfony + API Platform), `frontend/` (React + Vite) et `docker/` (environnement de développement).
Le contrat d'API est dans @docs/api-contract.md : toute modification d'endpoint le met à jour,
ainsi que la doc Swagger (`backend/src/OpenApi/AuthOpenApiFactory.php`).

## Règles communes
- Environnement : Windows 11, PowerShell
- Le backend ne touche jamais `frontend/`, et inversement
- Ne jamais commiter : c'est moi qui commite
- Secrets uniquement dans `.env.local` (jamais dans `.env` versionné)
- Hook Stop (`.claude/hooks/verify-on-stop.mjs`) : si `backend/` a changé, `php bin/phpunit` ; si `frontend/` a changé,
  `npm run lint` + `npm run build`. Un échec bloque la fin de la réponse tant qu'il n'est pas corrigé (3 tentatives max)

## Lancer le projet (Docker)
Une seule commande, depuis la racine : `docker compose -f docker/compose.yaml up -d --build`
- Frontend : http://localhost:5173 (utiliser `localhost`, pas `127.0.0.1` : cookie `SameSite=Strict`)
- API + Swagger : http://localhost:8000/api
- Mailpit (emails de dev) : http://localhost:8025. Par défaut, tous les emails y sont capturés et aucun ne sort ;
  pour un vrai envoi (Gmail), mettre `MAILER_DSN` et `MAILER_FROM` dans `backend/.env.local` (cf. docker/README.md)
- PostgreSQL : `localhost:5433`, base/utilisateur/mot de passe `app`
- Base, migrations, clés JWT et `.env.local` sont créés automatiquement au démarrage
- Commandes backend dans le conteneur : `docker compose -f docker/compose.yaml exec backend php bin/console …`
- Tests : `php bin/phpunit` (en local ou dans le conteneur) ; ils utilisent SQLite et le mailer nul (`phpunit.dist.xml`)
- Détails : docker/README.md

## Authentification (objectif actuel)
Inscription + email de confirmation, login JWT, refresh token, logout.
