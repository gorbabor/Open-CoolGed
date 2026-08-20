# Administration paramétrable

Ce document décrit les options configurables du menu **Administration** (par tenant)
et de l'écran **Super Admin → Paramètres plateforme**.

## RBAC enrichi — rôles de groupe

Modèle (CDG §12-13) :

```
Utilisateur ──┬── Rôles directs (user_role)        ──┐
              │                                       ├── Permissions (role_permission,
              └── Groupes (group_user)                │    scope tenant/space/folder/document,
                      └── Rôles de groupe (group_role)─┘    + denied prioritaire RM-008)
```

- Un **groupe** peut porter **plusieurs rôles** (table `group_role`) ; un rôle peut
  être affecté à plusieurs groupes.
- Les **membres héritent** des rôles de leurs groupes : rôles effectifs =
  union(rôles directs + rôles des groupes) — `PermissionService::effectiveRoleIds()`.
- Le **refus explicite** (denied) d'un rôle de groupe prévaut sur toute autorisation
  (RM-008 étendu).
- Les **portées** (tenant/space/folder/document) s'appliquent identiquement aux rôles
  de groupe.
- Groupes **plats** (pas de hiérarchie) — pas de récursion.

UI :
- **Groupes** → section « Rôles du groupe » (cases à cocher) → « Enregistrer les rôles »
  (audité `admin.group.roles.updated`).
- **Utilisateurs** → colonne « Permissions effectives » (rôles directs + hérités,
  résolus en slugs de permissions, badges).

## Architecture

- `app/Services/TenantSettings.php` : lecture/écriture des options par tenant
  (JSON `tenants.settings`) avec valeurs par défaut centralisées (`DEFAULTS`).
  Le reste de l'application lit via ce service.
- `app/Models/PlatformSettings.php` : ligne unique (id=1) portant les défauts
  plateforme ; `applyToNewTenant()` fusionne ces défauts dans les réglages des
  nouveaux tenants à leur création (D-ADM4).
- `tenants.branding` (JSON) : couleur principale + logo, appliqués via variables
  CSS `--brand` injectées dans le layout (sidebar, boutons, progress bars).

## Onglets Paramètres tenant (Administration → Paramètres)

| Onglet | Options | Application |
|--------|---------|-------------|
| Général | Quotas (stockage Mo, utilisateurs, taille max fichier), langue, fuseau horaire | Quotas et taille max appliqués |
| Sécurité | MFA requis admin/validateur, expiration session, longueur min. mot de passe | Longueur min. **appliquée** ; MFA/session **configurables seulement** (V2) |
| Documents | Formats MIME autorisés, verrouillage auto à l'édition, commentaire de version obligatoire | **Appliqués immédiatement** |
| Rétention | Durée par défaut (jours), purge corbeille, alerte avant suppression | Branchés sur les commandes de rétention |
| Notifications | Tâches, partages, échéances, email | Défauts de notification |
| Partage | Liens externes autorisés, durée max, mot de passe requis | **Appliqué** (flux partage externe) |
| IA | Activée, fournisseurs | Appliqué (AiService) |
| Workflows | Workflow par défaut | Référence pour démarrage |
| Branding | Couleur principale, logo URL | Appliqués (variables CSS) |

## CRUD complet (Lot B)

- **Utilisateurs** : édition (nom/email/rôle/groupes), réinitialisation de mot de passe,
  suspension, suppression **logique** (RM-010). Auto-suppression interdite.
- **Groupes** : renommage, membres, suppression (les utilisateurs ne sont jamais supprimés).
- **Rôles** : renommage, duplication (permissions copiées), suppression **bloquée**
  pour les rôles système ou affectés à des utilisateurs.
- **Types documentaires** : édition (nom, rétention), suppression **bloquée** si des
  documents utilisent le type.
- **Métadonnées** : édition (nom, type, options, obligatoire), suppression **bloquée**
  si des valeurs existent.

Chaque action admin est **auditée** (`admin.*.created/updated/deleted`, acteur + horodatage).

## Partage externe (D-ADM3)

- Désactivé par défaut ; activable dans Paramètres → Partage (ou défaut plateforme).
- Création depuis la fiche document (onglet Partage) : **expiration obligatoire**
  (max configurable), **mot de passe optionnel** (ou requis selon politique),
  droit consultation ou téléchargement.
- Accès : `GET /share/{token}` — jeton aléatoire 256 bits stocké **haché**
  (jamais d'URL publique — RM-012), révocable, chaque consultation/téléchargement audité.
- Révocation : bouton Révoquer sur le partage.

## Paramètres plateforme (super admin)

`Super Admin → Paramètres plateforme` : mêmes familles d'options (sécurité, documents,
partage, IA, MIME) — appliquées en **défauts** à chaque nouveau tenant.
Les tenants existants ne sont pas modifiés.

## Tests

`tests/Unit/TenantSettingsTest.php` (défauts, persistance, override MIME) et
`tests/Feature/AdminSettingsTest.php` :
- MIME restreint → import TXT refusé immédiatement
- verrouillage auto désactivé → pas de lock à l'édition
- commentaire obligatoire → version sans commentaire refusée
- partage externe : expiration requise, mot de passe vérifié, révocation, accès public par jeton
- rôle système / type avec documents non supprimables
- platform settings fusionnés dans les nouveaux tenants
- export CSV de l'audit
- actions admin et branding audités
- branding appliqué dans le layout (variables CSS)
