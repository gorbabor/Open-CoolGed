# spec/ — Documentation du projet Open-CoolGed (ex-Kaeged GED)

## Cartographie des documents

### Fichiers de `spec/` (ce dossier)

| Fichier | Type | Référence |
|---------|------|-----------|
| `cahier-des-charges-ged-saas-v1.0.docx` | Cahier des charges SaaS (source client, Word) | CDG (références `CDG §xx` dans la doc) |
| `spec-v02.md` | Spécification fonctionnelle & technique **source** (périmètre V02) | Transcription brute (tableaux aplatis, mots fusionnés) ; l'original `.eml` n'est pas dans le dépôt |
| `documentation-administrateur.html` | Guide administrateur (utilisateurs, rôles, référentiels, workflows, audit, super admin) | — |
| `procedure-installation-cpanel.html` | Procédure d'installation cPanel | CDG §39 « Contraintes cPanel » |
| `procedure-parametrage-v02.html` | Procédure de paramétrage du périmètre V02 | Dérivé de `spec-v02.md` |
| `processus-ajout-documents.html` | Processus d'ajout de documents (Section → Lot → Document + qualification transversale) | Dérivé de `spec-v02.md` |
| `restreindre-acces-espaces.html` | Guide : restreindre la visibilité d'un espace partagé à une partie des utilisateurs (refus explicites scopés par espace) | RBAC scopé (écart n°35) |

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
- Les évolutions hors cahier des charges sont tracées dans
  `docs/evolutions-cahier-des-charges.md` (numérotation continue des écarts).
