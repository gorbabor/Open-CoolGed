# Déploiement

## 1. Installation locale (XAMPP)

### Prérequis

- **PHP 8.4+** (obligatoire : Laravel 13 / Symfony exigent PHP >= 8.4.1).
  XAMPP standard fournit PHP 8.2 → installer une build **Thread-Safe** 8.4 dans
  `E:\xampp\php84` et repointée `apache/conf/extra/httpd-xampp.conf`
  (LoadFile `php8ts.dll`, LoadModule `php8apache2_4.dll`, `PHPRC`, `PHPINIDir`).
- MySQL/MariaDB (XAMPP), Composer (ou `php composer.phar`).

### Installation

```bash
cd E:\xampp\htdocs\kaeged
# .env : DB_CONNECTION=mysql, DB_HOST=127.0.0.1, DB_PORT=3306,
#        DB_DATABASE=kaeged, DB_USERNAME=root, DB_PASSWORD=secret
E:\xampp\mysql\bin\mysql.exe -u root -psecret -e "CREATE DATABASE kaeged CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve                 # dev : http://localhost:8000
# ou via Apache : http://localhost/kaeged/public
```

### Comptes de démonstration (seeder)

| Rôle | Email | Mot de passe |
|------|-------|--------------|
| Super Admin | `superadmin@kaeged.local` | `superadmin123` |
| Admin tenant | `admin@demo.local` | `password123` |
| Utilisatrice | `user@demo.local` | `password123` |
| Validateur | `validator@demo.local` | `password123` |

## 2. Déploiement cPanel (CDG §39)

Prérequis hébergeur : **PHP 8.4+** (selecteur PHP du cPanel), MySQL, Composer/SSH, cron.

1. Pousser le code dans `~/kaeged` (hors `public_html`) ; copier le contenu de
   `public/` dans `public_html` (ou sous-dossier).
2. `composer install --no-dev --optimize-autoloader`
3. Créer la base + utilisateur MySQL ; `.env` production :

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://votre-domaine
DB_*                     # base cPanel
QUEUE_CONNECTION=database
GED_STORAGE_DISK=local   # ou s3
```

4. `php artisan key:generate`
5. `php artisan migrate --force`
6. `php artisan config:cache route:cache view:cache`
7. Cron : `* * * * * /usr/local/bin/php ~/kaeged/artisan schedule:run >> /dev/null 2>&1`
   (lance sauvegarde 02:00 + rétention 03:00).
8. Files : `storage/app/private` reste hors webroot (RM-012) ; sauvegardes à déporter
   hors disque mutualisé (FTP/objet).

### Contraintes cPanel (résumé)

| Fonction | Compatible | Solution |
|----------|-----------|----------|
| Laravel/PHP 8.4 | Oui (sélecteur) | vérifier version PHP + extensions |
| MySQL | Oui | indexation stricte |
| Cron | Oui | `schedule:run` chaque minute |
| Queue persistante | Partiel | database queue + cron ; Redis/worker si besoin |
| Stockage massif | Limité | S3 (`GED_STORAGE_DISK=s3`) |
| OCR/IA lourds | Non | API externes (adaptateurs) |
| Édition Office | Non | service externe / fallback |

### Seuil d'escalade (CDG §39)

Si jobs asynchrones fréquents, stockage local > limites, recherche plein texte/vectorielle
centrale, ou CPU/mémoire limités → migrer le cœur vers un **VPS/service managé**
en conservant l'architecture (queue Redis/worker, S3, moteur de recherche externe).

## 3. Configuration (config/ged.php)

| Variable | Défaut | Rôle |
|----------|--------|------|
| `GED_STORAGE_DISK` | `local` | Disque de stockage documents (local/s3) |
| `GED_AI_PROVIDER` | `mock` | Fournisseur IA par défaut |
| `GED_OCR_PROVIDER` | `mock` | Fournisseur OCR par défaut |
| `GED_OFFICE_PROVIDER` | `fallback` | Mode édition Office |
| `GED_RAG_ENABLED` | `true` | Active la recherche sémantique |
| `GED_SSO_ENABLED` | `false` | Active le flux SSO (V2) |
| `GED_MYSQLDUMP_PATH` | `mysqldump` | Binaire mysqldump (sauvegardes) |
| `GED_MYSQL_PATH` | `mysql` | Binaire mysql (restauration) |
