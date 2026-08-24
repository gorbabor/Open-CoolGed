# Suivi des écarts — évolutions non prévues au cahier des charges

> Copie de référence de `E:\xampp\htdocs\kaeged\docs\evolutions-cahier-des-charges.md` (source de vérité).
> Dernière synchro : 2026-08-20.

Ce document trace **toutes les évolutions implémentées qui n'étaient pas prévues**
dans `spec/cahier-des-charges-ged-saas-v1.0.docx` (12/08/2026).
Il doit être **mis à jour à chaque itération** (règle de travail permanente).

## Écarts implémentés

| # | Date | Évolution | Écart vs CDG | Impact |
|---|------|-----------|--------------|--------|
| 1 | 08/2026 | **Viewers/éditeurs en ligne** (CodeMirror, pdf.js+pdf-lib, docx-preview, SheetJS, pptxjs) | CDG §18 prévoyait OnlyOffice/fallback ; pas de viewers embarqués | Aperçu et édition texte/PDF sans serveur externe |
| 2 | 08/2026 | **Éditeur PDF annotable** (texte, surlignage, notes) | Non prévu (CDG §18 = édition Office uniquement) | Annotation PDF en ligne |
| 3 | 08/2026 | **Partage externe par jeton** (RM-012, expiration, mot de passe) | CDG §22 le laissait « si introduit ultérieurement », désactivé par défaut | Liens externes sécurisés implémentés |
| 4 | 08/2026 | **Options paramétrables étendues** (MIME autorisés, verrouillage auto, commentaire obligatoire, branding, langue/fuseau) | CDG §55 listait des décisions mais pas d'écran paramètres riche | Administration paramétrable complète |
| 5 | 08/2026 | **Paramètres plateforme** super admin (défauts nouveaux tenants) | Non prévu | Pré-remplissage des tenants |
| 6 | 08/2026 | **Padding anti-IDM** sur le streaming PDF | Non prévu (contournement technique) | Contre les gestionnaires de téléchargement |
| 7 | 08/2026 | **Rôles de groupe** (`group_role`) — héritage des rôles par appartenance | CDG §12/13 ne prévoyait que des rôles directs | RBAC enrichi |
| 8 | 08/2026 | **Sous-menu Administration** dans la sidebar | Non prévu (UX) | Navigation |
| 9 | 08/2026 | **Périmètre V02** : référentiels (domaines, processus, postes, sites…), champs dimensions, workflow 7 statuts, menu « Mes documents », accusés de lecture, import CSV registre, alertes de revue | Nouveau périmètre client (GED opérationnelle V02) | Adaptation majeure |
| 10 | 08/2026 | **Édition des caractéristiques du document** (Titre, Espace, Dossier, Type documentaire) depuis l'onglet Métadonnées — auparavant fixées à l'import | CDG ne prévoyait que la modification des métadonnées personnalisées et de la confidentialité | Gestion documentaire complète (garde serveur dossier↔espace) |
| 11 | 08/2026 | **Cycle de vie documentaire — gel par statut** : documents approuvés, archivés, expirés ou obsolètes verrouillés (métadonnées + contenu) ; une nouvelle révision est requise ; UI avec bandeaux explicatifs | CDG ne verrouillait pas les documents par statut (règle 6/7 V02 non implémentée) | Conformité GED classique (intégrité des documents validés) |
| 12 | 08/2026 | **Renommage Open-CoolGed + nom personnalisable** : défaut `Open-CoolGed`, surcharge par le superadmin (nom plateforme) et par chaque admin tenant (`branding.brand_name`, vide = héritage) | KAE GED était le nom historique ; le CDG ne prévoyait pas de nom par tenant | Identité par entreprise (login, titres, sidebar, emails) |
| 13 | 08/2026 | **Design Kami** : restyle complet de l'interface (palette #f5f4ed/#efece2/#1b365d, typographie Georgia, angles vifs) basé sur spec/documentation-kami.html | CDG ne spécifiait pas de charte graphique | Identité visuelle cohérente |
| 14 | 08/2026 | **Messagerie SMTP par tenant** : onglet « Messagerie » (host/port/user/password chiffré/from), test d'envoi, notifications envoyées par email si activé | CDG prévoyait les notifications en base uniquement ; l'email dépendait du SMTP serveur | Notifications réellement délivrées par email |
| 15 | 08/2026 | **Thèmes visuels + mode sombre** : 12 thèmes (palettes claire/sombre, polices Google Fonts, radius) ; mode clair/sombre/auto (préférence utilisateur > tenant > plateforme) ; thème personnel par utilisateur (profil, avec aperçu pastilles + police) ; sélecteurs avec aperçu (Branding tenant + plateforme) ; profil enrichi (apparence, changement de mot de passe, MFA, infos) ; toggle sidebar | CDG ne spécifiait pas de charte graphique ni de mode sombre ; pas de changement de mot de passe en self-service | Identité visuelle personnalisable + confort (mode sombre) + autonomie utilisateur |
| 16 | 08/2026 | **Fournisseurs LLM réels (OpenAI/Anthropic)** : adaptateurs HTTP (chat completions / messages API), clés API chiffrées configurables (plateforme héritées + surcharge tenant), modèles paramétrables, boutons « Tester la connexion » (superadmin + tenant), sélection du premier fournisseur autorisé avec clé (repli mock) | CDG §35 ne prévoyait que le fournisseur mock | IA réellement opérationnelle (résumé, QA, classification, extraction, correction, comparaison, traduction, OCR) |

## Écarts à venir (prévus mais non faits)

| Évolution | Statut |
|-----------|--------|
| OnlyOffice (édition Office en ligne réelle) | En attente de décision (VPS requis) |
| MFA obligatoire + expiration de session (application stricte) | Configurables, application V2 |
| Écran « droits par espace/dossier » (gestion fine par périmètre) | Proposition faite, non retenue en V1 |
| Accusés de lecture obligatoires par poste | Optionnels par document (V02 futur) |
