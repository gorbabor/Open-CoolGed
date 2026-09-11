# Sauvegardes & restauration

Référence CDG §41 (RPO 24 h / RTO 8 h initiaux, à valider) et critère d'acceptation CA-020.

## Commandes

| Commande | Rôle |
|----------|------|
| `php artisan ged:backup` | Crée une sauvegarde : dump SQL (si MySQL + mysqldump), export JSON portable, copie des fichiers documents, manifeste ; purge les sauvegardes > 30 jours |
| `php artisan ged:backup --prune-only` | Purge uniquement les sauvegardes périmées |
| `php artisan ged:restore` | Restaure la sauvegarde la plus récente |
| `php artisan ged:restore --dir=<chemin>` | Restaure une sauvegarde précise |
| `php artisan ged:retention [--dry-run]` | Archive les documents arrivés à échéance (rétention) |
| `php artisan schedule:list` | Affiche les tâches planifiées |

## Contenu d'une sauvegarde

Répertoire : `storage/backups/<YYYYMMDD_HHMMSS>/`

```
database.sql      # dump mysqldump (--single-transaction --routines) si MySQL
database.json     # export JSON portable (30 tables, ordre de dépendance)
files/            # copie de storage/app/private (documents)
manifest.json     # horodatage, app, driver db, nb de fichiers
```

- Si `mysqldump` est indisponible, le dump SQL est omis et **l'export JSON suffit** (restauration possible).
- Chemins des binaires : par défaut résolus depuis le `PATH` ; définir les chemins
  absolus dans `.env` → `GED_MYSQLDUMP_PATH`, `GED_MYSQL_PATH` si l'hébergeur
  ne les expose pas.

## Planification (scheduler)

`routes/console.php` :

| Heure | Commande |
|-------|----------|
| 02:00 | `ged:backup` (sauvegarde quotidienne + purge 30 j) |
| 03:00 | `ged:retention` (échéances documentaires) |

En production : cron `* * * * * php /chemin/artisan schedule:run >> /dev/null 2>&1`.

## Procédure de reprise (testée sur MySQL réel)

1. `php artisan ged:backup` → vérifier `storage/backups/.../database.sql` créé.
2. Simuler une perte : suppression de documents/versions en base.
3. `php artisan ged:restore` (ou `--dir=...` pour une sauvegarde précise).
4. Vérifier : `SELECT COUNT(*) FROM documents;` → données recréées ; fichiers restaurés.

L'exercice de restauration est documenté (CDG §41 : test trimestriel recommandé) et
couvert par le test automatisé `BackupRestoreTest::test_restore_recreates_data_after_loss`.

## Rétention des sauvegardes

- Durée : **30 jours** (`BackupService::RETENTION_DAYS`).
- Purge automatique à chaque sauvegarde ; `ged:backup --prune-only` pour purger seul.

## Limites & recommandations

- En mutualisé cPanel, le stockage local des sauvegardes ne protège pas contre la perte
  du disque de l'hébergeur : déporter les sauvegardes (FTP/objet) en production.
- RPO/RTO cibles (24 h / 8 h) à confirmer ; le chiffrement des sauvegardes (SEC-013)
  est à activer si elles quittent la machine.
