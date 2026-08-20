# Viewers & éditeurs en ligne

## Vue d'ensemble

Chaque format accepté à l'upload a désormais un **viewer** (aperçu en ligne) et un
**éditeur** définis par le mapping `config/ged.php` (`editors` / `viewers`),
résolu par `App\Services\EditorRegistry`.

| Format | Viewer (aperçu) | Éditeur en ligne |
|--------|----------------|------------------|
| TXT / CSV / JSON / HTML | Texte brut (`<pre>`) | **Embarqué** (CodeMirror) |
| MD | Rendu markdown (marked.js + DOMPurify) | **Embarqué** (éditeur + aperçu divisé) |
| PDF | pdf.js (pages rendues) | **Annotation** (pdf.js + pdf-lib : texte, surlignage, note) |
| DOCX | docx-preview (rendu HTML) | Fallback (téléchargement → réimport) |
| XLSX | SheetJS (tableau HTML, onglets de feuilles) | Fallback |
| PPTX | pptxjs (diapositives) | Fallback |
| JPG / PNG / TIFF | Image | — (hors périmètre) |

L'édition Office (docx/xlsx/pptx) en ligne nécessite un serveur OnlyOffice/Collabora
(VPS — CDG §39) ; le mapping `editors` permet de passer ces MIME de `fallback` à
`onlyoffice` sans autre changement.

## Flux d'édition (inchangé pour la sécurité)

1. `GET /office/{document}/start` → `OfficeService::startEdit()` :
   vérifie `documents.edit`, **verrouille** la version (check-out), crée une session
   avec jeton temporaire hashé (expiration 60 min).
2. Redirection selon le mapping : `office.embedded/{token}`, `office.pdf/{token}`,
   ou `office.download/{token}` (fallback).
3. Enregistrement → **nouvelle version** (RM-005 : l'historique n'est jamais écrasé),
   verrou libéré, session close, audit `office.session.returned`.
   - Embarqué : `POST /office/embedded/save/{token}` (contenu texte) →
     `OfficeService::saveEmbedded()` (fichier temporaire → pipeline d'upload standard).
   - PDF : `POST /office/pdf/save/{token}` (fichier multipart) →
     `OfficeService::returnFile()`.
4. Un autre utilisateur ne peut pas enregistrer avec un jeton qui ne lui appartient pas
   (403) ; un jeton inconnu/expiré → 404 (testé).

## Viewers (consultation seule)

- Page plein écran : `GET /office/viewer/{document}` (permission `documents.preview`
  ou `documents.edit`), intégrée dans l'onglet **Aperçu** de la fiche document (iframe).
- Contenu streamé par `GET /documents/{document}/versions/{version}/content` :
  route authentifiée, permission vérifiée côté serveur, **jamais d'URL publique** (RM-012).
- Rendus HTML (markdown, XLSX) passés par **DOMPurify** (anti-XSS, SEC-006).

## Anti-interception des PDF (IDM et autres gestionnaires de téléchargement)

Le streaming `preview-content` sert les PDF préfixés de **64 espaces** avant `%PDF` :
la spécification PDF autorise jusqu'à 1 024 octets d'en-tête avant `%PDF`, ce que
pdf.js et pdf-lib gèrent — mais les gestionnaires de téléchargement (ex. Internet
Download Manager) qui détectent `%PDF` à l'offset 0 n'interceptent plus la requête
(ils répondaient par un 204 vide → « The PDF file is empty »). Le client retire le
padding avant l'édition pdf-lib. Le vrai MIME reste servi sur `documents.download`.

Si l'interception persiste, le message d'erreur affiché indique la marche à suivre :
bouton flottant IDM → « Disable IDM for this site » (ou Options IDM → Intégration
avancée désactivée pour le domaine).

## Bibliothèques (CDN, cohérent avec Bootstrap)

| Bibliothèque | Usage |
|--------------|-------|
| CodeMirror 5 | Éditeur de texte embarqué (modes plain/xml/json) |
| marked + DOMPurify | Rendu markdown sécurisé |
| pdfjs-dist 3.11 | Rendu PDF (viewer + éditeur d'annotations) |
| pdf-lib 1.17 | Génération du PDF annoté (texte, surlignage, notes) |
| docx-preview 0.3 | Rendu DOCX |
| SheetJS (xlsx 0.18) | Rendu XLSX (onglets de feuilles) |
| pptxjs 1.2 (+ jquery/jszip/filesaver) | Rendu PPTX (qualité moyenne, fallback si échec) |

## Tests

- `tests/Unit/EditorRegistryTest.php` : mapping MIME → éditeur/viewer, acceptation markdown.
- `tests/Feature/OfficeEditTest.php` : redirection vers le bon éditeur, enregistrement
  embarqué (nouvelle version + verrou + audit), jeton invalide/étranger refusé,
  permission `preview` exigée pour le streaming, page viewer accessible, fallback Office.

## Évolutions

- OnlyOffice : ajouter le MIME dans `editors` → `onlyoffice`, implémenter la vue
  iframe + callback JWT (voir `docs/deploiement.md` — VPS).
- Antivirus de contenu, coédition temps réel : dépendent des fournisseurs validés (CDG §55).
