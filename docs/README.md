# Documentation technique — Kaeged GED

Plateforme Web GED SaaS Multi-Tenant — Laravel 13 / PHP 8.4 / MySQL.

## Sommaire

| Document | Contenu |
|----------|---------|
| [Architecture technique](architecture.md) | Vue d'ensemble, couches, multi-tenant, files, stockage, IA, traçabilité |
| [Modèle de données](data-model.md) | Entités, relations, index, conventions |
| [API REST](api.md) | Authentification par jeton, endpoints, exemples |
| [Sécurité](security.md) | Isolation tenant, RBAC, uploads, secrets, IA, correspondance CA/RM |
| [Workflows & règles métier](workflows-regles-metier.md) | Moteur de workflow, transitions, rétention, règles RM |
| [Sauvegardes & restauration](sauvegardes-restauration.md) | Commandes, scheduler, procédure de reprise |
| [Déploiement](deploiement.md) | Installation (développement), déploiement cPanel, escalade VPS |
| [Tests](tests.md) | Suite de tests, couverture des critères d'acceptation |
| [Manuel utilisateur](manuel-utilisateur.md) | Guide d'utilisation quotidienne (connexion, documents, workflows, IA, administration) |
| [Viewers & éditeurs en ligne](office-viewers-edit.md) | Aperçu/édition des formats (texte, markdown, PDF, Office) et sécurité associée |
| [Administration paramétrable](administration-parametrable.md) | Options par tenant, CRUD complet, partage externe, paramètres plateforme |
| [Suivi des écarts du cahier des charges](evolutions-cahier-des-charges.md) | Évolutions non prévues implémentées (à mettre à jour à chaque itération) |
| [Périmètre V02](perimetre-v02.md) | Adaptation GED opérationnelle V02 : référentiels, workflow, mes documents, accusés, import |

## Références

- Cahier des charges : document client — non hébergé dans le dépôt (conservé hors dépôt)
- Code source : dépôt [`gorbabor/Open-CoolGed`](https://github.com/gorbabor/Open-CoolGed)
- Base de données : MySQL / MariaDB — bloc `DB_*` du fichier `.env`
  (compte dédié recommandé, base en utf8mb4)
