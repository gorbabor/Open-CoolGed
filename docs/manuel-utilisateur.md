# Manuel utilisateur — Kaeged GED

Plateforme de Gestion Électronique de Documents (GED) SaaS multi-tenant.
Ce manuel décrit l'utilisation quotidienne pour les profils : utilisateur standard,
validateur, manager, administrateur du tenant et super administrateur.

---

## 1. Connexion

1. Ouvrir l'URL de la plateforme (ex. `https://votre-domaine`).
2. Saisir votre **email** et votre **mot de passe**, puis « Se connecter ».
3. Si l'authentification à deux facteurs (MFA) est activée sur votre compte,
   saisir le **code à 6 chiffres** de votre application d'authentification.

> Compte suspendu, organisation suspendue ou identifiants invalides :
> un message d'erreur s'affiche et la connexion est refusée.

**Comptes de démonstration (environnement local)**

| Profil | Email | Mot de passe |
|--------|-------|--------------|
| Administrateur tenant | `admin@demo.local` | `password123` |
| Utilisatrice | `user@demo.local` | `password123` |
| Validateur | `validator@demo.local` | `password123` |
| Super administrateur | `superadmin@kaeged.local` | `superadmin123` |

---

## 2. Tableau de bord

Après connexion, le tableau de bord affiche :
- le **stockage utilisé** par l'organisation (avec jauge) ;
- les **documents récents** (cliquables) ;
- vos **tâches de validation** en attente ;
- vos **notifications** récentes.

Bouton « Nouveau document » pour importer un document directement.

---

## 3. Documents

### 3.1 Importer un document

Menu **Documents → Importer** :

1. **Titre** (obligatoire) et **référence** (optionnelle).
2. **Espace** (obligatoire) et **dossier** (optionnel).
3. **Type documentaire** (contrat, facture…) et **confidentialité**.
4. **Description** et **date d'expiration** (optionnelles).
5. Choisir le **fichier** (PDF, Word, Excel, PowerPoint, TXT, CSV, images JPG/PNG/TIFF).
6. Renseigner les **métadonnées** et **tags** si disponibles, puis « Importer et créer ».

> Formats non autorisés, fichiers trop lourds ou quota dépassé : l'import est refusé
> avec un message clair. Le document n'est pas créé.

### 3.2 Consulter un document

Cliquer sur un document dans la liste. La fiche document comporte des onglets :

| Onglet | Contenu |
|--------|---------|
| Aperçu | Contenu textuel ou image (si disponible) |
| Versions | Historique complet, téléchargement, restauration |
| Métadonnées | Référence, confidentialité, expiration, champs personnalisés, tags |
| Workflow | Workflow en cours ou démarrage d'un workflow |
| IA | Résumé, Q/R, classification, extraction, OCR, correction, traduction |
| Commentaires | Fil de discussion |
| Partage | Accès accordés à d'autres utilisateurs/groupes |

Actions principales (selon vos droits) : **Télécharger**, **Éditer**, **Corbeille**.

### 3.3 Versions

- **Créer une version** : onglet Versions → choisir le fichier + commentaire →
  « Créer une version ». L'historique n'est jamais écrasé (la version précédente reste).
- Version **majeure** (2.0) par défaut ; ajouter « [mineur] » dans le commentaire
  pour une version mineure (2.1).
- **Restaurer** une ancienne version : elle devient la version courante, sans supprimer
  l'historique.

### 3.4 Corbeille, archivage, suppression

- **Corbeille** : suppression logique — le document est masqué des listes.
- **Restaurer** : depuis la corbeille, remet le document en place.
- **Archiver** : passe le statut à « archivé » (rétention).
- **Suppression définitive** : réservée aux profils autorisés, tracée dans l'audit.

---

## 4. Espaces et dossiers

Menu **Espaces** :
- Créer un **espace** (Direction, RH, Finances, Projets…) avec couleur et description.
- Ajouter des **dossiers** dans chaque espace.
- Les espaces/dossiers contenant encore des éléments ne peuvent pas être supprimés.

---

## 5. Recherche

Menu **Recherche** :
- Recherche par **titre, référence ou contenu** (plein texte).
- Filtres : type documentaire, statut, tag.
- La recherche ne retourne **que les documents auxquels vous avez accès**.

---

## 6. Workflows (validation)

### 6.1 Démarrer un workflow

Depuis la fiche document, onglet **Workflow** → choisir le workflow → « Démarrer ».
Le document passe en statut « en revue » et des tâches sont créées.

### 6.2 Traiter mes tâches

Menu **Mes tâches** :
- **Valider** : la tâche passe à l'étape suivante ; à la dernière étape, le document
  est « approuvé ».
- **Rejeter** : le **motif est obligatoire** ; le document revient en brouillon et
  le créateur est notifié.

> Une tâche ne peut être traitée que par la personne à qui elle est affectée.

### 6.3 Créer un workflow (administrateur)

Menu **Workflows → Nouveau** :
1. Nom du workflow, type documentaire optionnel.
2. Ajouter les **étapes** (nom + assignation : utilisateur, groupe, rôle ou créateur).
3. Créer puis activer/désactiver.

