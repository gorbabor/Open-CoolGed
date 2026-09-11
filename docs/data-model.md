# Modèle de données

Base MySQL / MariaDB (utf8mb4 / utf8mb4_unicode_ci — nom de base et identifiants
configurés dans le fichier `.env`). Migrations dans `database/migrations/`.
Convention : **toute entité métier porte `tenant_id`** (FK vers `tenants`, cascade delete)
et utilise le trait `BelongsToTenant` (scope global).

## Entités

### Tenants & identité

| Table | Rôle | Champs notables |
|-------|------|-----------------|
| `tenants` | Entreprise cliente (frontière de sécurité) | name, slug (unique), status (active/suspended), plan, storage_quota_mb, user_quota, max_file_size_mb, settings (json), branding (json) |
| `users` | Comptes (soft deletes) | tenant_id (nullable), is_super_admin, status (active/invited/suspended), mfa_enabled, mfa_secret, last_login_at |
| `groups` / `group_user` | Groupes métier par tenant | group_user : tenant_id, group_id, user_id (unique group+user) |
| `roles` | Rôles RBAC (système ou custom) | tenant_id (nullable pour rôles globaux), slug unique par tenant, is_system |
| `permissions` | Catalogue de permissions (global) | slug unique, group |
| `role_permission` | Permissions accordées/refusées par rôle + portée | role_id, permission_id, scope_type (tenant/space/folder/document/global), scope_id, denied |
| `user_role` | Affectation utilisateur → rôle | tenant_id, user_id, role_id |

### Espaces & documents

| Table | Rôle | Champs notables |
|-------|------|-----------------|
| `spaces` | Zone de travail (Direction, RH, Finances…) | name, description, color, soft deletes |
| `folders` | Dossiers/sous-dossiers | space_id, parent_id (nullable), name, inherit_permissions, soft deletes |
| `document_types` | Types documentaires (contrat, facture…) | name, slug, metadata_schema (json), retention_days |
| `documents` | Objet central | space_id, folder_id, document_type_id, title, reference, description, status (draft/in_review/approved/expired/archived), confidentiality (public/internal/confidential/secret), expiration_at, current_version_id, created_by, archived_at, soft deletes |
| `document_versions` | Historique des versions | document_id, version (ex. 1.0), file_path, file_name, mime_type, size, checksum (sha256), lock_token (verrou Office), extracted_text, comment, created_by |
| `tags` / `document_tag` | Tags par tenant | document_tag : tenant_id, document_id, tag_id |
| `metadata_definitions` | Schéma de métadonnées par tenant | name, key, type (text/longtext/number/date/boolean/list/multiselect/user), required, options (json) |
| `metadata_values` | Valeurs par document | document_id, definition_id, value (text) |

### Processus

| Table | Rôle | Champs notables |
|-------|------|-----------------|
| `workflows` | Définition de workflow | name, slug, document_type_id (nullable), is_active |
| `workflow_steps` | Étapes ordonnées | workflow_id, position, name, assignee_type (user/group/role/creator), assignee_id, is_final |
| `workflow_instances` | Exécution sur un document | workflow_id, document_id, status (active/completed/rejected/cancelled), current_step_id, created_by |
| `workflow_tasks` | Tâches d'étape | instance_id, step_id, status (pending/completed/rejected/delegated/expired), assignee_user_id, delegated_to_id, due_at, decision_comment, acted_by, acted_at |

### Exploitation & traçabilité

| Table | Rôle | Champs notables |
|-------|------|-----------------|
| `comments` | Commentaires sur documents | document_id, user_id, body |
| `notifications` | Centre de notifications interne | user_id, type, title, body, link, read_at |
| `audit_logs` | Journal d'audit (immutable, `updated_at` désactivé) | tenant_id, user_id, action, resource_type, resource_id, details (json), ip, user_agent, created_at |
| `shares` | Partages internes | document_id, shared_with_user_id / shared_with_group_id, permission (view/download/edit), expires_at, revoked_at, created_by |
| `office_sessions` | Sessions d'édition en ligne | document_id, version_id, user_id, token (hash sha256), expires_at, returned_at |
| `api_tokens` | Jetons API (hash sha256, **non scopé**) | tenant_id, user_id, name, token_hash, last_used_at, expires_at |
| `ai_jobs` | Jobs IA/OCR | user_id, document_id, version_id, job_type (summary/qa/classification/extraction/correction/comparison/translation/ocr/embed), provider, status (pending/running/succeeded/failed), input_summary, output (json), error |
| `ai_results` | Résultats IA (validation humaine) | job_id, document_id, result_type, content (json), confidence, validated_by, validated_at |
| `rag_chunks` | Segments indexés pour la recherche sémantique | document_id, version_id, chunk_index, content, embedding (json) |

## Relations principales (ERD simplifié)

```
tenants 1──∞ users            tenants 1──∞ spaces 1──∞ folders 1──∞ documents
tenants 1──∞ groups           tenants 1──∞ roles ∞──∞ permissions (via role_permission)
tenants 1──∞ document_types   documents 1──∞ document_versions (current_version_id →)
tenants 1──∞ metadata_definitions ∞──∞ documents (via metadata_values)
documents ∞──∞ tags (via document_tag)
tenants 1──∞ workflows 1──∞ workflow_steps
workflows 1──∞ workflow_instances 1──∞ workflow_tasks
documents 1──∞ comments / shares / ai_jobs / rag_chunks
users 1──∞ audit_logs (nullable) / notifications
```

## Index recommandés

Déjà présents dans les migrations : `tenant_id` (FK indexées), `users.email` (unique),
`tenants.slug` (unique), `documents.status`, `documents.space_id`, `document_versions.document_id`,
`workflow_tasks.status`, `audit_logs.action`, `ai_jobs.status`.

Pour le dimensionnement cible (plusieurs centaines de milliers de documents) :
- `documents` : index composite `(tenant_id, status)`, `(tenant_id, space_id)`, `(tenant_id, updated_at)`
- `document_versions` : `(tenant_id, document_id, id)`
- `audit_logs` : `(tenant_id, created_at)`, `(tenant_id, action)`
