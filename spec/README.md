# spec/ — Documentation du projet GED (Kaeged)

## Cartographie des documents

| Fichier | Type | Version | Référence |
|---------|------|---------|-----------|
| `cahier-des-charges-ged-saas-v1.0.docx` | Cahier des charges SaaS (source client, Word) | v1.0 | CDG (références `CDG §xx` dans la doc) |
| `spec-v02.md` | Spécification fonctionnelle & technique **source** (transmise par email) | V02 | Fichier source du périmètre V02 — **transcription brute** (tableaux aplatis, mots fusionnés) ; l'original `.eml` n'est pas dans le dépôt, le déposer dans `spec/` s'il devient disponible |
| `documentation-administrateur.html` | Guide administrateur (utilisateurs, rôles, référentiels, workflows, audit, super admin) | v1.0 | — |
| `procedure-parametrage-v02.html` | Procédure de paramétrage (déploiement du périmètre V02) | V02 | Dérivé de spec-v02.md |
| `procedure-installation-cpanel.html` | Procédure d'installation cPanel | v1.0 | CDG §39 « Contraintes cPanel » |
| `processus-ajout-documents.html` | Processus d'ajout de documents (Section → Lot → Document + qualification transversale) | V02 | Dérivé de spec-v02.md (état implémenté Kaeged) |
| `docs/perimetre-v02.md` | Périmètre V02 implémenté (mapping V02 ↔ Kaeged, lots A–F, unification des termes) | V02 | Écart n°9 (spec-v02.md) |
| `docs/evolutions-cahier-des-charges.md` | Évolutions du cahier des charges | — | Écart n°9 (spec-v02.md) |

## Convention de nommage

- `-v02` suffixe = document relatif au **périmètre V02** (même sans suffixe, les autres
  documents dérivent de la même spécification).
- Les fichiers `docs/` sont des copies de référence de la documentation applicative
  (`E:\xampp\htdocs\kaeged\docs\` — source de vérité de l'implémentation). Chaque copie
  porte un en-tête « Dernière synchro : <date> » à mettre à jour à chaque copie.
