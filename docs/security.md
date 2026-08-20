# Sécurité

Correspondance entre exigences du cahier des charges (SEC, RM, CA) et l'implémentation.

## Isolation multi-tenant (critique)

| Mécanisme | Implémentation |
|-----------|----------------|
| Contexte tenant obligatoire | `TenantContext` fixé par le middleware `tenant.context` (après `auth`) |
| Scope global | `TenantScope` via trait `BelongsToTenant` — `tenant_id` sur chaque requête (RM-002) |
| Double garde | `PermissionService::can()` refuse toute ressource d'un autre tenant (RM-001) |
| Pas de binding de route | Résolution manuelle des modèles dans les contrôleurs (le binding s'exécuterait hors contexte) |
| API | Jeton lié à un tenant, `ApiAuth` vérifie compte + tenant actifs |
| Super Admin | `is_super_admin` → pas de lecture par défaut du contenu tenant (RM-003) ; accès superadmin isolé par middleware `superadmin` |
| Tenant suspendu | `EnsureTenantActive` déconnecte et bloque (CA-017) ; login refusé (RM-026) |

## RBAC & permissions

- Catalogue : 24 permissions (`Permission::allSlugs()`) réparties en groupes (documents, workflow, ai, admin).
- Portées : `tenant` (défaut), `space`, `folder`, `document`, `global`.
- Héritage : la chaîne document → dossier(s) → espace → tenant est parcourue du plus précis au plus global.
- **Refus explicite prioritaire** (RM-008) : un `denied=true` sur un scope quelconque de la chaîne bloque,
  même si une autorisation existe ailleurs.
- Rôles système par tenant (`SystemRoleService`) : `tenant_admin`, `manager`, `user`, `auditor`, `validator`.
  Leur création à la création du tenant sépare administration tenant et plateforme (RM-029).

## Sessions & authentification

- Hachage moderne des mots de passe (bcrypt, BCRYPT_ROUNDS=12) (SEC-003).
- Cookies Secure/HttpOnly/SameSite via config Laravel, CSRF natif (SEC-004/005).
- Verrouillage : statut `suspended` utilisateur / tenant contrôlé à chaque requête.
- MFA TOTP (RFC 6238) implémenté sans dépendance (`MfaService`) : secret base32, 6 chiffres,
  fenêtre ±1 ; activable par utilisateur depuis le profil ; vérifié au login (F-025).
- SSO/OIDC par tenant (V2, flux mock) : routes `sso.start`/`sso.callback`,
  activé par `ged.sso_enabled` + `settings['sso_enabled']` du tenant ; l'utilisateur
  doit exister et être actif **dans le tenant ciblé**.

## Uploads & stockage (SEC-009, RM-012/013)

- Liste blanche MIME : PDF, Office (doc/xls/ppt + OOXML), TXT/CSV/JSON/HTML, JPEG/PNG/TIFF.
- Taille max par tenant (`max_file_size_mb`), quota global (`storage_quota_mb`) vérifié avant écriture (RM-020).
- Fichiers stockés hors webroot (`storage/app/private`), jamais servis par URL publique permanente (RM-012).
- Téléchargements contrôlés par permission (`documents.download`) ; sessions Office par jeton
  temporaire unique (hash sha256, expiration 60 min).

## IA (SEC-014/015/016, RM-016/017/019)

- **Jamais d'appel fournisseur sans permission** : `AiService::assertAllowed()`
  vérifie `ai.use` sur le document + activation IA du tenant (CA-009).
- Minimisation : seul `input_summary` (200 premiers caractères) + le texte nécessaire sont transmis.
- Fournisseurs derrière `AiProvider` (adaptateurs), secrets jamais exposés au navigateur (SEC-011, CA-011).
- L'IA **ne modifie jamais** le document source : elle produit des propositions
  (`ai_results`), toute application passe par validation humaine et crée une nouvelle version (CA-010, RM-017).
- Prompt injection : le contenu documentaire est traité comme donnée non fiable (SEC-015) —
  l'architecture adaptateur prévoit la séparation instructions système/contenu.
- RAG : le filtrage des droits intervient **avant** la sélection des segments (RM-019, SEC-016) ;
  l'index (`rag_chunks`) est partitionné par `tenant_id`.

## Audit & journaux (SEC-012/018, RM-025)

- `AuditService::log()` enregistre : tenant, utilisateur (ou acteur explicite), action,
  ressource, IP, user-agent, détails JSON.
- Actions tracées : connexions (succès/échec/MFA), CRUD documents, versions,
  partages, changements de permissions, transitions de workflow, jobs IA, actions superadmin.
- La table `audit_logs` n'a pas d'`updated_at` et n'est pas modifiable via l'interface (CA-014).

## Sauvegardes (SEC-013, CA-020)

- `ged:backup` : dump SQL (mysqldump) + export JSON portable + copie des fichiers + rétention 30 j.
- `ged:restore` : réimport SQL ou JSON + restauration des fichiers.
- Scheduler : sauvegarde quotidienne 02:00. Restauration démontrée sur MySQL réel (voir `sauvegardes-restauration.md`).

## Tests de sécurité automatisés

Voir `tests.md`. Points clés : 0 fuite inter-tenant (CA-001), refus explicite,
secret absent du HTML, fichiers hors webroot, IDOR refusé, RAG étanche.
