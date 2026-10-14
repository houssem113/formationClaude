---
name: verify
description: Teste de bout en bout le parcours d'authentification sur la stack Docker (inscription, email reçu dans Mailpit, clic sur le lien de confirmation, login, refresh du token, logout, puis refresh refusé). À lancer avant un commit, après une modification de l'authentification, ou quand on demande de vérifier que l'app fonctionne.
---

# Vérification du parcours d'authentification

Ce skill rejoue contre la stack Docker le parcours décrit dans `docs/api-contract.md`, avec de vraies requêtes HTTP et un vrai email.

## 1. S'assurer que la stack tourne

```powershell
docker compose -f docker/compose.yaml ps
```

Les services `backend`, `database`, `mailpit` et `frontend` doivent être `Up`, et `backend` doit être `healthy`. Sinon, lancer la stack et attendre que `backend` soit `healthy` :

```powershell
docker compose -f docker/compose.yaml up -d --build
```

## 2. Lancer le parcours

Depuis la racine du dépôt :

```powershell
node .claude/skills/verify/e2e-auth.mjs
```

Le script crée un compte jetable `verify+<horodatage>@example.com` et vérifie, dans l'ordre :

| # | Étape | Attendu |
|---|---|---|
| 1 | Inscription | `201`, compte non confirmé |
| 2 | Login avant confirmation | `403 EMAIL_NOT_CONFIRMED` |
| 3 | Email reçu dans Mailpit | email trouvé (via l'API Mailpit), lien `…/confirm-email?token=…` présent |
| 4 | Clic sur le lien | la page frontend répond ; `POST /api/auth/confirm-email` donne `200`, puis `400 INVALID_TOKEN` si on réutilise le lien |
| 5 | Login | `200`, JWT accepté par `/api/me`, cookie `refresh_token` HttpOnly, Secure, SameSite=Strict, Path=/api/auth |
| 6 | Refresh du token | `200`, nouveau JWT valide, nouveau cookie ; l'ancien refresh token est refusé (`401`) |
| 7 | Logout | `204`, cookie effacé (`Max-Age=0`) |
| 8 | Refresh après logout | `401 INVALID_REFRESH_TOKEN` |

Code de sortie `0` : tout passe. Code `1` : le script s'arrête à la première étape en échec et affiche la réponse reçue.

## 3. Rendre compte

Donner le résultat étape par étape (copier la sortie du script). En cas d'échec, citer l'étape, le statut et le code d'erreur reçus, puis chercher la cause : `docker compose -f docker/compose.yaml logs backend --tail 50`.

## Causes d'échec connues

- **Étape 1 en `429 TOO_MANY_REQUESTS`** : l'inscription est limitée à 3 par minute et par IP. Attendre une minute avant de relancer.
- **Étape 3 : aucun email dans Mailpit** : un vrai service d'envoi (Gmail…) est sans doute configuré dans `backend/.env.local` (`MAILER_DSN`). Les emails ne passent alors plus par Mailpit.
- **Étape 4 : la page frontend ne répond pas** : le conteneur `frontend` n'est pas démarré, ou Vite est encore en train de s'installer (`docker compose -f docker/compose.yaml logs frontend`).
- **« Erreur inattendue » avec `ECONNREFUSED`** : la stack n'est pas lancée.

Variables optionnelles pour viser une autre adresse : `API_URL` (défaut `http://localhost:8000/api`) et `MAILPIT_URL` (défaut `http://localhost:8025`).
Chaque lancement laisse un compte `verify+…@example.com` en base. L'email de test est en revanche supprimé de Mailpit à la fin.
