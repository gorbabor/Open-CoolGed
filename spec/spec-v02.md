# GED documentaire opérationnelle V02 — Spécification (source)

> **ATTENTION — transcription brute** : ce fichier est une extraction texte de l'email
> original (« GED documentaire opérationnelle V02.eml »), non nettoyée : les tableaux y
> sont aplatis et certains mots sont fusionnés (ex. « Politiquede », « Référentielde »).
> L'original .eml n'est pas disponible dans le dépôt (transmis par email) — le déposer dans
> `spec/` s'il devient disponible. Pour une lecture structurée et fiable du périmètre
> implémenté, se référer à `docs/perimetre-v02.md` (mapping V02 ↔ Kaeged, lots A–F).

Document de cadrage fonctionnel et technique **source** (transmis par email).
Support cible d'origine : **Odoo** (module Documents / GED).

> **Cible retenue pour l'implémentation : plateforme Laravel Kaeged** (voir
> `docs/perimetre-v02.md` et `docs/evolutions-cahier-des-charges.md` écart n°9).
> Le périmètre fonctionnel a été transposé ; les points spécifiques à Odoo
> (modules/opérations Odoo) sont hors périmètre de Kaeged.

---
Projet Odoo — GED documentaire opérationnelle V02


Document de cadrage fonctionnel et technique à destination du développeur / 
intégrateur Odoo

  _____


1. Page de titre

Élément	InformationNom 
du projet	Projet Odoo — GED documentaire opérationnelle V02Objet 
du document	Cadrage fonctionnel et technique pour la mise en place d’une GED 
documentaire opérationnelle dans OdooVersion 
du document	V01Date	À 
compléterDestinataire	Développeur 
/ intégrateur OdooÉmetteur	Direction 
Générale / Responsable Système DocumentairePérimètre	Système 
Documentaire V02 de l’entrepriseSupport 
cible	Odoo — module Documents / GED et modules métiers associésStatut	Document 
de cadrage projet  _____


1.1 Objet du document


Le présent document a pour objet de cadrer le projet de paramétrage et d’intégration 
dans Odoo du Système Documentaire V02 de l’entreprise.

Il constitue une base de travail fonctionnelle et technique destinée au 
développeur ou intégrateur Odoo chargé de concevoir, paramétrer et déployer 
une GED documentaire opérationnelle, structurée, sécurisée, versionnée et 
intégrée aux opérations métier.

Le besoin ne consiste pas uniquement à stocker des fichiers PDF, Word ou 
Excel dans Odoo. Il s’agit de créer une GED capable de gérer :

*	le registre maître documentaire ;
*	les métadonnées documentaires ;
*	les sections et lots documentaires ;
*	les familles documentaires ;
*	les droits d’accès ;
*	les versions ;
*	les statuts ;
*	les workflows de vérification et d’approbation ;
*	les documents applicables par poste ;
*	les documents applicables par département, site, entité et pays ;
*	le rattachement aux modules et opérations Odoo ;
*	la traçabilité ;
*	l’auditabilité ;
*	les notifications ;
*	les preuves de lecture, de diffusion, d’approbation et d’archivage.

  _____


2. Contexte général


2.1 Présentation du Registre maître documentaire V02


L’entreprise a construit un Registre maître documentaire V02 permettant de 
structurer l’ensemble du système documentaire selon une logique organisée 
par :

*	sections ;
*	lots ;
*	ID documentaires ;
*	codes documents ;
*	titres documentaires ;
*	types documentaires ;
*	niveaux documentaires ;
*	domaines ;
*	processus ;
*	propriétaires ;
*	vérificateurs ;
*	approbateurs ;
*	périmètres d’application ;
*	pays, entités et sites ;
*	criticité ;
*	fréquence de revue ;
*	support ;
*	preuves associées ;
*	lien Odoo ;
*	obligation réglementaire ;
*	version ;
*	statut ;
*	commentaire V02.

Le registre maître documentaire est la source de référence pour l’identification, 
le classement et le suivi des documents du Système Documentaire V02.

  _____


2.2 Sections déjà validées


Les sections déjà validées à intégrer dans Odoo sont les suivantes :

Section	Intitulé	Plage d’IDSection 
00	Gouvernance documentaire	ID 001 à 020Section 
01	Gouvernance Groupe et juridique	ID 021 à 040Section 
02	Stratégie Groupe, pilotage et performance	ID 041 à 060Section 
03	Organisation Groupe, responsabilités et délégations opérationnelles	ID 
061 à 080Section 
04	Management des risques, contrôle interne et conformité Groupe	ID 081 à 
100Aucune création de nouvelle section documentaire n’est demandée dans le 
présent document. Le projet Odoo doit uniquement intégrer et exploiter les 
sections existantes ou déjà validées.

  _____


2.3 Logique de continuité des ID


Chaque document du registre maître dispose d’un ID documentaire unique.

La logique actuelle repose sur une continuité numérique par section :

*	Section 00 : ID 001 à 020 ;
*	Section 01 : ID 021 à 040 ;
*	Section 02 : ID 041 à 060 ;
*	Section 03 : ID 061 à 080 ;
*	Section 04 : ID 081 à 100.

Cette logique doit être strictement conservée dans Odoo.

Odoo ne doit pas générer automatiquement un nouvel ID documentaire métier 
différent de l’ID du registre maître, sauf si un identifiant technique 
interne est nécessaire. Dans ce cas, l’identifiant technique Odoo ne doit 
jamais remplacer l’ID documentaire officiel.

  _____


2.4 Nécessité d’intégrer le système documentaire dans Odoo


L’intégration dans Odoo répond aux besoins suivants :

*	centraliser les documents applicables ;
*	fiabiliser l’accès aux versions à jour ;
*	éviter l’usage de documents obsolètes ;
*	lier les documents aux opérations métier ;
*	limiter l’accès aux documents selon les responsabilités ;
*	fournir à chaque utilisateur les documents utiles à son poste ;
*	sécuriser les documents critiques ou confidentiels ;
*	organiser les workflows de création, vérification et approbation ;
*	historiser les actions documentaires ;
*	faciliter les audits internes, contrôles et revues ;
*	renforcer la gouvernance documentaire de l’entreprise.

  _____


3. Objectifs du projet


3.1 Objectif général


Mettre en place dans Odoo une GED documentaire opérationnelle V02 permettant 
de gérer, diffuser, sécuriser et exploiter l’ensemble des documents du 
Registre maître documentaire V02.

  _____


3.2 Objectifs détaillés


Le projet doit permettre de :

Objectif	Description attendueCentraliser 
les documents	Regrouper dans Odoo les documents du Système Documentaire V02.Classer 
les documents	Classer par section, lot, famille documentaire, domaine, 
processus, entité, site et poste.Gérer 
les versions	Identifier la version applicable et archiver les versions 
obsolètes.Gérer 
les statuts	Suivre les documents depuis la création jusqu’à l’archivage.Gérer 
les droits d’accès	Appliquer des droits par utilisateur, poste, département, 
entité, site, pays, groupe et module.Lier 
les documents aux postes	Afficher les documents applicables selon la 
fonction de l’utilisateur connecté.Lier 
les documents aux opérations Odoo	Afficher les procédures, instructions, 
formulaires ou règles applicables dans les écrans métier concernés.Garantir 
l’utilisation des documents à jour	Masquer ou restreindre les documents 
obsolètes.Assurer 
la traçabilité	Historiser les créations, modifications, validations, 
consultations et archivages.Assurer 
l’auditabilité	Permettre aux auditeurs habilités de consulter les preuves et 
historiques nécessaires.Notifier 
les acteurs	Envoyer des alertes lors des étapes clés : vérification, 
approbation, revue, obsolescence, nouvelle version.Gérer 
les preuves	Conserver les validations, accusés de lecture, preuves de 
diffusion et preuves d’archivage.  _____


4. Périmètre fonctionnel


4.1 Documents concernés


Le projet concerne les documents du Registre maître documentaire V02 
appartenant aux sections déjà validées :

*	Section 00 — Gouvernance documentaire ;
*	Section 01 — Gouvernance Groupe et juridique ;
*	Section 02 — Stratégie Groupe, pilotage et performance ;
*	Section 03 — Organisation Groupe, responsabilités et délégations 
opérationnelles ;
*	Section 04 — Management des risques, contrôle interne et conformité 
Groupe.

Les types de documents concernés incluent notamment :

*	politiques ;
*	manuels ;
*	référentiels ;
*	processus ;
*	procédures ;
*	instructions ;
*	formulaires ;
*	registres ;
*	modèles ;
*	rapports ;
*	preuves ;
*	matrices ;
*	tableaux de bord ;
*	documents associés aux contrôles ;
*	documents associés aux délégations ;
*	documents associés à la conformité ;
*	documents associés à la gouvernance.

  _____


4.2 Utilisateurs concernés


Les utilisateurs concernés sont :