---

## 7. Intelligence artificielle (IA)

Onglet **IA** de la fiche document (si activée pour votre organisation) :

| Action | Effet |
|--------|-------|
| Résumé | Produit un résumé du contenu |
| Question/Réponse | Répond à partir du document |
| Classification | Propose type/catégorie/tags |
| Extraction | Extrait des champs structurés |
| Correction / Traduction | Propose des corrections / traductions |
| OCR | Extrait le texte des scans et images |

- L'IA **propose**, elle ne modifie jamais le document.
- Vous pouvez **Valider** ou **Rejeter** chaque résultat ; l'application d'un résultat
  validé crée une **nouvelle version** (le document source reste intact).
- Chaque action IA est tracée (qui, quoi, quand) dans l'audit.

---

## 8. Partage

Onglet **Partage** de la fiche document :
1. Choisir un **bénéficiaire** (utilisateur ou groupe) et un **droit** (voir, télécharger, modifier).
2. **Expiration** optionnelle.
3. « Partager » ; le partage peut être **révoqué** à tout moment.

> Un partage ne peut jamais donner plus de droits que ceux que vous possédez.

### Lien externe (si activé par l'organisation)

1. Section « Lien externe » de l'onglet Partage : droit (consultation ou téléchargement),
   **expiration obligatoire**, mot de passe optionnel.
2. « Créer le lien » → le lien sécurisé s'affiche (à copier pour l'envoyer).
3. Le destinataire ouvre le lien, saisit le mot de passe si présent, et consulte/télécharge.
4. Le lien est **révocable** à tout moment et expire à la date fixée.

---

## 9. Notifications

Menu **Notifications** : centre de notifications (tâches, validations, rejets, partages…).
- « Ouvrir » pour marquer comme lue et accéder à l'élément.
- « Tout marquer comme lu ».

---

## 10. Administration du tenant (administrateur)

Menu **Administration** :

