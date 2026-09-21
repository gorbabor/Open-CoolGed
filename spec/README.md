# spec/ — Documentation du projet Open-CoolGed (ex-Kaeged GED)

## Cartographie des documents

### Fichiers de `spec/` (ce dossier)

| Fichier | Type | Référence |
|---------|------|-----------|
| `documentation-administrateur.html` | Guide administrateur (utilisateurs, rôles, référentiels, workflows, audit, super admin) | — |
| `procedure-installation-cpanel.html` | Procédure d'installation cPanel | CDG §39 « Contraintes cPanel » |
| `procedure-parametrage-v02.html` | Procédure de paramétrage du périmètre V02 | Dérivé de la spécification V02 client (non hébergée dans le dépôt) |
| `processus-ajout-documents.html` | Processus d'ajout de documents (Section → Lot → Document + qualification transversale) | Dérivé de la spécification V02 client (non hébergée dans le dépôt) |
| `restreindre-acces-espaces.html` | Guide : restreindre la visibilité d'un espace partagé à une partie des utilisateurs (refus explicites scopés par espace) | RBAC scopé (écart n°35) |

> **Documents client** : le cahier des charges (Word) et la spécification V02 transmise
> par le client **ne sont pas hébergés dans ce dépôt** — ils sont conservés hors dépôt
> (côté client/projet). Les documents de ce dossier qui en dérivent y font référence
> sous « CDG §xx ».

### Documentation applicative (`docs/`)

| Fichier | Contenu |
|---------|---------|
| `docs/README.md` | Sommaire de la documentation technique (architecture, modèle de données, API, sécurité, déploiement, tests, manuel utilisateur…) |
| `docs/evolutions-cahier-des-charges.md` | Suivi des écarts au cahier des charges — mis à jour à chaque itération |
| `docs/perimetre-v02.md` | Périmètre V02 implémenté (mapping V02 ↔ plateforme, lots A–F, unification des termes) |

## Convention de nommage

- `-v02` suffixe = document relatif au **périmètre V02** (même sans suffixe, les autres
  documents dérivent de la même spécification).
- Les guides HTML sont **autonomes** (ouvrables directement dans un navigateur, sans serveur).
- **Guides HTML — copies de publication** : les 5 guides de `spec/` sont des copies
  **byte-identiques** des guides du dépôt de documentation (référence) — toute évolution doit
  être reportée des deux côtés. **Documents client** (cahier des charges, spécification V02) :
  non hébergés ici — conservés hors du dépôt applicatif.
- Les évolutions hors cahier des charges sont tracées dans
  `docs/evolutions-cahier-des-charges.md` (numérotation continue des écarts).
