# Contrat d'API — Authentification

Source de vérité entre `backend/` et `frontend/`. Toute modification d'endpoint met à jour ce fichier **avant** le code.

## Conventions

- Base URL : `http://localhost:8000/api`. Le frontend tourne sur `http://localhost:5173`.
- Documentation interactive (Swagger) : `http://localhost:8000/api`. Elle reprend ce contrat et doit rester alignée (`backend/src/OpenApi/AuthOpenApiFactory.php`).
- Requêtes et réponses en JSON (`Content-Type: application/json`), encodées en UTF-8.
- **Access token** : un JWT envoyé dans `Authorization: Bearer <jwt>`. Il dure 900 s (15 min). Le front le garde **en mémoire** uniquement, jamais dans localStorage.
- **Refresh token** : il circule dans un cookie `refresh_token`, jamais dans le corps des requêtes ou des réponses.
  - Attributs du cookie : `HttpOnly; Secure; SameSite=Strict; Path=/api/auth; Max-Age=2592000` (30 jours).
  - Rotation : chaque appel à `/api/auth/refresh` révoque l'ancien token et en émet un nouveau. Un token ne sert donc qu'une fois.
- Le front appelle les endpoints `/api/auth/*` avec `credentials: 'include'`.
- CORS : origine autorisée `http://localhost:5173` (variable `FRONTEND_URL`), avec `Access-Control-Allow-Credentials: true`.

### Format d'erreur (unique pour tous les endpoints)

```json
{
  "error": {
    "code": "VALIDATION_FAILED",
    "message": "Texte lisible par un humain",
    "violations": [
      { "field": "email", "message": "Cette adresse email n'est pas valide." }
    ]
  }
}
```

- `code` est stable et sert au traitement côté front. `message` sert uniquement à l'affichage et peut changer.
- `violations` n'est présent que pour `VALIDATION_FAILED`.

### Codes d'erreur transverses

| Status | Code | Quand |
|---|---|---|
| 400 | `INVALID_JSON` | Corps absent ou JSON mal formé |
| 422 | `VALIDATION_FAILED` | Champs invalides (détail dans `violations`) |
| 429 | `TOO_MANY_REQUESTS` | Limite de requêtes dépassée ; en-tête `Retry-After` (secondes) |
| 500 | `INTERNAL_ERROR` | Erreur inattendue |

## Règles métier

- **Email** : format valide, normalisé en minuscules, unique.
- **Mot de passe** : entre 8 et 4096 caractères, avec au moins une lettre et un chiffre. Il est stocké haché.
- **Token de confirmation d'email** :
  - 32 octets aléatoires encodés en base64url. Seul son hash SHA-256 est stocké en base.
  - Il est valable 24 h et ne sert qu'une fois.
  - Un renvoi invalide le token précédent.
- **Lien envoyé par email** : `{FRONTEND_URL}/confirm-email?token=<token>`. La page frontend appelle ensuite `POST /api/auth/confirm-email`.
- **Rate limit** :
  - login : 5 tentatives par minute, par couple IP + email ;
  - register et resend-confirmation : 3 requêtes par minute, par IP.

## Endpoints

| # | Méthode | Chemin | Authentification |
|---|---|---|---|
| 1 | POST | `/api/auth/register` | — |
| 2 | POST | `/api/auth/confirm-email` | — |
| 3 | POST | `/api/auth/resend-confirmation` | — |
| 4 | POST | `/api/auth/login` | — |
| 5 | POST | `/api/auth/refresh` | cookie `refresh_token` |
| 6 | POST | `/api/auth/logout` | cookie `refresh_token` (optionnel) |
| 7 | GET | `/api/me` | `Authorization: Bearer <jwt>` |

---

### 1. `POST /api/auth/register`

Crée un compte non confirmé et envoie l'email de confirmation.

**Corps**

```json
{ "email": "alice@example.com", "password": "secret123" }
```

**`201 Created`**

```json
{ "id": 1, "email": "alice@example.com", "isVerified": false }
```

**Erreurs**

| Status | Code | Quand |
|---|---|---|
| 400 | `INVALID_JSON` | Corps illisible |
| 422 | `VALIDATION_FAILED` | Email invalide ou mot de passe non conforme |
| 409 | `EMAIL_ALREADY_USED` | Un compte existe déjà avec cet email |
| 429 | `TOO_MANY_REQUESTS` | Limite atteinte |

---

### 2. `POST /api/auth/confirm-email`

Confirme l'adresse email à partir du token reçu par email.

**Corps**

```json
{ "token": "q3J0c2Vj..." }
```

**`200 OK`**

