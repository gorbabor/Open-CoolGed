# Tests

Suite : PHPUnit via Laravel (`php artisan test`) — 45 tests / 140 assertions, tout vert.
Base de test : SQLite en mémoire (phpunit.xml), migrations + seed des rôles à chaque test.

## Exécution

```bash
php artisan test                    # toute la suite
php artisan test --filter=TenantIsolation
php artisan test --filter=BackupRestore
```

## Fichiers de test

| Fichier | Couverture |
|---------|-----------|
| `tests/TestCase.php` | Helpers : tenant, user (par rôle), space, folder, document ; gestion du `TenantContext` |
| `TenantIsolationTest` | CA-001 (accès inter-tenant refusé), jeton API bloqué, super admin sans lecture par défaut (RM-003), recherche filtrée, IDOR rejeté |
| `RbacTest` | CA-003 (téléchargement refusé sans permission), RM-008 (refus explicite prioritaire), portée espace, utilisateur suspendu |
| `VersioningTest` | CA-004 (3 versions + checksums), CA-005/RM-006 (restauration sans perte), jamais d'écrasement |
| `WorkflowTest` | CA-006 (transition non définie refusée), CA-007 (tâche non affectée refusée), rejet motivé, chemin complet d'approbation, CA-014 (audit avec acteur) |
| `UploadAndSecurityTest` | CA-012 (MIME/taille/quota), CA-013 (hors webroot), CA-011 (secrets absents du HTML), CA-015 (pagination), CA-018 (quota utilisateurs) |
| `AiAndOcrTest` | CA-009 (aucun appel sans permission), CA-010 (source intacte), CA-016 (fournisseur en panne → doc intact), OCR, validation humaine, audit des permissions |
| `OfficeAndRagTest` | CA-019 (fallback + nouvelle version), retour d'édition + verrou, RM-019 (RAG étanche), CA-017 (tenant suspendu), MFA |
| `SsoTest` | SSO désactivé par défaut, flux OK dans le tenant, refus inter-tenant |
| `BackupRestoreTest` | CA-020 (backup → perte → restore), purge 30 j, rétention documentaire |

## Critères d'acceptation du CDG couverts

| Critère | Test |
|---------|------|
| CA-001/002 isolation | TenantIsolationTest |
| CA-003 téléchargement sans droit | RbacTest |
| CA-004/005 versioning | VersioningTest |
| CA-006/007 workflows | WorkflowTest |
| CA-008 recherche filtrée | TenantIsolationTest / SearchController |
| CA-009/010/011 IA + secrets | AiAndOcrTest, UploadAndSecurityTest |
| CA-012/013 uploads + stockage | UploadAndSecurityTest |
| CA-014 audit | WorkflowTest, AiAndOcrTest |
| CA-015 pagination | UploadAndSecurityTest |
| CA-016 fournisseur indisponible | AiAndOcrTest |
| CA-017 tenant suspendu | OfficeAndRagTest |
| CA-018 quotas | UploadAndSecurityTest |
| CA-019 fallback Office | OfficeAndRagTest |
| CA-020 restauration sauvegarde | BackupRestoreTest (+ démonstration MySQL réelle) |

## Qualité

- `php vendor/bin/pint` : style PSR-12/Laravel (96 fichiers, 0 issue).
- Pas de dépendance de test externe (fake files, sqlite mémoire, mock providers).