*	Direction Générale ;
*	directions fonctionnelles ;
*	responsables métiers ;
*	propriétaires documentaires ;
*	vérificateurs ;
*	approbateurs ;
*	utilisateurs opérationnels ;
*	collaborateurs par site ;
*	collaborateurs par entité ;
*	collaborateurs par département ;
*	administrateurs GED ;
*	auditeurs internes ;
*	contrôleurs internes ;
*	utilisateurs Odoo concernés par les opérations métier.

  _____


4.3 Entités concernées


Le système doit permettre de gérer des documents applicables :

*	au Groupe ;
*	à une entité juridique spécifique ;
*	à plusieurs entités ;
*	à une filiale ;
*	à une direction ;
*	à un département ;
*	à un périmètre fonctionnel ;
*	à un site ;
*	à un pays.

  _____


4.4 Sites concernés


Le paramétrage doit permettre d’associer un document à :

*	tous les sites ;
*	un site précis ;
*	plusieurs sites ;
*	un site de production ;
*	un site logistique ;
*	un site administratif ;
*	un siège ;
*	un établissement spécifique.

  _____


4.5 Modules Odoo concernés


Les modules Odoo à prendre en compte sont au minimum :

*	Documents / GED ;
*	Qualité ;
*	Approbations ;
*	Employés / RH ;
*	Achats ;
*	Ventes ;
*	Inventaire ;
*	Fabrication ;
*	Maintenance ;
*	Comptabilité ;
*	Projet.

  _____


4.6 Postes et départements concernés


Le système doit permettre d’associer les documents à :

*	un poste ;
*	plusieurs postes ;
*	un département ;
*	une direction ;
*	une équipe ;
*	un rôle métier ;
*	un groupe de sécurité ;
*	une responsabilité opérationnelle.

Exemples :

Poste / rôle	Documents applicables possiblesDirecteur 
Général	Politiques, délégations, tableaux de bord, rapports de gouvernanceResponsable 
Qualité	Procédures qualité, registres, rapports d’audit, plans d’actionsResponsable 
Achats	Procédures achats, règles fournisseurs, délégations d’engagementGestionnaire 
stock	Instructions réception, stockage, inventaireTechnicien 
maintenance	Procédures maintenance, checklists, instructions sécuritéComptable	Procédures 
comptables, règles de validation, contrôles internesResponsable 
RH	Politiques RH, formulaires RH, règles de délégation RHAuditeur 
interne	Documents applicables et archives selon habilitation  _____


5. Hors périmètre éventuel


5.1 Hors périmètre de la première phase


Les éléments suivants peuvent être exclus de la première phase, sauf 
décision contraire :

*	numérisation massive d’archives papier historiques ;
*	migration complète de tous les documents antérieurs non V02 ;
*	signature électronique avancée qualifiée ;
*	coffre-fort numérique légal ;
*	OCR automatique de documents scannés ;
*	automatisation avancée par intelligence artificielle ;
*	gestion documentaire externe fournisseurs ou clients ;
*	portail documentaire externe ;
*	refonte complète des processus métier Odoo ;
*	développement d’une application mobile dédiée ;
*	gestion documentaire réglementaire pays par pays si non disponible dans le 
registre initial ;
*	reprise exhaustive des historiques de consultation antérieurs à Odoo.

  _____


5.2 Développements futurs possibles


Des évolutions futures pourront être envisagées :

*	accusés de lecture obligatoires par poste ;
*	quiz de compréhension après lecture ;
*	signature électronique des validations ;
*	lien avec les formations RH ;
*	tableau de bord de conformité documentaire ;
*	gestion documentaire fournisseur ;
*	gestion documentaire client ;
*	workflow avancé multi-approbateurs ;
*	archivage légal renforcé ;
*	intégration avec une solution externe de GED ;
*	lecture documentaire obligatoire avant habilitation à une opération ;
*	contrôle de validité documentaire bloquant dans certains processus Odoo.

  _____


6. Architecture documentaire cible dans Odoo


6.1 Dossier racine


Créer un dossier racine dans Odoo intitulé :

Système Documentaire V02

Ce dossier doit être le point d’entrée principal de la GED documentaire 
opérationnelle.

  _____


6.2 Classement par sections


Sous le dossier racine, créer les sections suivantes :

Code section	Dossier Odoo00 
00 — Gouvernance documentaire01 
01 — Gouvernance Groupe et juridique02 
02 — Stratégie Groupe, pilotage et performance03 
03 — Organisation Groupe, responsabilités et délégations opérationnelles04 
04 — Management des risques, contrôle interne et conformité Groupe  _____


6.3 Classement par lots


Chaque section doit contenir ses lots documentaires.

Exemple :

text
Système Documentaire V02

└── 00 — Gouvernance documentaire

    └── Lot 00 — Gouvernance documentaire

        ├── KAE-DOC-POL-001-V01

        ├── KAE-DOC-MAN-001-V01

        ├── KAE-DOC-PRO-001-V01

        └── ...




Le classement par lot doit être paramétrable, car d’autres lots peuvent 
exister au sein d’une section selon l’évolution du registre.

  _____


6.4 Classement par familles documentaires


Chaque document doit être rattaché à une famille documentaire :

*	POL — Politique ;
*	MAN — Manuel ;
*	REF — Référentiel ;
*	PRO — Processus ;
*	PRC — Procédure ;
*	INS — Instruction ;
*	FOR — Formulaire ;
*	REG — Registre ;
*	MOD — Modèle ;
*	RAP — Rapport ;
*	PRE — Preuve ;
*	MAT — Matrice ;
*	TDB — Tableau de bord ;
*	CHK — Checklist ;
*	AUT — Autre si nécessaire.

La famille documentaire doit être une métadonnée obligatoire.

  _____


6.5 Classement par domaines


Le système doit permettre un classement transversal par domaine principal et 
domaine secondaire.

Exemples de domaines :

*	DOC — Documentation ;
*	GOV — Gouvernance ;
*	LEGAL — Juridique ;
*	QMS — Qualité ;
*	RISK — Risques ;
*	FIN — Finance ;
*	HR — Ressources humaines ;
*	OPS — Opérations ;
*	PUR — Achats ;
*	SALES — Ventes ;
*	IT — Systèmes d’information ;
*	MAINT — Maintenance ;
*	PROD — Production ;
*	LOG — Logistique.

  _____


6.6 Classement par processus


Chaque document doit pouvoir être lié à :

*	un processus principal ;
*	un processus secondaire ;
*	un ou plusieurs processus opérationnels ;
*	un module Odoo ;
*	une opération métier Odoo.

Exemples :

Processus principal	Processus secondaireGouvernance 
documentaire	Codification documentaireGouvernance 
documentaire	Publication et diffusionContrôle 
interne	Gestion des risquesAchats	Qualification 
fournisseurInventaire	Réception 
et stockageFabrication	Ordre 
de fabricationMaintenance	Intervention 
correctiveRH	Gestion 
du personnel  _____


6.7 Classement par entités, sites et pays


Un document doit pouvoir être applicable à :

*	tous pays ;
*	un pays ;
*	plusieurs pays ;
*	toutes entités ;
*	une entité ;
*	plusieurs entités ;
*	tous sites ;
*	un site ;
*	plusieurs sites.

Le système doit donc prévoir des champs multi-valeurs pour les pays, entités 
et sites lorsque nécessaire.

  _____


6.8 Classement par postes ou groupes métiers


Chaque document doit pouvoir être associé à :

*	un poste ;
*	plusieurs postes ;
*	un groupe métier ;
*	un département ;
*	une direction ;
*	un groupe de sécurité Odoo ;
*	un profil utilisateur ;
*	un module Odoo utilisé.

Cette logique est indispensable pour créer le menu :

Documents applicables à mon poste

  _____


7. Familles documentaires à créer


Les familles documentaires suivantes doivent être créées dans Odoo.

Code	Famille documentaire	Description	ExemplePOL	Politique	Document 
de haut niveau fixant une orientation ou une règle générale	Politique de 
gouvernance documentaireMAN	Manuel	Document 
structurant décrivant un système ou un dispositif global	Manuel de gestion 
documentaireREF	Référentiel	Document 
de référence regroupant des exigences, règles ou cadres structurantsRéférentielde contrôle interne
PRO	Processus	Description macro d’un enchaînement d’activités	Processus de 
maîtrise documentairePRC	Procédure	Description 
détaillée des règles de réalisation d’une activité	Procédure de publication 
documentaireINS	Instruction	Consigne 
opérationnelle précise	Instruction de nommage des fichiersFOR	Formulaire	Support 
vierge ou renseigné destiné à collecter des informations	Formulaire de 
demande de création documentaireREG	Registre	Tableau 
ou base de suivi officiel	Registre maître documentaireMOD	Modèle	Gabarit 
ou trame standardisée	Modèle standard de procédureRAP	Rapport	Document 
restituant une analyse, un audit ou un résultat	Rapport de revue 
documentairePRE	Preuve	Élément 
démontrant une action ou une conformité	Preuve d’approbationMAT	Matrice	Tableau 
de correspondance, de droits ou de responsabilités	Matrice RACITDB	Tableau 
de bord	Support de pilotage avec indicateurs	Tableau de bord documentaireCHK	Checklist	Liste 
de contrôle	Checklist de conformité documentaireAUT	Autre	Catégorie 
résiduelle à utiliser exceptionnellement	Document non classable 
temporairementL’utilisation de la famille AUT — Autre doit rester exceptionnelle et 
contrôlée par l’administrateur GED.

  _____


