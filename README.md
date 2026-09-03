# Open-CoolGed — GED SaaS Multi-Tenant (ex-Kaeged GED)

Implémentation du cahier des charges `Cahier_des_Charges_GED_SaaS_Multi_Tenant_V1.0`
(plateforme de Gestion Électronique de Documents, SaaS multi-tenant, ~1 000 utilisateurs,
plusieurs centaines de milliers de documents, hébergement cPanel/XAMPP).

**Documentation technique** : voir [`docs/`](docs/README.md) — architecture, modèle de
données, API, sécurité, workflows & règles métier, sauvegardes, déploiement, tests.

## Stack

- PHP 8.4 + Laravel 13 (monolithe modulaire)
- MySQL / MariaDB 8 (base locale `kaeged`)
- Blade + Bootstrap 5 (UI responsive, non-techniciens)
- Queue : `sync` en dev (`QUEUE_CONNECTION=sync`) — database queue pour prod cPanel
- Stockage : disque local `storage/app/private` (abstraction `StorageService`, S3 prêt via `GED_STORAGE_DISK`)
- IA / OCR / Office : couche d'adaptateurs (fournisseur `mock` par défaut)

## Installation locale (XAMPP)

```bash
cd E:\xampp\htdocs\kaeged
copy .env.example .env          # déjà fait lors du create-project
# .env : DB_CONNECTION=mysql, DB_HOST=127.0.0.1, DB_PORT=3306,
#        DB_DATABASE=kaeged, DB_USERNAME=root, DB_PASSWORD=secret
E:\xampp\mysql\bin\mysql.exe -u root -psecret -e "CREATE DATABASE kaeged CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php composer.phar install       # ou composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve               # http://localhost:8000
```

## Comptes de démonstration (seeder)

| Rôle | Email | Mot de passe |
|------|-------|--------------|
| Super Admin (plateforme) | `superadmin@kaeged.local` | `superadmin123` |
| Admin tenant (Entreprise Démo SA) | `admin@demo.local` | `password123` |
| Utilisatrice | `user@demo.local` | `password123` |
| Validateur | `validator@demo.local` | `password123` |

## Tests

```bash
php artisan test        # 45 tests, 140 assertions (sqlite :memory:, isolation incluse)
```

Couverture : isolation inter-tenant (CA-001/002), RBAC/refus explicite (CA-003, RM-008),
versioning + checksum + restauration (CA-004/005), workflows (CA-006/007, RM-014/015),
audit (CA-014), uploads (CA-012), quotas (CA-018, RM-020), secrets (CA-011),
hors webroot (CA-013), pagination (CA-015), tenant suspendu (CA-017),
IA (CA-009/010/016, RM-016/017), Office fallback (CA-019), RAG filtré (RM-019),
sauvegarde/restauration (CA-020, démontré sur MySQL réel + test automatisé),
rétention documentaire (RM-030), SSO par tenant (V2).

## Sauvegardes et planification (CDG §41, §24)

```bash
php artisan ged:backup             # dump SQL (mysqldump) + export JSON portable + fichiers, rétention 30 j
php artisan ged:restore            # restaure la sauvegarde la plus récente
php artisan ged:restore --dir=...  # restaure une sauvegarde précise
php artisan ged:retention          # archive les documents arrivés à échéance de rétention
php artisan ged:retention --dry-run
php artisan schedule:list
```

Planification intégrée (`routes/console.php`) : sauvegarde quotidienne à 02:00,
rétention à 03:00. Binaires MySQL configurés via `.env` :
`GED_MYSQLDUMP_PATH` / `GED_MYSQL_PATH` (défaut `E:\xampp\mysql\bin\...`).

## Déploiement cPanel (CDG §39)

Prérequis hébergeur : **PHP 8.4+** (Laravel 13 / Symfony exigent PHP ≥ 8.4.1),
MySQL/MariaDB, Composer, SSH, cron.

1. Pousser le code dans `~/kaeged` (hors `public_html`), le contenu de `public/`
   dans `public_html` (ou un sous-dossier).
