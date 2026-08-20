# API REST

Préfixe : `/api/v1` — toutes les routes passent par le middleware `ApiAuth` (jeton Bearer).

## Authentification

1. Créer un jeton (CLI ou tinker) :

```bash
php artisan tinker
```

```php
$token = bin2hex(random_bytes(32));
App\Models\ApiToken::create([
    'tenant_id' => 1,
    'user_id' => 2,                       // utilisateur du tenant
    'name' => 'intégration ERP',
    'token_hash' => App\Models\ApiToken::hash($token),
]);
echo $token; // à conserver côté client — seul le hash est stocké (SEC-011)
```

2. Utiliser le jeton :

```
Authorization: Bearer <jeton>
```

- Jeton expiré / inconnu → `401`
- Compte ou tenant suspendu → `403`
- Chaque requête est résolue dans le contexte du tenant du jeton (isolation stricte).

## Endpoints

### Identité

| Méthode | URL | Description |
|---------|-----|-------------|
| GET | `/api/v1/me` | Profil de l'utilisateur du jeton |

### Documents

| Méthode | URL | Description |
|---------|-----|-------------|
| GET | `/api/v1/documents` | Liste paginée des documents accessibles (filtre `q` sur titre/référence) |
| GET | `/api/v1/documents/{id}` | Détail d'un document (versions, type, espace, tags, métadonnées) |

La liste ne retourne que les documents accessibles à l'utilisateur
(RBAC + partages actifs). Un document d'un autre tenant → `404`.

### Recherche

| Méthode | URL | Description |
|---------|-----|-------------|
| GET | `/api/v1/search?q=...` | Recherche par titre/référence, limitée aux droits |
| GET | `/api/v1/rag?q=...` | Réponse RAG (mock) avec sources ; segments filtrés par droits (RM-019) |

### IA

| Méthode | URL | Description |
|---------|-----|-------------|
| POST | `/api/v1/documents/{id}/ai?job_type=summary` | Lance un job IA (summary, qa, classification, extraction…) ; résultat via la relation `result` |

Refus si l'utilisateur n'a pas la permission `ai.use` sur le document (`403`).

### Tâches

| Méthode | URL | Description |
|---------|-----|-------------|
| GET | `/api/v1/tasks` | Tâches de workflow en attente de l'utilisateur |

## Formats

- Réponses JSON, pagination Laravel standard (`data`, `links`, `meta`).
- Erreurs : `{"error": "message"}` avec codes HTTP appropriés (401/403/404/422).

## Exemples

```bash
# Liste des documents accessibles
curl -H "Authorization: Bearer $TOKEN" http://localhost/kaeged/public/api/v1/documents

# Recherche
curl -H "Authorization: Bearer $TOKEN" "http://localhost/kaeged/public/api/v1/search?q=contrat"

# Lancement d'un job IA
curl -X POST -H "Authorization: Bearer $TOKEN" \
  "http://localhost/kaeged/public/api/v1/documents/1/ai?job_type=summary"
```

## Évolutions V2

- Webhooks sortants (signature, retry, idempotence).
- Connecteurs ERP/CRM/messagerie.
- SSO/OIDC par tenant (routes web `sso.start` / `sso.callback`, flux mock activable via `ged.sso_enabled` + paramètre tenant `sso_enabled`).