8. Champs / métadonnées à créer dans Odoo


8.1 Principe général


Les 26 colonnes du Registre maître documentaire V02 doivent être reprises 
dans Odoo sous forme de champs structurés.

L’objectif est d’éviter une GED limitée au stockage de fichiers. Chaque 
document doit être qualifié par des métadonnées exploitables pour :

*	rechercher ;
*	filtrer ;
*	sécuriser ;
*	notifier ;
*	auditer ;
*	rattacher aux opérations Odoo ;
*	afficher les documents applicables par poste.

  _____


8.2 Tableau des champs recommandés

Colonne registre	Nom du champ Odoo recommandé	Type de champ recommandéObligatoire	Description	Exemplede valeur
ID	x_document_master_id	Char / Texte court unique	Oui	ID officiel du 
registre maître documentaire	001Section	x_section_id	Many2one 
/ Liste de valeurs	Oui	Section documentaire de rattachement	00 — Gouvernance 
documentaireLot	x_lot_id	Many2one 
/ Liste de valeurs	Oui	Lot documentaire de rattachement	Lot 00 — Gouvernance 
documentaireCode 
document	x_document_code	Char / Texte court unique	Oui	Code documentaire 
officiel	KAE-DOC-POL-001-V01Titre 
document	x_document_title	Char / Texte	Oui	Titre officiel du documentPolitiquede gouvernance documentaire Groupe
Type	x_document_type	Selection / Many2one	Oui	Famille documentaire	POL — 
PolitiqueNiveau	x_document_level	Selection 
/ Many2one	Oui	Niveau documentaire dans la hiérarchie	Niveau 1 — PolitiqueDomaine 
principal	x_primary_domain_id	Many2one	Oui	Domaine principal du documentDOC — Documentation
Domaine secondaire	x_secondary_domain_ids	Many2many	Non	Domaines secondaires 
associés	GOV / QMSProcessus 
principal	x_primary_process_id	Many2one	Oui	Processus principal couvertGouvernancedocumentaire
Processus secondaire	x_secondary_process_ids	Many2many	Non	Processus 
secondaires couverts	Publication, diffusionPropriétaire	x_owner_id	Many2one 
vers utilisateur / employé	Oui	Responsable métier ou documentaireResponsableGouvernance documentaire
Vérificateur	x_reviewer_id	Many2one vers utilisateur / employé	Oui si 
workflow actif	Personne chargée de vérifier le document	Responsable Qualité 
GroupeApprobateur	x_approver_id	Many2one 
vers utilisateur / employé	Oui si document applicable	Personne chargée d’approuver 
le document	Direction GénéralePérimètre 
d’application	x_application_scope	Selection / Many2many	Oui	Périmètre d’application 
du document	GroupePays 
/ entité	x_country_entity_ids	Many2many	Oui	Pays ou entités concernés	Toutes 
entitésSite 
concerné	x_site_ids	Many2many	Oui	Sites concernés	Tous sitesCriticité	x_criticality	Selection	Oui	Niveau 
de criticité documentaire	CritiqueFréquence 
de revue	x_review_frequency	Selection	Oui	Périodicité de revue	AnnuelleSupport	x_support_type	Selection 
/ Many2many	Non	Support documentaire	Word contrôlé / PDF approuvéPreuve 
associée	x_associated_evidence	Text / Many2many documents	Non	Preuves liées 
au document	Preuve d’approbationLien 
Odoo	x_odoo_link	URL / Reference	Non	Lien vers l’enregistrement Odoo ou l’opération 
associée	Lien fiche documentObligation 
réglementaire	x_regulatory_obligation	Boolean + commentaire	Oui	Indique si 
le document répond à une obligation réglementaire	Oui / NonVersion	x_version	Char 
/ Selection	Oui	Version officielle du document	V01Statut	x_status	Selection	Oui	Statut 
documentaire	Approuvé / ApplicableCommentaire 
V02	x_v02_comment	Text	Non	Commentaire de migration ou de création V02Documentcréé dans la refonte V02
  _____


8.3 Champs complémentaires fortement recommandés


En plus des 26 colonnes du registre, les champs suivants sont recommandés 
pour rendre la GED opérationnelle.

Champ complémentaire	Type recommandé	Obligatoire	Descriptionx_applicable_job_ids	Many2many 
postes	Oui à terme	Postes auxquels le document s’appliquex_department_ids	Many2many 
départements	Oui à terme	Départements concernésx_direction_ids	Many2many 
directions	Non	Directions concernéesx_odoo_module_ids	Many2many 
modules Odoo	Oui à terme	Modules Odoo concernésx_odoo_operation_ids	Many2many 
opérations / modèles Odoo	Non	Opérations Odoo concernéesx_confidentiality_level	Selection	Oui	Niveau 
de confidentialitéx_effective_date	Date	Oui 
si applicable	Date d’entrée en applicationx_next_review_date	Date 
calculée ou saisie	Oui	Date de prochaine revuex_archive_date	Date	Non	Date 
d’archivagex_replaced_by_document_id	Many2one 
document	Non	Document ou version remplaçantex_replaces_document_id	Many2one 
document	Non	Document ou version remplacéex_read_ack_required	Boolean	Non	Indique 
si un accusé de lecture est requisx_read_ack_user_ids	Many2many 
utilisateurs	Non	Utilisateurs devant accuser lecturex_is_active_version	Boolean	Oui	Indique 
si la version est applicablex_archive_reason	Text	Non	Motif 
d’archivagex_import_batch_id	Char 
/ Many2one	Non	Lot d’import initial ou complémentaire  _____


9. Workflow documentaire cible


9.1 Statuts documentaires


Le workflow documentaire cible doit intégrer les statuts suivants :

Statut	DescriptionÀ 
créer	Document identifié dans le registre mais fichier ou contenu non encore 
créé.Brouillon	Document 
en cours de rédaction ou de préparation.En 
vérification	Document soumis au vérificateur pour contrôle du fond, de la 
forme et de la conformité.En 
approbation	Document validé par le vérificateur et soumis à l’approbateur 
final.Approuvé 
/ Applicable	Document validé, publié et applicable aux utilisateurs 
concernés.En 
révision	Document applicable ou anciennement applicable en cours de mise à 
jour.Obsolète 
/ Archivé	Document remplacé, retiré ou non applicable, conservé en archive.  _____


9.2 Règles de passage d’un statut à l’autre

Statut source	Statut cible	Condition de passage	Acteur autoriséÀ 
créer	Brouillon	Création ou dépôt du fichier initial	Créateur / Propriétaire 
/ Administrateur GEDBrouillon	En 
vérification	Document prêt pour vérification	Propriétaire du documentEn 
vérification	Brouillon	Corrections demandées	VérificateurEn 
vérification	En approbation	Vérification validée	VérificateurEn 
approbation	Brouillon	Approbation refusée ou corrections majeuresApprobateurEn approbation	Approuvé / Applicable	Approbation validée	Approbateur
Approuvé / Applicable	En révision	Révision périodique ou demande de 
modification	Propriétaire / Administrateur GEDEn 
révision	En vérification	Nouvelle version prête	PropriétaireApprouvé 
/ Applicable	Obsolète / Archivé	Remplacement, retrait ou fin d’applicationAdministrateurGED / Propriétaire avec validation
En révision	Obsolète / Archivé	Révision abandonnée ou document remplacéAdministrateurGED
Obsolète / Archivé	Approuvé / Applicable	Réactivation exceptionnelle validéeAdministrateurGED + Approbateur
  _____


9.3 Règles de contrôle du workflow


Les règles suivantes doivent être appliquées :

1.	Un document ne peut pas être Approuvé / Applicable sans fichier attaché.
2.	Un document ne peut pas être applicable sans version renseignée.
3.	Un document ne peut pas être applicable sans propriétaire.
4.	Un document applicable doit avoir une date d’application.
5.	Un document applicable doit avoir une prochaine date de revue.
6.	Un document approuvé doit être verrouillé en modification pour les 
utilisateurs standards.
7.	Seuls les brouillons et documents en révision peuvent être modifiés.
8.	Les documents obsolètes doivent rester consultables uniquement par les 
personnes habilitées.
9.	Une nouvelle version applicable doit automatiquement rendre l’ancienne 
version obsolète ou archivée.
10.	Toute approbation doit être historisée.

  _____


10. Rôles et responsabilités


