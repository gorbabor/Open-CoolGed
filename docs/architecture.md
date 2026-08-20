# Architecture technique

## Vue d'ensemble

Monolithe modulaire Laravel 13 (PHP 8.4) pour le cœur métier (Web + API),
base MySQL/MariaDB, stockage documentaire abstrait, traitements asynchrones
via jobs, services externes encapsulés par des adaptateurs.

```
Navigateur Web
      │
      ▼
Application Laravel / PHP 8.4 (E:\xampp\htdocs\kaeged)
      │
      ├── MySQL / MariaDB (base kaeged, utf8mb4)
      ├── Stockage documents : disque local storage/app/private (abstraction, S3 prêt)
      ├── Queue : sync en dev (database en prod cPanel)
      ├── Cron / Scheduler : sauvegarde 02:00, rétention 03:00
      ├── Passerelle IA : AiService + adaptateurs (mock par défaut)
      ├── Édition Office : OfficeService (session token-scopée, fallback)
      └── SMTP : notifications (configurable, log en dev)
```

## Couches

| Couche | Composants |
|--------|-----------|
| HTTP / Routing | `routes/web.php`, `routes/api.php`, middlewares (`auth`, `tenant.context`, `tenant.active`, `superadmin`, `ApiAuth`) |
| Contrôleurs | Résolution manuelle des modèles via `app/Http/Controllers/Controller.php` (helpers `doc()`, `space()`, …) — **jamais de route model binding** (voir Multi-tenant) |
| Services | `app/Services/` : Permission, Document, Workflow, Ai, Office, Rag, Backup, Quota, Storage, Audit, Notification, Mfa, TextExtractor, SystemRole |
| Modèles | `app/Models/` avec trait `BelongsToTenant` (scope global) |
| Migration/Seed | `database/migrations/`, `database/seeders/DatabaseSeeder.php` |
| Vues | Blade + Bootstrap 5 (`resources/views/`) |
| Console | `routes/console.php` (scheduler), `app/Console/Commands/` |

## Multi-tenant (point critique)

- **`TenantContext`** (`app/Support/TenantContext.php`) : singleton de contexte tenant courant.
- **`SetTenantContext`** : middleware **de route** (alias `tenant.context`), exécuté **après `auth`**,
  qui fixe le contexte depuis l'utilisateur authentifié.
- **`TenantScope`** (`app/Models/Scopes/TenantScope.php`) : scope Eloquent global ajouté par le trait
  `BelongsToTenant` — toute requête filtre sur `tenant_id` ; sans contexte, `0=1` (aucun résultat).
- **Pourquoi pas de route model binding** : le binding s'exécute avant les middlewares de route,
  donc avant que le contexte tenant existe ; il résoudrait des objets d'un autre tenant (ou 404 partout).
  Les contrôleurs utilisent donc des helpers de résolution (`$this->doc($id)`).
- **Double garde** : `PermissionService::can()` refuse explicitement toute ressource dont
  `tenant_id` diffère de celui de l'utilisateur (RM-001/002).
- **Exceptions au scope** : `Tenant` (jamais scopé), `User` (résolu via `withoutGlobalScopes()`
  quand nécessaire), `ApiToken` (localisé par hash unique — SEC-011), `Permission`, `RolePermission`
  (lié par `role_id`).

## Sécurité applicative

- Auth : sessions Laravel, cookies Secure/HttpOnly/SameSite (SEC-004), protection CSRF native (SEC-005).
- RBAC : `PermissionService` + tables `roles`, `permissions`, `role_permission` (portées tenant/space/folder/document, refus explicite prioritaire).
- Uploads : validation MIME (liste blanche), taille max tenant, quota, stockage hors webroot (RM-012/013).
- API : jetons Bearer hashés (sha256) en table `api_tokens`, middleware `ApiAuth`.
- IA : `AiService` vérifie permission + activation tenant avant tout appel (CA-009) ;
  fournisseurs via interface `AiProvider` (jamais codés en dur).
- Journal d'audit : `AuditService` + table `audit_logs` (action, acteur, ressource, IP, détails JSON).

## Services externes (adaptateurs)

| Service | Abstration | Implémentation par défaut |
|---------|-----------|---------------------------|
| Stockage | `StorageService` (config `ged.storage_disk`) | `local` ; S3 via `GED_STORAGE_DISK=s3` |
| IA / OCR | `AiProvider` (interface) | `MockAiProvider` ; enregistrement via `AiService::registerProvider()` |
| Office | `OfficeService` (config `ged.office_provider`) | `fallback` (téléchargement → réimport) ; WOPI/externe à brancher |
| Email | Laravel Mail | `log` en dev, SMTP en prod |

## Points d'extension prévus

- Nouveau fournisseur IA : implémenter `AiProvider`, `app(AiService::class)->registerProvider(...)`,
  renseigner `settings['ai_providers']` du tenant.
- Stockage S3 : configurer le disque `s3` de `config/filesystems.php` + `GED_STORAGE_DISK=s3`.
- Recherche plein texte avancée : brancher Meilisearch/OpenSearch (V2).