```json
{ "message": "Email confirmé." }
```

**Erreurs**

| Status | Code | Quand |
|---|---|---|
| 400 | `INVALID_JSON` | Corps illisible |
| 422 | `VALIDATION_FAILED` | `token` manquant ou vide |
| 400 | `INVALID_TOKEN` | Token inconnu ou déjà utilisé |
| 410 | `TOKEN_EXPIRED` | Token expiré : le front propose un renvoi |

---

### 3. `POST /api/auth/resend-confirmation`

Renvoie un email de confirmation et invalide le token précédent.

**Corps**

```json
{ "email": "alice@example.com" }
```

**`202 Accepted`** : la réponse est **toujours** identique, que le compte existe ou non, qu'il soit déjà confirmé ou non. Cela évite l'énumération des comptes.

```json
{ "message": "Si un compte non confirmé existe, un email a été envoyé." }
```

**Erreurs**

| Status | Code | Quand |
|---|---|---|
| 400 | `INVALID_JSON` | Corps illisible |
| 422 | `VALIDATION_FAILED` | Email absent ou mal formé |
| 429 | `TOO_MANY_REQUESTS` | Limite atteinte |

---

### 4. `POST /api/auth/login`

**Corps**

```json
{ "email": "alice@example.com", "password": "secret123" }
```

**`200 OK`**

```json
{
  "token": "eyJhbGciOiJSUzI1NiJ9...",
  "expiresIn": 900,
  "user": { "id": 1, "email": "alice@example.com", "roles": ["ROLE_USER"] }
}
```

En-tête : `Set-Cookie: refresh_token=<token>; HttpOnly; Secure; SameSite=Strict; Path=/api/auth; Max-Age=2592000`

**Erreurs**

| Status | Code | Quand |
|---|---|---|
| 400 | `INVALID_JSON` | Corps illisible |
| 401 | `INVALID_CREDENTIALS` | Email inconnu ou mot de passe faux (même réponse dans les deux cas) |
| 403 | `EMAIL_NOT_CONFIRMED` | Identifiants corrects mais email non confirmé |
| 429 | `TOO_MANY_REQUESTS` | Trop de tentatives |

---

### 5. `POST /api/auth/refresh`

Échange le refresh token, envoyé automatiquement dans le cookie, contre un nouvel access token. Le refresh token fait l'objet d'une rotation.

**Corps** : aucun.

**`200 OK`**

```json
{ "token": "eyJhbGciOiJSUzI1NiJ9...", "expiresIn": 900 }
```

En-tête : `Set-Cookie` avec un **nouveau** `refresh_token`, l'ancien étant révoqué.

**Erreurs**

| Status | Code | Quand |
|---|---|---|
| 401 | `INVALID_REFRESH_TOKEN` | Cookie absent, token expiré, révoqué ou déjà utilisé. Le cookie est effacé (`Max-Age=0`). |

---

### 6. `POST /api/auth/logout`

Révoque le refresh token et efface le cookie.

**Corps** : aucun.

**`204 No Content`** : le refresh token est supprimé en base et la réponse contient `Set-Cookie: refresh_token=; Max-Age=0; Path=/api/auth`. L'appel est **idempotent** : il renvoie `204` même sans cookie ou avec un cookie déjà invalide.

L'access token en cours reste techniquement valide jusqu'à son expiration (15 min au plus). Le front l'efface de sa mémoire.

---

### 7. `GET /api/me`

Renvoie l'utilisateur connecté.

**`200 OK`**

```json
{ "id": 1, "email": "alice@example.com", "roles": ["ROLE_USER"], "isVerified": true }
```

**Erreurs**

| Status | Code | Quand |
|---|---|---|
| 401 | `TOKEN_MISSING` | Pas d'en-tête `Authorization` |
| 401 | `TOKEN_INVALID` | JWT mal formé ou signature invalide |
| 401 | `TOKEN_EXPIRED` | JWT expiré : le front appelle `/api/auth/refresh` puis rejoue la requête |

## Comportement attendu côté frontend

- Sur une `401` dont le code vaut `TOKEN_EXPIRED`, `TOKEN_INVALID` ou `TOKEN_MISSING`, le front fait **un seul** appel à `POST /api/auth/refresh`, partagé entre toutes les requêtes en attente. Il rejoue ensuite la requête d'origine.
- Si le refresh renvoie `401 INVALID_REFRESH_TOKEN`, le front efface l'access token et redirige vers `/login`.
- Au chargement de l'application, il appelle `POST /api/auth/refresh` puis `GET /api/me` pour restaurer la session, puisque l'access token n'est gardé qu'en mémoire.