### Utilisateurs
- Créer un utilisateur (nom, email, rôle) — invitation par email.
- **Modifier** (nom, email, rôle, groupes), **réinitialiser le mot de passe**,
  **suspendre / réactiver**, **supprimer** (logique — l'historique est conservé).
- Le quota d'utilisateurs de l'organisation est affiché.

### Groupes
- Créer des groupes (Direction, RH, Projets…) et gérer les membres (sélection multiple).
- **Rôles du groupe** : cocher un ou plusieurs rôles — **tous les membres héritent
  automatiquement** de ces rôles (en plus de leurs rôles directs).
- L'écran **Utilisateurs** affiche la colonne **« Permissions effectives »** :
  l'union des rôles directs et hérités des groupes, résolus en permissions.

### Rôles et permissions
- Rôles système prédéfinis (admin tenant, manager, utilisateur, auditeur, validateur).
- Créer des **rôles personnalisés** ; **renommer**, **dupliquer** (permissions copiées),
  **supprimer** (interdit pour un rôle système ou affecté).
- Pour chaque rôle : cocher les permissions, choisir une **portée**
  (tenant / espace / dossier / document) et activer le **refus explicite** si besoin.
- Le refus explicite l'emporte toujours sur une autorisation.

### Types et métadonnées
- Créer des **types documentaires** (contrat, facture…) avec durée de **rétention** ;
  les **modifier** ou les **supprimer** (protégé si des documents les utilisent).
- Créer des **champs de métadonnées** (texte, nombre, date, liste…) et les marquer
  obligatoires ; les **modifier** ou **supprimer** (protégé si des valeurs existent).

### Audit
- Journal consultable/filtrable (action, utilisateur, dates) de toutes les actions
  sensibles — non modifiable. Bouton **Exporter CSV** pour l'archive.

### Paramètres (onglets)
- **Général** : quotas (stockage, utilisateurs, taille max), langue, fuseau horaire.
- **Sécurité** : MFA requis (V2), expiration session (V2), longueur min. mot de passe (appliquée).
- **Documents** : formats autorisés à l'import (appliqués), verrouillage auto à l'édition
  (appliqué), commentaire de version obligatoire (appliqué).
- **Rétention** : durée par défaut, purge corbeille, alerte.
- **Notifications** : défauts (tâches, partages, échéances), email.
- **Partage** : liens externes autorisés, durée max, mot de passe requis.
- **IA** : activation et fournisseurs autorisés.
- **Workflows** : workflow par défaut.
- **Branding** : couleur principale et logo appliqués à l'interface (variables CSS).

---

## 11. Super administration (plateforme)

Menu accessible uniquement au super administrateur (`superadmin@kaeged.local`) :
- Statistiques globales (tenants, utilisateurs, documents, stockage).
- Créer / **suspendre** / réactiver des **tenants** (organisations clientes).
- Créer des super administrateurs.
- **Paramètres plateforme** : défauts (sécurité, formats, partage, IA) appliqués
  automatiquement à chaque nouveau tenant.

> Le super administrateur n'a pas, par défaut, accès au contenu des tenants.

---

## 12. Profil et sécurité

Menu **Profil** :
- Activer le **MFA** : « Générer le secret » → scanner la clé avec votre application
  d'authentification (Google Authenticator, etc.) → saisir un code pour confirmer.
- Désactiver le MFA à tout moment.

---

## 13. Édition et aperçu des documents (viewers & éditeurs en ligne)

### Aperçu (tous les formats)

L'onglet **Aperçu** de la fiche document affiche le contenu **sans téléchargement** :
- **TXT / CSV / JSON / HTML** : texte brut.
- **Markdown (.md)** : rendu mis en forme.
- **PDF** : pages affichées dans le navigateur.
- **Word (.docx)** : rendu HTML fidèle (texte, tableaux, images).
- **Excel (.xlsx)** : tableau avec onglets de feuilles.
- **PowerPoint (.pptx)** : diapositives (qualité variable selon la mise en page).
- **Images** : affichage direct.

### Édition en ligne

Depuis la fiche document : **Éditer** (le document est verrouillé pendant l'édition) :

| Format | Éditeur |
|--------|---------|
| TXT / CSV / JSON / HTML / **MD** | Éditeur intégré (avec **aperçu markdown** en direct pour .md) |
| **PDF** | Annotations : boutons **T** (texte), **🖍 Surligner** (clic-glisser), **🗒 Note** + Annuler, puis **Enregistrer** |
| Word / Excel / PowerPoint | Mode repli : **téléchargement → modification locale → réimport** (nouvelle version) |

- L'enregistrement crée **toujours une nouvelle version** (l'historique est conservé)
  et libère le verrou.
- Une session d'édition appartient à son auteur : un autre utilisateur ne peut pas
  enregistrer à sa place.
- L'édition Office (Word/Excel/PowerPoint) en ligne sera activée quand un serveur
  OnlyOffice sera déployé (nécessite un VPS).

---

## 13bis. Périmètre V02 — GED opérationnelle

Le périmètre V02 ajoute une gouvernance documentaire opérationnelle (Registre maître V02).

### Documents applicables à mon poste

Menu **Mes documents** (sidebar) : liste des documents **approuvés/applicables**,
**version active**, non obsolètes, **filtrés selon vos dimensions** (poste, département,
direction, site, entité, pays) ou vos rôles (propriétaire, vérificateur, approbateur).

- Filtres : section, famille, domaine, criticité, texte (titre/code/référence).
- Colonnes : code, titre, famille, section, version, date d'application, prochaine revue,
  propriétaire, criticité.
- Badges de revue : **proche** (≤ 30 jours) et **en retard** (date dépassée).
- Si le document exige un **accusé de lecture**, le bouton « Accuser » apparaît ;
  une fois fait, le badge « lu » s'affiche.

### Référentiels (Administration → Référentiels)

Domaines, processus, postes, départements, directions, sites, entités, pays.
Utilisés pour filtrer « Mes documents » et qualifier les fiches.

### Familles documentaires

Types pré-créés au format V02 : POL, MAN, REF, PRO, PRC, INS, FOR, REG, MOD, RAP,
PRE, MAT, TDB, CHK, AUT.

### Règles d'approbation

Un document ne peut passer « approuvé/applicable » que si :
1. un fichier est attaché ;
2. une version est renseignée ;
3. un propriétaire est affecté ;
4. une date d'application est fixée ;
5. une prochaine date de revue est fixée.

Une **nouvelle version applicable rend automatiquement obsolète** l'ancienne version
(statut « obsolète/archivé », masquée aux utilisateurs standards, lien remplace/remplacé par).

### Import du registre (administrateur)

Commande : `php artisan v02:import <fichier.csv> [--tenant=1] [--dry-run]`
Colonnes : `id;code;titre;section;lot;famille;version;statut;proprietaire;date_application;prochaine_revue;domaine;criticite`.
Les doublons et lignes invalides sont signalés dans un rapport ; les référentiels
(sections, lots, familles, domaines) sont créés automatiquement.

---

## 14. Conseils d'utilisation

- Utilisez les **espaces/dossiers** pour structurer ; les **tags** et **métadonnées**
  pour retrouver rapidement.
- Commentez les documents pour collaborer (les commentaires sont horodatés).
- Pour une version mineure, indiquez « [mineur] » dans le commentaire de version.
- Vérifiez régulièrement **Mes tâches** pour ne pas laisser une validation en attente.
- En cas de doute sur vos droits, contactez l'administrateur de votre organisation.

---

## 15. Dépannage

| Problème | Solution |
|----------|----------|
| Connexion refusée | Vérifier email/mot de passe ; compte ou organisation suspendus ? |
| Import refusé | Format non autorisé, fichier trop lourd (> taille max) ou quota dépassé |
| Téléchargement refusé | Vous n'avez pas la permission `documents.download` sur ce document |
| Document introuvable | Il est dans la corbeille, archivé, ou hors de votre périmètre de droits |
| Édition impossible | Le document est verrouillé par une autre session — attendez ou réimportez |
| IA indisponible | IA désactivée pour l'organisation, permission manquante, ou fournisseur indisponible (le document reste intact) |
