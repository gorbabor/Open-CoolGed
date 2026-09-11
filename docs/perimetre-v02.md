# Périmètre V02 — GED documentaire opérationnelle

> Documentation de référence du projet Open-CoolGed — source de vérité de la documentation applicative.
> Dernière synchro : 2026-09-11.

Adaptation de Kaeged au document « Projet Odoo — GED documentaire opérationnelle V02 »
(GED documentaire opérationnelle V02.eml), sur la base du mapping conceptuel suivant.

## Mapping V02 ↔ Kaeged

| V02 | Kaeged |
|-----|--------|
| Section (00-04) | Espace |
| Lot | Dossier |
| Famille documentaire (POL, MAN, PRC…) | Type documentaire (15 familles seedées) |
| ID / code document | `reference` (ID) + `document_code` (code officiel unique) |
| Domaine / processus | Référentiels `domain` / `process` |
| Propriétaire / vérificateur / approbateur | `owner_id` / `reviewer_id` / `approver_id` |
| Poste, département, direction, site, entité, pays | Référentiels + champs utilisateur + multi-valeurs document |
| Statuts (7) | `a_creer, brouillon, en_verification, en_approbation, approuve_applicable, en_revision, obsolete_archive` |
| Documents applicables à mon poste | Menu « Mes documents » |
| Accusés de lecture | Table `read_acknowledgements` |

## Lot A — Référentiels & dimensions

- Table `referentials` (tenant, type : domain/process/job/department/direction/site/entity/country, name, code)
  + `document_referential` (multi-valeurs par document).
- Champs `users` : job_id, department_id, direction_id, site_id, entity_id, country_id.
- Champs `documents` : document_code, domain_id, process_id, owner_id, reviewer_id, approver_id,
  criticality, review_frequency, next_review_date, effective_date, replaces/replaced_by, is_active_version.
- UI : Administration → Référentiels (CRUD avec dédoublonnage orthographique).

## Lot B — Workflow V02 & règles (§9.3)

`V02DocumentService` :
- `approvalBlockers()` : règles 1-5 (fichier, version, propriétaire, date d'application, prochaine revue).
- `approve()` : passage à `approuve_applicable` + `is_active_version`, **obsolescence automatique**
  de l'ancienne version applicable (lien remplace/remplacé par) — règle 9.
- `isVisibleToStandardUser()` : les obsolètes/archivés sont masqués aux standards — règle 8.
- Verrouillage : les approuvés sont en lecture seule (modification via « en révision ») — règles 6-7.

## Lot C — Menu « Mes documents »

`/mes-documents` : documents `approuve_applicable` + version active, filtrés par les dimensions
de l'utilisateur (poste, département, direction, site, entité, pays) OU rôles de propriétaire/
vérificateur/approbateur. Filtres : section, famille, domaine, criticité, texte. Colonnes V02 §13.4.
Badges « proche » (≤30 j) / « en retard » de revue.

## Lot D — Accusés de lecture

- Option `read_ack_required` par document ; bouton « Accuser lecture » sur les documents applicables.
- Table `read_acknowledgements` (unique version+utilisateur), audit `v02.read_acknowledged`.

## Lot E — Import du registre (CSV)

`php artisan v02:import <fichier.csv> [--tenant=1] [--dry-run]`
Colonnes : id;code;titre;section;lot;famille;version;statut;proprietaire;date_application;prochaine_revue;domaine;criticite
- Contrôles : id/code obligatoires et uniques, titre requis, statut valide (7 valeurs).
- Création auto des espaces (sections), dossiers (lots), types (familles), référentiels (domaines).
- Rapport : ok / erreurs (ligne par ligne).

## Lot F — Alertes de revue

`php artisan v02:review-alerts` (planifié 06:00) : notifications `review.upcoming` (J-30)
et `review.late` (retard) aux propriétaires. Badges dans « Mes documents ».

## Unification des termes V02 ↔ Kaeged (UI)

| V02 | Kaeged (structure) | Libellé UI retenu | Où gérer |
|-----|--------------------|-------------------|----------|
| Section (00-04) | `spaces` | Section (espace) | Espaces (`/spaces`) — création, **renommage**, suppression |
| Lot | `folders` | Dossier | Dans une section (espace) |
| Famille documentaire (POL, MAN, PRC…) | `document_types` | Famille (type documentaire) | Administration → Types & métadonnées |
| Domaine / processus | `referentials` (type domain/process) | Domaine / Processus | Administration → Référentiels |
| Poste, département, direction, site, entité, pays | `referentials` (job/department/…) | Idem | Administration → Référentiels |
| Confidentialité | `documents.confidentiality` | Confidentialité (public/interne/confidentiel/secret) | Fiche document (création + métadonnées) |
| Statuts (7) | `documents.status` | Voir tableau ci-dessous | Fiche document |

### Statuts harmonisés (libellés UI)

| Valeur stockée | Libellé affiché | Origine |
|----------------|-----------------|---------|
| `a_creer` | À créer | V02 |
| `brouillon` | Brouillon | V02 |
| `draft` | Brouillon | Legacy (cycle Kaeged) |
| `en_verification` | En vérification | V02 |
| `en_approbation` | En approbation | V02 |
| `in_review` | En revue | Legacy (cycle Kaeged) |
| `approved` | Approuvé | Legacy (cycle Kaeged) |
| `approuve_applicable` | Approuvé — applicable | V02 |
| `en_revision` | En révision | V02 |
| `archived` | Archivé | Legacy (cycle Kaeged) |
| `obsolete_archive` | Obsolète / archivé | V02 |
| `expired` | Expiré | Legacy (cycle Kaeged) |

> Les statuts **V02** (7 valeurs : `a_creer`, `brouillon`, `en_verification`,
> `en_approbation`, `approuve_applicable`, `en_revision`, `obsolete_archive`) sont ceux
> utilisés par le périmètre V02 (import CSV, « Mes documents »). Les statuts **legacy**
> (`draft`, `in_review`, `approved`, `archived`, `expired`) restent gérés par le cycle de
> vie Kaeged (workflow, rétention) ; les deux coexistants sont affichés avec le même libellé
> (« Brouillon », « Approuvé — applicable », « Obsolète / archivé »).

Implémentation : `App\Models\Document::STATUS_LABELS` + `statusLabel()`, utilisées dans
les vues (tableau de bord, liste des documents, fiche document).

## Tests

`tests/Feature/V02Test.php` (8 tests) : règles d'approbation, obsolescence auto, masquage,
accusé (idempotent + audit), filtrage par dimensions, CRUD référentiels dédoublonné,
alertes, import CSV (création + rejet ligne invalide).