10.1 Créateur


Le créateur est la personne qui initie une fiche documentaire ou dépose un 
document dans Odoo.

Responsabilités :

*	créer la fiche documentaire ;
*	renseigner les premières métadonnées ;
*	rattacher le fichier initial ;
*	affecter le propriétaire si nécessaire ;
*	soumettre le document en brouillon ou en vérification.

  _____


10.2 Propriétaire du document


Le propriétaire est responsable du contenu métier du document.

Responsabilités :

*	garantir l’exactitude du contenu ;
*	maintenir le document à jour ;
*	initier les révisions ;
*	définir les postes et départements concernés ;
*	proposer les droits d’accès ;
*	suivre les dates de revue ;
*	traiter les commentaires ou demandes de correction.

  _____


10.3 Vérificateur


Le vérificateur contrôle le document avant approbation.

Responsabilités :

*	vérifier la cohérence du document ;
*	contrôler la conformité à la structure documentaire ;
*	vérifier les métadonnées ;
*	demander des corrections si nécessaire ;
*	valider le passage en approbation.

  _____


10.4 Approbateur


L’approbateur valide officiellement le document.

Responsabilités :

*	approuver ou refuser le document ;
*	garantir l’autorisation de diffusion ;
*	valider le caractère applicable du document ;
*	déclencher la publication ;
*	assurer la responsabilité finale de l’approbation.

  _____


10.5 Utilisateur lecteur


L’utilisateur lecteur consulte les documents applicables à son poste ou à 
ses opérations.

Responsabilités :

*	consulter les documents applicables ;
*	utiliser uniquement les versions en vigueur ;
*	accuser lecture si demandé ;
*	signaler toute incohérence ou document manquant ;
*	ne pas diffuser de document confidentiel sans autorisation.

  _____


10.6 Responsable métier


Le responsable métier supervise l’application documentaire dans son 
périmètre.

Responsabilités :

*	valider les documents applicables à son équipe ;
*	contribuer à la matrice documents / postes ;
*	vérifier que les utilisateurs disposent des documents nécessaires ;
*	participer aux revues périodiques ;
*	remonter les besoins de création ou modification documentaire.

  _____


10.7 Administrateur GED


L’administrateur GED gère le système dans Odoo.

Responsabilités :

*	paramétrer les dossiers ;
*	maintenir les métadonnées ;
*	gérer les familles documentaires ;
*	gérer les droits d’accès ;
*	superviser les imports ;
*	archiver les versions obsolètes ;
*	contrôler les doublons ;
*	maintenir les workflows ;
*	produire les états de suivi ;
*	assister les utilisateurs ;
*	garantir la cohérence de la GED.

  _____


10.8 Auditeur interne ou contrôleur interne


L’auditeur interne ou contrôleur interne consulte les documents et preuves 
dans le cadre des contrôles.

Responsabilités :

*	consulter les documents selon habilitation ;
*	vérifier la conformité du workflow ;
*	vérifier les preuves d’approbation ;
*	vérifier les versions applicables ;
*	vérifier les documents obsolètes et archivés ;
*	émettre des constats ;
*	demander des actions correctives si nécessaire.

  _____


11. Gestion des versions


11.1 Principes généraux


La gestion des versions doit garantir que les utilisateurs accèdent 
uniquement à la version applicable.

Chaque document doit disposer :

*	d’un code document ;
*	d’une version ;
*	d’un statut ;
*	d’une date d’application ;
*	d’un historique des versions ;
*	d’un lien avec la version précédente et la version suivante si applicable.

  _____


11.2 Création initiale


La création initiale d’un document est réalisée en version :

V01

Exemple :

text
KAE-DOC-POL-001-V01




La version V01 correspond à la première version approuvée ou destinée à être 
approuvée du document.

  _____


11.3 Révision majeure


Les révisions majeures doivent suivre la logique :

*	V01 ;
*	V02 ;
*	V03 ;
*	V04 ;
*	etc.

Une révision majeure est requise lorsque :

*	le contenu métier change significativement ;
*	le périmètre d’application change ;
*	le workflow change ;
*	les responsabilités changent ;
*	les règles applicables changent ;
*	une nouvelle validation formelle est nécessaire.

  _____


11.4 Révision mineure


Une révision mineure peut être utilisée si l’entreprise souhaite distinguer 
les corrections non substantielles.

Exemples :

*	V01.1 ;
*	V01.2 ;
*	V02.1.

Une révision mineure peut être utilisée pour :

*	correction typographique ;
*	modification de mise en page ;
*	ajout non substantiel ;
*	clarification sans impact métier ;
*	correction de lien ou de métadonnée.

La politique de versioning doit être validée avant paramétrage définitif.

  _____


11.5 Archivage des anciennes versions


Lorsqu’une nouvelle version devient applicable :

1.	l’ancienne version passe en statut Obsolète / Archivé ;
2.	elle est déplacée ou marquée comme archive ;
3.	elle n’est plus visible par les utilisateurs standards ;
4.	elle reste consultable par les profils habilités ;
5.	elle conserve son historique et ses preuves ;
6.	elle indique la version qui la remplace.

  _____


11.6 Identification de la version applicable


La version applicable doit être clairement identifiable par :

*	le statut Approuvé / Applicable ;
*	le champ x_is_active_version = Oui ;
*	la date d’application ;
*	l’absence de date d’archivage ;
*	le lien éventuel vers les postes ou opérations concernés.

  _____


11.7 Interdiction d’utiliser une version obsolète


Le système doit empêcher ou limiter l’utilisation des versions obsolètes :

*	masquage des documents obsolètes aux utilisateurs standards ;
*	affichage d’un avertissement si un utilisateur habilité ouvre une archive 
;
*	absence de rattachement opérationnel des versions obsolètes ;
*	lien obligatoire vers la version applicable lorsqu’elle existe ;
*	interdiction d’utiliser un document obsolète comme référence active dans 
une opération Odoo.

  _____


12. Gestion des droits d’accès


12.1 Principes de sécurité


Les droits d’accès doivent respecter le principe du besoin d’en connaître.

Un utilisateur ne doit accéder qu’aux documents nécessaires à :

*	son poste ;
*	son département ;
*	sa direction ;
*	son entité ;
*	son pays ;
*	son site ;
*	son groupe de sécurité ;
*	ses modules Odoo ;
*	ses opérations Odoo ;
*	ses responsabilités dans le workflow.

Il est déconseillé de donner un accès global à l’ensemble du système 
documentaire à tous les utilisateurs.

  _____


12.2 Critères de droits


Les droits doivent pouvoir être déterminés selon :

Critère	DescriptionUtilisateur	Accès 
nominatif spécifiquePoste	Accès 
selon fonction occupéeDépartement	Accès 
selon rattachement organisationnelDirection	Accès 
selon niveau hiérarchiqueEntité	Accès 
selon société ou filialePays	Accès 
selon localisation géographiqueSite	Accès 
selon site opérationnelGroupe 
de sécurité	Accès selon profil OdooModule 
Odoo utilisé	Accès selon les modules utilisés au quotidienRôle 
documentaire	Créateur, propriétaire, vérificateur, approbateur, lecteurConfidentialité	Niveau 
de restriction documentaire  _____


12.3 Niveaux d’accès recommandés

Niveau d’accès	DescriptionAucun 
accès	L’utilisateur ne voit pas le document.Lecture	L’utilisateur 
peut consulter le document applicable.Lecture 
+ accusé	L’utilisateur doit confirmer avoir pris connaissance du document.Contribution	L’utilisateur 
peut proposer ou modifier en brouillon selon autorisation.Vérification	L’utilisateur 
peut vérifier et commenter.Approbation	L’utilisateur 
peut approuver ou refuser.Administration	L’utilisateur 
peut gérer les métadonnées, droits, statuts et archives.Audit	L’utilisateur 
peut consulter les historiques et preuves selon périmètre.  _____


12.4 Matrice de droits indicative

