# Workflows & règles métier

## Moteur de workflow

Implémentation : `app/Services/WorkflowService.php`.

### Modèle

- **Workflow** : définition nommée, optionnellement liée à un type documentaire, active/inactive.
- **Étapes** (`workflow_steps`) : ordonnées par `position` ; chaque étape définit un
  `assignee_type` : `user`, `group`, `role`, `creator` (le créateur du document).
- **Instance** : exécution sur un document ; statuts `active | completed | rejected | cancelled`.
- **Tâches** : une par assigné résolu ; statuts `pending | completed | rejected | delegated | expired`.

### Transitions

Seules deux décisions sont autorisées (CA-006) :
- `approve` → la tâche passe `completed` ; quand toutes les tâches de l'étape sont terminées,
  on passe à l'étape suivante (`position` supérieure) ; si l'étape est la dernière,
  l'instance passe `completed` et le document `approved`.
- `reject` → motif **obligatoire** ; l'instance passe `rejected`, le document revient `draft`,
  le créateur est notifié.

### Contrôles (CA-007, RM-014/015)

- Une tâche ne peut être traitée que par son assigné effectif (ou délégataire) — sinon `403`.
- Chaque décision est datée et attribuée (`acted_by`, `acted_at`) et journalisée dans l'audit.
- Délégation : `delegate()` change `delegated_to_id` (auditée).
- Rappels : `due_at` positionné à J+7 à la création ; notifications envoyées aux assignés.

### Création (UI)

`Workflows → Nouveau` : nom, type documentaire optionnel, étapes avec type d'assignation
et identifiant (ID utilisateur/groupe/rôle). Le premier workflow actif peut être démarré
depuis la fiche document (onglet Workflow) ; un seul workflow actif par document.

### Exemples du CDG

- Contrat : Brouillon → Révision juridique → Validation → Signé → Actif → Échéance → Archivé.
- Facture : Import → Contrôle → Validation service → Validation finance → Paiement → Archivé.

## Rétention & cycle de vie

Commande `ged:retention` (scheduler 03:00) :
- Documents dont le type a une `retention_days` dépassée (depuis `created_at`)
  ou dont `expiration_at` est passée → statut `expired` + `archived_at`.
- La **suppression définitive** reste une décision humaine (RM-030), auditée
  (`documents.delete-permanent` après passage en corbeille).

Cycle de vie complet : `draft` → `in_review` (workflow) → `approved` →
`expired`/`archived` ; corbeille (soft delete) → restauration → suppression définitive (réservée).

## Règles métier (RM) — implémentation

| Règle | Où |
|-------|----|
| RM-001/002 — contexte tenant explicite | `TenantScope` + `PermissionService::can()` |
| RM-003 — super admin sans lecture par défaut | scope tenant + permissions |
| RM-004 — élévation support justifiée/auditée | (à activer avec un outil support — super admin audité) |
| RM-005 — jamais d'écrasement silencieux | `DocumentService::addVersion()` crée toujours une version |
| RM-006 — restauration = nouvelle version | `restoreVersion()` copie le fichier, conserve l'historique |
| RM-007 — héritage des permissions | chaîne document → dossier → espace → tenant |
| RM-008 — refus explicite prioritaire | `PermissionService` (denied avant allow) |
| RM-009 — partage limité aux droits de l'émetteur | vérification `documents.share` + partage interne uniquement |
| RM-010 — suppression utilisateur logique | `SoftDeletes` sur `users` |
| RM-011 — suppression définitive réservée/auditée | permission `documents.delete` + log |
| RM-012 — jamais d'URL publique permanente | stockage hors webroot + téléchargements contrôlés |
| RM-013 — validation upload (extension/MIME/taille/quota) | `DocumentService::validateUpload()` |
| RM-014/015 — tâches par acteur, transitions datées | `WorkflowService::decide()` |
| RM-016/017 — IA gouvernée, jamais d'écriture directe | `AiService::assertAllowed()` + `applyResult()` |
| RM-018 — clés API jamais au navigateur | config `.env`, adaptateurs serveur |
| RM-019 — RAG limité aux segments accessibles | `RagService::search()` filtre avant classement |
| RM-020 — quotas vérifiés avant création | `QuotaService` |
| RM-021 — rupture d'héritage auditée | champ `inherit_permissions` + journal (renommage/suppression audités) |
| RM-022 — métadonnées obligatoires | schéma de type + validation formulaire |
| RM-023 — changement de type → revalidation | champ `document_type_id` modifiable + workflow lié au type |
| RM-024 — checksum conservé | `sha256` par version |
| RM-025 — journalisation des opérations sensibles | `AuditService` |
| RM-026 — tenant suspendu conservé mais bloqué | `EnsureTenantActive` + login refusé |
| RM-027 — jobs idempotents | jobs IA en `sync` (dev) / état de job persistant |
| RM-028 — erreur externe ne perd jamais le document | catch + statut `failed`, source intacte (testé) |
| RM-029 — admin tenant vs admin plateforme séparés | `SystemRoleService`, middleware `superadmin` |
| RM-030 — rétention configurable et validée | `retention_days` + commande `ged:retention` |