2. `composer install --no-dev --optimize-autoloader`
3. Créer la base MySQL + utilisateur, configurer `.env` (APP_ENV=production,
   APP_DEBUG=false, DB_*, `QUEUE_CONNECTION=database`, `GED_STORAGE_DISK=local`).
4. `php artisan key:generate`, `php artisan migrate --force`, `php artisan config:cache route:cache view:cache`
5. Cron : `* * * * * /usr/local/bin/php ~/kaeged/artisan schedule:run >> /dev/null 2>&1`
   (lance sauvegarde 02:00 + rétention 03:00).
6. Stockage : `php artisan storage:link` si lien public requis ; les fichiers
   documents restent hors webroot (`storage/app/private`) — jamais d'URL publique (RM-012).
7. Seuil d'escalade (CDG §39) : si jobs asynchrones/stockage/CPU dépassent les
   limites du mutualisé, migrer le cœur vers un VPS en conservant l'architecture
   (queue Redis/worker, S3 via `GED_STORAGE_DISK=s3` + credentials, moteur de
   recherche externe).

## Architecture multi-tenant

- `TenantContext` : contexte tenant courant, défini par le middleware `tenant.context`
  (après `auth` — le route model binding est évité car il s'exécuterait avant).
- `TenantScope` (trait `BelongsToTenant`) : scope global `tenant_id` sur toutes les
  entités métier ; contexte absent ⇒ aucun enregistrement visible (`0=1`).
- `PermissionService::can()` : RBAC + portées (tenant/space/folder/document),
  refus explicite prioritaire (RM-008), garde inter-tenant (RM-001/002).
- API : jetons Bearer (hash sha256, table `api_tokens`), middleware `ApiAuth`.

## Modules (cf. CDG §9, 47-49)

- SaaS : tenants, suspension, quotas, paramètres, branding (F-001/002)
- Identité : utilisateurs, groupes, rôles RBAC, MFA TOTP (F-003/004/005/025)
- GED : espaces, dossiers, documents, versions, métadonnées, tags, corbeille,
  archivage, rétention (F-006→010, 014)
- Affichage : vue liste ⇄ vue cartes (Documents & Mes documents), regroupements
  par métadonnées/référentiels/propriétés avec comptage, persistance session
- Dimensions V02 : domaine/processus/référentiels d'application liés à la création
  (formulaire ou import CSV), modifiables depuis la fiche, assignables aux
  utilisateurs (poste→pays) par l'admin
- Internationalisation FR/EN (sélecteur de langue navbar, persistant par
  utilisateur), préférences d'affichage durables (vue liste/cartes et
  colonnes configurables Documents/Mes documents en base), badge de
  notifications avec rafraîchissement périodique
- Reset des contenus d'un tenant : UI super admin (Tenants) et admin tenant
  (Paramètres → zone dangereuse), sauvegarde préalable obligatoire,
  CLI `tenant:reset --dry-run`
- Processus : workflows paramétrables, tâches, délégation, notifications (F-011, 015)
- Recherche : structurée + plein texte (F-012, 021)
- Partage interne contrôlé (F-016), audit (F-013), tableaux de bord (F-024)
- Office : session token-scopée, verrouillage, fallback téléchargement/réimport (F-017)
- IA/OCR : adaptateurs `AiProvider`, jobs + résultats, validation humaine (F-018/019/020)
- RAG : index par tenant, filtrage droits avant sélection (F-022, RM-019)

## Fichiers clés

- `app/Services/PermissionService.php` — moteur RBAC
- `app/Services/DocumentService.php` — import, versions, restauration
- `app/Services/WorkflowService.php` — moteur de workflow
- `app/Services/AiService.php` + `app/Services/Providers/` — adaptateurs IA/OCR
- `app/Services/OfficeService.php` — édition en ligne + repli
- `app/Services/RagService.php` — recherche sémantique filtrée
- `app/Models/Scopes/TenantScope.php` — frontière multi-tenant