Profil	Lecture documents applicables	Lecture documents confidentielsCréation	Modificationbrouillon	Vérification	Approbation	Archivage	Administration droits	Accès 
archives	Audit / preuvesDirection 
Générale	Oui	Oui selon périmètre	Non par défaut	Non par défaut	Possible	OuiPossible	Non	Oui	OuiJuridique / Conformité	Oui	Oui	Oui	Oui selon périmètre	Oui	Possible	Possible 
Non	Oui	OuiContrôle 
interne / Risques	Oui	Oui selon habilitation	Oui	Oui selon périmètre	OuiPossible	Possible	Non	Oui	OuiFinance	Oui finance	Selon habilitation	Possible	Oui sur documents finance 
Possible	Possible	Non par défaut	Non	Limité	LimitéRH	Oui 
RH	Oui RH	Possible	Oui sur documents RH	Possible	Possible	Non par défaut	NonLimité	LimitéOpérations	Oui opérations	Non sauf habilitation	Possible	Oui sur documents 
opérations	Possible	Possible	Non par défaut	Non	Non par défaut	LimitéQualité	Oui	Oui 
selon périmètre	Oui	Oui	Oui	Possible	Possible	Non	Oui	OuiAchats	Oui 
achats	Selon habilitation	Possible	Oui sur documents achats	PossiblePossible	Nonpar défaut	Non	Limité	Limité
Ventes	Oui ventes	Selon habilitation	Possible	Oui sur documents ventesPossible	Possible	Nonpar défaut	Non	Limité	Limité
Maintenance	Oui maintenance	Non sauf habilitation	Possible	Oui sur documents 
maintenance	Possible	Non par défaut	Non par défaut	Non	Non par défaut	LimitéUtilisateur 
standard	Oui selon poste	Non	Non	Non	Non	Non	Non	Non	Non	NonAuditeur 
interne	Oui selon mission	Oui selon habilitation	Non	Non	Non	Non	Non	Non	OuiOuiAdministrateur GED	Oui	Oui technique selon règles	Oui	Oui	Non métier par 
défaut	Non métier par défaut	Oui	Oui	Oui	OuiCette matrice devra être affinée avec les responsables métiers avant import 
massif.

  _____


12.5 Règles complémentaires d’accès


1.	Les documents Approuvés / Applicables sont visibles uniquement par les 
profils concernés.
2.	Les documents Brouillon, En vérification et En approbation ne sont 
visibles que par les acteurs du workflow.
3.	Les documents Obsolètes / Archivés sont masqués aux utilisateurs 
standards.
4.	Les documents confidentiels nécessitent des groupes de sécurité 
spécifiques.
5.	L’administrateur GED dispose d’un accès technique mais ne remplace pas 
les approbateurs métier.
6.	Les droits doivent être testés avant déploiement.
7.	Toute modification de droits doit être historisée si possible.

  _____


13. Menu “Documents applicables à mon poste”


13.1 Objectif du menu


Créer dans Odoo un menu dédié intitulé :

Documents applicables à mon poste

Ce menu doit permettre à chaque utilisateur connecté de consulter rapidement 
les documents qui lui sont applicables.

  _____


13.2 Règles d’affichage


Le menu doit afficher uniquement les documents :

*	rattachés au poste de l’utilisateur ;
*	ou rattachés à son département ;
*	ou rattachés à sa direction ;
*	ou rattachés à son site ;
*	ou rattachés à son entité ;
*	ou rattachés à son pays ;
*	ou rattachés aux modules Odoo qu’il utilise ;
*	ou rattachés aux processus dans lesquels il intervient ;
*	ayant le statut Approuvé / Applicable ;
*	correspondant à la version active ;
*	non obsolètes ;
*	autorisés par son groupe de sécurité.

  _____


13.3 Filtres attendus


Le menu doit permettre de filtrer par :

Filtre	ExempleSection 
00 — Gouvernance documentaireLot	Lot 
00 — Gouvernance documentaireFamille 
documentaire	PRC — ProcédureDomaine 
DOC — DocumentationProcessus	Publication 
documentairePoste	Responsable 
QualitéDépartement	Qualité
Site	Site AEntité	Entité 
GroupePays	Côte 
d’Ivoire / France / autre pays concernéModule 
Odoo	AchatsStatut	Approuvé 
/ ApplicableVersion	V01
Criticité	Critique  _____


13.4 Affichage recommandé


L’écran doit afficher au minimum :

*	ID ;
*	code document ;
*	titre document ;
*	type ;
*	section ;
*	lot ;
*	version ;
*	statut ;
*	date d’application ;
*	date de prochaine revue ;
*	propriétaire ;
*	criticité ;
*	module Odoo concerné ;
*	bouton d’ouverture du fichier ;
*	bouton d’accusé de lecture si applicable.

  _____


14. Rattachement aux modules Odoo


14.1 Principe général


Les documents ne doivent pas uniquement être classés dans une GED centrale. 
Ils doivent aussi pouvoir être affichés dans les modules Odoo concernés par 
leur utilisation opérationnelle.

  _____


14.2 Modules et documents associés

Module Odoo	Documents à afficher potentiellementDocuments 
/ GED	Tous documents selon droits, registre, archives, preuvesQualité	Politiques 
qualité, procédures qualité, checklists, rapports d’audit, plans d’action, 
contrôles qualitéApprobations	Délégations, 
règles de validation, seuils d’approbation, procédures d’approbationEmployés 
/ RH	Politiques RH, procédures RH, formulaires RH, fiches de poste, 
délégations RHAchats	Procédures 
achats, règles fournisseurs, critères de sélection, délégations d’engagement, 
formulaires achatsVentes	Procédures 
commerciales, règles de validation devis, conditions commerciales, modèles 
contractuelsInventaire	Instructions 
réception, stockage, inventaire, procédures de mouvement, checklists 
inventaireFabrication	Modes 
opératoires, instructions de production, contrôles qualité, consignes 
sécurité, procédures OFMaintenance	Procédures 
maintenance, checklists intervention, gammes maintenance, consignes sécuritéComptabilité	Procédures 
comptables, règles de clôture, contrôles internes, délégations financièresProjet	Méthodologies 
projet, modèles de reporting, procédures de suivi, tableaux de bord, 
matrices de risques  _____


15. Rattachement aux opérations Odoo


15.1 Principe attendu


Le système doit permettre d’afficher des documents applicables directement 
depuis les écrans opérationnels Odoo.

L’objectif est que l’utilisateur retrouve les consignes, règles, procédures 
et formulaires utiles au moment où il réalise une opération.

  _____


15.2 Exemples de rattachement opérationnel

Opération Odoo	Documents à afficherDemande 
d’achat	Procédures achats, délégations d’engagement, règles fournisseurs, 
seuils d’approbationBon 
de commande fournisseur	Procédure achat, conditions d’achat, matrice d’autorisation, 
modèle contractuelFiche 
fournisseur	Documents de conformité fournisseur, critères d’évaluation, 
questionnaire fournisseur, preuves réglementairesRéception 
fournisseur	Instructions de réception, checklist de contrôle, procédure de 
non-conformité, règles de stockageOpération 
de stock	Instructions de stockage, procédure d’inventaire, règles de 
transfert, consignes sécuritéInventaire	Procédure 
d’inventaire, checklist de comptage, règles d’écart, formulaire d’ajustementOrdre 
de fabrication	Modes opératoires, instructions qualité, instructions 
sécurité, checklists de démarrageContrôle 
qualité	Plan de contrôle, checklist qualité, procédure de traitement des 
non-conformitésIntervention 
maintenance	Procédure maintenance, checklist intervention, consignes 
sécurité, historique documentaire applicableDossier 
RH employé	Politiques RH, formulaires RH, procédures internes, règles 
applicables au posteDemande 
d’approbation	Procédure d’approbation, seuils de validation, délégations 
applicablesFacture 
fournisseur	Procédure de contrôle facture, règles de validation, délégations 
financièresProjet	Méthodologie 
projet, modèles de compte-rendu, matrice des risques, tableau de bord projet  _____


15.3 Règles d’affichage dans les opérations


Les documents affichés dans une opération doivent respecter :

1.	le module concerné ;
2.	l’opération concernée ;
3.	le poste de l’utilisateur ;
4.	son département ;
5.	son site ;
6.	son entité ;
7.	son pays ;
8.	son groupe de sécurité ;
9.	le statut Approuvé / Applicable ;
10.	la version active ;
11.	le niveau de confidentialité.

  _____


16. Notifications et alertes


16.1 Notifications attendues


Le système doit générer des notifications pour les événements suivants :

Événement	Destinataire	DéclencheurDocument 
à vérifier	Vérificateur	Passage en statut En vérificationDocument 
à approuver	Approbateur	Passage en statut En approbationDocument 
approuvé	Propriétaire, créateur, utilisateurs concernés si nécessairePassageen Approuvé / Applicable
Document proche de sa date de revue	Propriétaire, Administrateur GED	Date de 
revue procheDocument 
en retard de revue	Propriétaire, responsable métier, Administrateur GED	Date 
de revue dépasséeDocument 
obsolète	Propriétaire, administrateur, utilisateurs concernés si nécessairePassageen Obsolète / Archivé
Nouvelle version applicable	Utilisateurs concernés	Publication d’une 
nouvelle versionAccusé 
de lecture demandé	Utilisateurs ciblés	Document applicable avec lecture 
obligatoireAccusé 
de lecture en retard	Utilisateur, responsable métier	Délai de lecture 
dépasséDocument 
sans propriétaire	Administrateur GED	Contrôle périodiqueDocument 
sans métadonnée obligatoire	Administrateur GED	Contrôle qualité GED  _____


16.2 Supports de notification


Les notifications peuvent être réalisées via :

*	activité Odoo ;
*	email ;
*	notification interne Odoo ;
*	message dans le chatter Odoo ;
*	tableau de bord GED ;
*	action planifiée ;
*	relance automatique.

  _____


16.3 Règles de relance


Recommandations :

Cas	Relance recommandéeVérification 
non réalisée	Relance après 3 à 5 jours ouvrésApprobation 
non réalisée	Relance après 3 à 5 jours ouvrésAccusé 
de lecture non réalisé	Relance après 7 joursRevue 
documentaire proche	Alerte 30 jours avant échéanceRevue 
documentaire en retard	Relance immédiate puis hebdomadaireMétadonnées 
incomplètes	Alerte administrateur GED lors des contrôles  _____


17. Traçabilité et auditabilité


17.1 Éléments à historiser


Le système doit historiser autant que possible :

*	créateur ;
*	date de création ;
*	auteur des modifications ;
*	date des modifications ;
*	anciennes et nouvelles valeurs des champs critiques ;
*	versions ;
*	fichiers attachés ;
*	vérifications ;
*	approbations ;
*	refus ;
*	commentaires ;
*	dates d’approbation ;
*	dates d’application ;
*	consultations si possible ;
*	preuves de diffusion ;
*	accusés de lecture ;
*	archivage ;
*	date d’archivage ;
*	motif d’archivage ;
*	remplacement de version ;
*	suppression contrôlée ;
*	changement de droits ;
*	import initial ou import complémentaire.

  _____


17.2 Champs critiques à historiser en priorité


Les changements suivants doivent être traçables :

*	code document ;
*	titre document ;
*	version ;
*	statut ;
*	propriétaire ;
*	vérificateur ;
*	approbateur ;
*	criticité ;
*	confidentialité ;
*	droits d’accès ;
*	date d’application ;
*	date de revue ;
*	fichier attaché ;
*	version remplacée ;
*	version remplaçante.

  _____


17.3 Auditabilité attendue


L’auditeur habilité doit pouvoir répondre aux questions suivantes :

Question d’audit	Attendu dans OdooQuel 
document est applicable actuellement ?	Version active et statut Approuvé / 
ApplicableQui 
a approuvé le document ?	Historique d’approbationQuand 
le document a-t-il été approuvé ?	Date d’approbationQuelle 
version a été remplacée ?	Lien version précédenteQui 
a consulté ou accusé lecture ?	Historique de consultation ou accuséPourquoi 
le document est-il archivé ?	Motif d’archivageQuels 
documents arrivent à échéance de revue ?	Tableau de bord ou filtreQuels 
utilisateurs ont accès au document ?	Règles d’accès ou groupes associésQuels 
documents sont applicables à un poste ?	Matrice documents / postesQuels 
documents sont liés à une opération Odoo ?	Rattachement module / opération  _____


18. Import initial des documents


18.1 Source d’import


L’import initial doit être réalisé à partir du :

Registre maître documentaire V02

Ce registre contient les 26 colonnes documentaires de référence.

  _____


18.2 Données à importer


L’import doit couvrir :

*	les fiches documentaires ;
*	les 26 métadonnées principales ;
*	les fichiers liés ;
*	les sections ;
*	les lots ;
*	les familles documentaires ;
*	les domaines ;
*	les processus ;
*	les propriétaires ;
*	les vérificateurs ;
*	les approbateurs ;
*	les périmètres ;
*	les pays, entités et sites ;
*	les versions ;
*	les statuts ;
*	les liens Odoo si existants.

  _____


18.3 Contrôle de cohérence avant import


Avant import, le développeur / intégrateur doit prévoir un contrôle des 
éléments suivants :

Contrôle	Règle attendueID 
documentaire	Obligatoire et uniqueCode 
document	Obligatoire et uniqueTitre 
document	ObligatoireSection	Doit 
correspondre à une section validéeLot	Doit 
correspondre à un lot existant ou à créerType 
documentaire	Doit correspondre à une famille autoriséeVersion	Obligatoire
Statut	Doit correspondre à une valeur autoriséePropriétaire	Doit 
correspondre à un utilisateur, employé ou rôle identifiéVérificateur	Doit 
correspondre à un utilisateur, employé ou rôle identifié si applicableApprobateur	Doit 
correspondre à un utilisateur, employé ou rôle identifié si applicablePays 
/ entité	Doit correspondre aux référentiels OdooSite	Doit 
correspondre aux sites OdooDoublons	Rejet 
des doublons d’ID ou de codes documentsFichier 
lié	Vérifier l’existence du fichier lorsque le statut est applicableMétadonnées 
obligatoires	Rejet ou mise en quarantaine si manquantes  _____


18.4 Gestion des doublons


Le système doit rejeter ou signaler :

*	deux documents avec le même ID ;
*	deux documents avec le même code document ;
*	deux fichiers applicables avec le même code et la même version ;
*	deux versions actives du même document ;
*	un document sans version ;
*	un document applicable sans fichier.

  _____


18.5 Création automatique des métadonnées


L’import doit permettre de créer ou rattacher automatiquement :

*	sections ;
*	lots ;
*	familles documentaires ;
*	domaines ;
*	processus ;
*	entités ;
*	sites ;
*	pays ;
*	modules Odoo ;
*	postes si fournis ;
*	groupes métiers si fournis.

Toute création automatique doit être contrôlée afin d’éviter la 
multiplication de doublons dus à des différences d’orthographe.

  _____


18.6 Rattachement aux dossiers


Lors de l’import, chaque document doit être automatiquement rattaché à la 
bonne structure :

text
Système Documentaire V02

└── Section

    └── Lot

        └── Famille documentaire ou document




Le choix final entre classement physique par lot ou par famille doit être 
validé au cadrage. Les autres axes de classement doivent rester disponibles 
via les métadonnées et filtres.

  _____


19. Données de base à préparer


19.1 Fichiers et référentiels nécessaires


Avant paramétrage et import, les données suivantes doivent être préparées.

Donnée à préparer	Description	Responsable recommandéRegistre 
maître documentaire V02	Fichier Excel avec les 26 colonnes	Responsable 
Système DocumentaireListe 
des utilisateurs	Utilisateurs Odoo actifs	RH / ITListe 
des postes	Postes officiels	RHListe 
des départements	Départements de l’entreprise	RH / DirectionListe 
des directions	Directions fonctionnelles	Direction GénéraleListe 
des entités	Sociétés ou filiales	Direction / FinanceListe 
des sites	Sites opérationnels	Direction / OpérationsListe 
des pays	Pays d’application	DirectionListe 
des modules Odoo utilisés par poste	Correspondance poste / modules Odoo	IT / 
MétiersMatrice 
documents / postes	Documents applicables par poste	Responsable Système 
Documentaire / MétiersMatrice 
documents / modules Odoo	Documents applicables par module	Responsable 
Système Documentaire / IntégrateurMatrice 
droits d’accès	Droits par profil, poste, groupe, département	Direction / IT 
/ GEDListe 
des groupes de sécurité	Profils de droits Odoo	IT / IntégrateurListe 
des propriétaires documentaires	Responsables par document	Responsable 
Système DocumentaireListe 
des vérificateurs	Personnes en charge de la vérification	Direction / QualitéListe 
des approbateurs	Personnes habilitées à approuver	Direction Générale  _____


19.2 Recommandation sur la qualité des données


Avant import massif, les fichiers doivent être nettoyés :

*	suppression des doublons ;
*	harmonisation des libellés ;
*	validation des sections ;
*	validation des lots ;
*	validation des codes documents ;
*	validation des versions ;
*	validation des statuts ;
*	validation des noms utilisateurs ;
*	validation des sites et entités ;
*	validation des postes ;
*	validation des familles documentaires.

  _____


20. Livrables attendus du développeur Odoo


Le développeur / intégrateur Odoo devra fournir au minimum les livrables 
suivants.

Livrable	Description attendueProposition 
de solution technique	Description des modules utilisés, paramétrages, 
développements spécifiques éventuelsArchitecture 
GED	Structure des dossiers, métadonnées, règles de classementModèle 
de données	Objets, champs, relations, référentielsChamps 
personnalisés	Liste des champs créés avec types et règlesWorkflows	Statuts, 
transitions, validations, notificationsGroupes 
de sécurité	Groupes Odoo et profils documentairesRègles 
d’accès	Droits selon postes, départements, sites, entités, modulesÉcrans 
et menus	Menus GED, documents par poste, vues de recherche, tableaux de bordProcessus 
d’import	Méthode d’import Excel et fichiers liésTests 
de droits	Scénarios par profil utilisateurTests 
de workflow	Scénarios de création, vérification, approbation, révision et 
archivageDocumentation 
utilisateur	Guide d’utilisation pour les lecteurs, propriétaires, 
vérificateurs et approbateursDocumentation 
administrateur	Guide d’administration GEDPlan 
de déploiement	Méthode de mise en productionPlan 
de reprise ou correction	Mesures en cas d’erreur d’import ou de droits  _____


21. Critères d’acceptation


Le projet pourra être considéré conforme si les critères suivants sont 
remplis.


21.1 Critères liés aux documents

Critère	AttenduTous 
les documents du périmètre sont importés	Chaque document des sections 
validées est présent dans OdooChaque 
document possède ses métadonnées	Les 26 champs principaux sont renseignés ou 
justifiésChaque 
document a un statut	Aucun document sans statutChaque 
document a une version	Aucun document sans versionChaque 
document a un propriétaire	Responsable clairement identifiéChaque 
document applicable a un fichier	Aucun document applicable sans fichier 
attachéChaque 
document est classé	Section, lot et famille renseignés  _____


21.2 Critères liés aux droits

Critère	AttenduLes 
droits sont appliqués	Les accès respectent les profils définisUn 
utilisateur voit uniquement ses documents applicables	Le menu dédié 
fonctionne selon poste et périmètreLes 
documents obsolètes sont masqués	Les utilisateurs standards ne voient pas 
les archivesLes 
documents confidentiels sont protégés	Accès réservé aux groupes habilitésLes 
auditeurs voient les preuves autorisées	Accès audit maîtrisé  _____


21.3 Critères liés au workflow

Critère	AttenduLes 
statuts fonctionnent	Transitions conformes au workflowLes 
vérificateurs sont notifiés	Notification au passage en vérificationLes 
approbateurs sont notifiés	Notification au passage en approbationLes 
documents approuvés sont verrouillés	Pas de modification directe par 
utilisateur standardLes 
anciennes versions sont archivées	Une seule version active par documentLes 
refus ou corrections sont historisés	Commentaires et changements visibles  _____


21.4 Critères liés aux recherches et filtres

Critère	AttenduRecherche 
par code document	FonctionnelleRecherche 
par titre	FonctionnelleFiltre 
par section	FonctionnelFiltre 
par lot	FonctionnelFiltre 
par famille	FonctionnelFiltre 
par processus	FonctionnelFiltre 
par poste	FonctionnelFiltre 
par module Odoo	FonctionnelFiltre 
par statut	FonctionnelFiltre 
par version	Fonctionnel  _____


21.5 Critères liés à la traçabilité

Critère	AttenduLes 
approbations sont historisées	Date, acteur, décisionLes 
versions sont historisées	Ancienne et nouvelle versionLes 
archivages sont historisés	Date, motif, acteurLes 
accusés de lecture sont suivis	Utilisateur, date, documentLes 
preuves sont conservées	Documents ou historiques accessiblesLes 
imports sont traçables	Lot d’import identifié  _____


22. Phasage recommandé


22.1 Phase 1 — Cadrage


Objectifs :

*	valider le périmètre documentaire ;
*	confirmer les sections à intégrer ;
*	valider les 26 métadonnées ;
*	définir les champs complémentaires ;
*	valider les familles documentaires ;
*	valider les statuts ;
*	définir les rôles ;
*	valider la stratégie de droits ;
*	choisir le niveau de personnalisation Odoo.

Livrables :

*	compte rendu de cadrage ;
*	modèle de données cible ;
*	matrice des rôles ;
*	matrice des droits cible ;
*	plan projet détaillé.

  _____


22.2 Phase 2 — Préparation des données


Objectifs :

*	nettoyer le registre maître ;
*	préparer les fichiers documentaires ;
*	préparer les utilisateurs ;
*	préparer les postes ;
*	préparer les entités et sites ;
*	préparer les matrices documents / postes et documents / modules ;
*	contrôler les doublons.

Livrables :

*	registre nettoyé ;
*	fichiers prêts à importer ;
*	matrice documents / postes ;
*	matrice documents / modules ;
*	matrice droits d’accès ;
*	rapport de contrôle qualité des données.

  _____


22.3 Phase 3 — Paramétrage Odoo


Objectifs :

*	créer la structure GED ;
*	créer les champs ;
*	paramétrer les statuts ;
*	paramétrer les groupes ;
*	paramétrer les droits ;
*	créer les menus ;
*	configurer les notifications ;
*	préparer les vues et filtres.

Livrables :

*	environnement Odoo paramétré ;
*	vues GED ;
*	menu Documents applicables à mon poste ;
*	workflow documentaire ;
*	groupes de sécurité ;
*	règles d’accès.

  _____


22.4 Phase 4 — Import pilote


Objectifs :

*	sélectionner un lot pilote ;
*	importer un nombre limité de documents ;
*	tester les métadonnées ;
*	tester les fichiers ;
*	tester les droits ;
*	tester les filtres ;
*	tester le workflow.

Lot pilote recommandé :

*	Section 00 — Gouvernance documentaire ;
*	ID 001 à 020.

Livrables :

*	import pilote réalisé ;
*	rapport d’anomalies ;
*	corrections à effectuer ;
*	validation du modèle avant import massif.

  _____


22.5 Phase 5 — Tests et corrections


Objectifs :

*	tester avec plusieurs profils utilisateurs ;
*	vérifier les droits ;
*	vérifier les documents applicables par poste ;
*	vérifier les documents par module ;
*	vérifier les notifications ;
*	tester la révision et l’archivage ;
*	corriger les anomalies.

Profils de test recommandés :

*	Direction Générale ;
*	Responsable Qualité ;
*	Responsable Achats ;
*	Utilisateur standard ;
*	Auditeur interne ;
*	Administrateur GED.

Livrables :

*	cahier de tests ;
*	résultats des tests ;
*	liste des anomalies ;
*	corrections validées ;
*	accord pour déploiement.

  _____


22.6 Phase 6 — Déploiement général


Objectifs :

*	importer l’ensemble du périmètre validé ;
*	appliquer les droits définitifs ;
*	activer les menus ;
*	activer les notifications ;
*	communiquer aux utilisateurs ;
*	mettre en production.

Livrables :

*	GED en production ;
*	registre importé ;
*	droits appliqués ;
*	rapport de déploiement ;
*	validation finale.

  _____


22.7 Phase 7 — Formation et transfert de compétence


Objectifs :

*	former les administrateurs GED ;
*	former les propriétaires documentaires ;
*	former les vérificateurs ;
*	former les approbateurs ;
*	former les utilisateurs lecteurs ;
*	transférer la documentation d’administration.

Livrables :

*	support de formation utilisateur ;
*	support de formation administrateur ;
*	guide de consultation ;
*	guide de gestion documentaire ;
*	guide de workflow ;
*	procès-verbal de transfert de compétence.

  _____


23. Risques du projet

Risque	Impact potentiel	Mesure de maîtrise recommandéeMauvaise 
qualité du registre Excel	Erreurs d’import, doublons, métadonnées 
incorrectes	Nettoyage et contrôle avant importAbsence 
de matrice documents / postes	Menu par poste non fiable	Préparer et valider 
la matrice avec les métiersDroits 
trop larges	Accès non autorisé à des documents sensibles	Validation 
préalable de la matrice de droitsDroits 
trop restrictifs	Utilisateurs bloqués dans leurs opérations	Tests par 
profils et ajustementsDoublons 
de documents	Confusion entre versions ou codes	Rejet automatique des 
doublonsAbsence 
de responsable documentaire	Documents non maintenus	Nommer un propriétaire 
pour chaque documentVersions 
obsolètes utilisées	Non-conformité opérationnelle	Masquer les obsolètes et 
identifier la version activeComplexité 
excessive du workflow	Faible adoption utilisateur	Commencer avec un workflow 
simple et robusteManque 
de formation utilisateur	Mauvais usage de la GED	Former par profil 
utilisateurMétadonnées 
trop nombreuses ou mal renseignées	Recherche inefficace	Définir champs 
obligatoires et contrôles qualitéAbsence 
de gouvernance post-déploiement	Dégradation du système dans le temps	Nommer 
un administrateur GED et planifier des revuesMauvais 
rattachement aux modules Odoo	Documents non visibles au bon moment	Tester 
avec opérations métier réellesAbsence 
de gestion des archives	Documents obsolètes encore utilisés	Mettre en place 
statut Obsolète / ArchivéNotifications 
mal paramétrées	Retards de validation ou surcharge d’alertes	Définir règles 
et seuils de relance  _____


24. Recommandations finales


24.1 Recommandations de mise en œuvre


Il est recommandé de :

1.	commencer par un lot pilote ;
2.	utiliser la Section 00 — Gouvernance documentaire comme périmètre de test 
;
3.	tester avec 3 à 5 profils utilisateurs représentatifs ;
4.	valider la structure des métadonnées avant import massif ;
5.	valider la matrice des droits avant ouverture aux utilisateurs ;
6.	ne pas donner accès à tous les documents à tous les utilisateurs ;
7.	verrouiller les documents approuvés ;
8.	masquer les documents obsolètes aux utilisateurs standards ;
9.	archiver les versions remplacées ;
10.	historiser les approbations ;
11.	maintenir la numérotation documentaire globale ;
12.	nommer un administrateur GED ;
13.	prévoir une revue périodique de la qualité des données ;
14.	documenter les règles de création et de modification ;
15.	éviter les développements spécifiques inutiles si le paramétrage 
standard Odoo suffit.

  _____


24.2 Recommandations pour le développeur / intégrateur Odoo


Le développeur ou intégrateur devra porter une attention particulière à :

*	la robustesse du modèle de données ;
*	la simplicité d’utilisation ;
*	la performance des filtres ;
*	la cohérence des droits ;
*	la non-duplication des documents ;
*	la gestion des versions ;
*	la traçabilité des actions ;
*	la capacité d’import et de mise à jour en masse ;
*	la facilité d’administration ;
*	la compatibilité avec les modules Odoo existants ;
*	la possibilité d’évolution progressive.

  _____


24.3 Principe directeur


La GED Odoo doit être conçue comme un outil opérationnel quotidien.

Elle doit permettre à chaque utilisateur de répondre simplement à la 
question suivante :

Quels documents dois-je appliquer pour mon poste, mon site, mon entité et l’opération 
Odoo que je suis en train de réaliser ?

  _____


25. Annexes

  _____


Annexe 1 — Modèle de tableau des métadonnées documentaires

ID	Section	Lot	Code document	Titre document	Type	Niveau	Domaine principalDomainesecondaire	Processus principal	Propriétaire	Version	Statut
001	00 — Gouvernance documentaire	Lot 00 — Gouvernance documentaireKAE-DOC-POL-001-V01	Politiquede gouvernance documentaire Groupe	POL	Niveau 1	DOC	GOV / QMS	Gouvernance 
documentaire	Responsable Gouvernance documentaire	V01	Approuvé / Applicable  _____


Annexe 2 — Modèle de matrice documents / postes

Code document	Titre document	Poste 1	Poste 2	Poste 3	Département	Site	EntitéObligatoire	Accuséde lecture requis
KAE-DOC-PRC-003-V01	Procédure de publication, diffusion et accès 
documentaire	Oui	Oui	Non	Qualité	Tous sites	Toutes entités	Oui	Oui  _____


Annexe 3 — Modèle de matrice documents / modules Odoo

Code document	Titre document	Module Odoo	Opération Odoo	Affichage 
automatique	Condition d’affichage	Statut requisKAE-ACH-PRC-001-V01	Procédure 
achats	Achats	Demande d’achat	Oui	Utilisateur du département Achats	Approuvé 
/ ApplicableKAE-STK-INS-001-V01	Instruction 
de réception stock	Inventaire	Réception fournisseur	Oui	Site logistique 
concerné	Approuvé / Applicable  _____


Annexe 4 — Modèle de matrice des droits

Profil / groupe	Lecture	Création	Modification brouillon	VérificationApprobation	Archivage	Accèsarchives	Administration
Direction Générale	Oui	Non	Non	Possible	Oui	Possible	Oui	NonResponsable 
métier	Oui	Oui	Oui	Possible	Possible	Non	Limité	NonUtilisateur 
standard	Oui selon poste	Non	Non	Non	Non	Non	Non	NonAuditeur 
interne	Oui selon mission	Non	Non	Non	Non	Non	Oui	NonAdministrateur 
GED	Oui	Oui	Oui	Non métier	Non métier	Oui	Oui	Oui  _____


Annexe 5 — Modèle de tableau des statuts

Statut	Description	Modification autorisée	Visible utilisateur standardNotification	Statutfinal
À créer	Document identifié mais non rédigé	Oui	Non	Non	NonBrouillon	Document 
en rédaction	Oui	Non	Non	NonEn 
vérification	Document soumis à contrôle	Non sauf retour	Non	Oui au 
vérificateur	NonEn 
approbation	Document soumis à approbation	Non sauf retour	Non	Oui à l’approbateur	NonApprouvé / Applicable	Document validé et en vigueur	Non	Oui selon droits	Oui 
si requis	NonEn 
révision	Document en mise à jour	Oui version de travail	Version active 
visible uniquement	Oui au propriétaire	NonObsolète 
/ Archivé	Document retiré ou remplacé	Non	Non	Oui si requis	Oui  _____


Annexe 6 — Modèle de tableau des familles documentaires

Code	Famille	Utilisation	ExemplePOL	Politique	Orientation 
générale	Politique documentaireMAN	Manuel	Description 
globale d’un système	Manuel GEDREF	Référentiel	Cadre 
ou exigences de référence	Référentiel contrôle internePRO	Processus	Macro-processus	Processus 
documentairePRC	Procédure	Règles 
détaillées	Procédure de diffusionINS	Instruction	Consignes 
opérationnelles	Instruction de nommageFOR	Formulaire	Collecte 
d’informations	Formulaire de demandeREG	Registre	Suivi 
officiel	Registre maîtreMOD	Modèle	Trame 
standard	Modèle de procédureRAP	Rapport	Restitution 
ou analyse	Rapport d’auditPRE	Preuve	Élément 
de justification	Preuve d’approbationMAT	Matrice	Tableau 
de correspondance	Matrice RACITDB	Tableau 
de bord	Pilotage	Tableau de bord documentaireCHK	Checklist	Liste 
de contrôle	Checklist de conformitéAUT	Autre	Usage 
exceptionnel	À préciser  _____


Annexe 7 — Modèle de tableau des notifications

Notification	Déclencheur	Destinataire	Délai	Relance	CanalDocument 
à vérifier	Passage en vérification	Vérificateur	Immédiat	3 à 5 jours	Odoo / 
emailDocument 
à approuver	Passage en approbation	Approbateur	Immédiat	3 à 5 jours	Odoo / 
emailDocument 
approuvé	Approbation validée	Propriétaire / utilisateurs ciblés	Immédiat	NonOdooRevue proche	Date de revue à 30 jours	Propriétaire	30 jours avant 
Hebdomadaire	Odoo / emailRevue 
en retard	Date de revue dépassée	Propriétaire / Administrateur GED	ImmédiatHebdomadaire	Odoo/ email
Nouvelle version applicable	Publication nouvelle version	Utilisateurs 
concernés	Immédiat	Si accusé requis	OdooAccusé 
de lecture demandé	Document applicable avec lecture obligatoire	Utilisateurs 
ciblés	Immédiat	7 jours	Odoo / emailDocument 
archivé	Passage en archive	Propriétaire / Administrateur GED	Immédiat	NonOdoo  _____


Annexe 8 — Modèle de fiche document Odoo cible

Rubrique	Champs affichésIdentification	ID, 
code document, titre, type, niveau, version, statutClassement	Section, 
lot, famille, domaine principal, domaine secondaireProcessus	Processus 
principal, processus secondaire, module Odoo, opération OdooResponsabilités	Propriétaire, 
vérificateur, approbateurApplication	Périmètre, 
pays, entités, sites, postes, départementsSécurité	Confidentialité, 
groupes autorisés, droits spécifiquesCycle 
de vie	Date création, date application, date prochaine revue, date archivageVersioning	Version 
active, remplace, remplacé par, historiquePreuves	Approbation, 
diffusion, accusés lecture, archivageFichier	Document 
attaché, format, lien de consultationCommentaires	Commentaire 
V02, motif d’archivage, notes internes  _____


Annexe 9 — Modèle de contrôle avant import

Contrôle	Résultat attendu	StatutTous 
les ID sont uniques	Aucun doublon	À contrôlerTous 
les codes documents sont uniques	Aucun doublon	À contrôlerToutes 
les sections sont valides	Sections 00 à 04 uniquement dans le périmètre 
actuel	À contrôlerTous 
les documents ont une version	Version renseignée	À contrôlerTous 
les documents ont un statut	Statut autorisé	À contrôlerTous 
les documents applicables ont un fichier	Fichier disponible	À contrôlerTous 
les propriétaires sont identifiés	Utilisateur ou rôle existant	À contrôlerLes 
familles documentaires sont valides	Code famille autorisé	À contrôlerLes 
sites et entités sont harmonisés	Libellés cohérents	À contrôlerLes 
documents obsolètes sont identifiés	Statut clair	À contrôler  _____


Annexe 10 — Synthèse des décisions à valider au cadrage

Décision à valider	Options possibles	Décision retenueClassement 
principal dans Odoo	Par section / lot ou par famille	À déciderVersioning	V01, 
V02 ou V01.1, V01.2	À déciderAccusé 
de lecture	Obligatoire pour tous ou selon criticité	À déciderConsultation 
historisée	Oui / Non / selon documents critiques	À déciderWorkflow	Simple 
ou avancé multi-approbateurs	À déciderDroits	Par 
poste uniquement ou multi-critères	Multi-critères recommandéArchives	Masquées 
ou visibles avec avertissement	Masquées aux utilisateurs standardsImport 
initial	Section 00 pilote puis généralisation	RecommandéDocuments 
confidentiels	Groupes dédiés ou droits nominatifs	À déciderRattachement 
aux opérations	Par module seulement ou opération détaillée	Opération 
détaillée recommandée à terme  _____