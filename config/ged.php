<?php

return [
    /*
    | Storage abstraction (CDG §34): 'local' for the cPanel/MVP,
    | 's3' once a provider is chosen. Controllers only use StorageService.
    */
    'storage_disk' => env('GED_STORAGE_DISK', 'local'),

    /*
    | AI provider: 'mock' by default. Add adapters without touching business logic.
    | (CDG §35: provider must never be coded directly into business logic.)
    */
    'ai_provider' => env('GED_AI_PROVIDER', 'mock'),

    'ocr_provider' => env('GED_OCR_PROVIDER', 'mock'),

    'office_provider' => env('GED_OFFICE_PROVIDER', 'fallback'),

    /*
    | Mapping MIME → éditeur en ligne (étape 1).
    | 'embedded' : éditeur texte/markdown dans le navigateur
    | 'pdf'      : visualisation + annotations PDF (pdf.js + pdf-lib)
    | 'fallback' : téléchargement → réimport (CA-019) — à remplacer par
    |              'onlyoffice' une fois un Document Server déployé (VPS).
    */
    'editors' => [
        'text/plain' => 'embedded',
        'text/markdown' => 'embedded',
        'text/csv' => 'embedded',
        'application/json' => 'embedded',
        'text/html' => 'embedded',
        'application/pdf' => 'pdf',
        'application/msword' => 'fallback',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'fallback',
        'application/vnd.ms-excel' => 'fallback',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'fallback',
        'application/vnd.ms-powerpoint' => 'fallback',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'fallback',
        'image/jpeg' => 'viewer',
        'image/png' => 'viewer',
        'image/tiff' => 'viewer',
    ],

    /*
    | Mapping MIME → viewer JS (aperçu en ligne, jamais d'URL publique — RM-012).
    */
    'viewers' => [
        'application/pdf' => 'pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'text/markdown' => 'markdown',
        'text/plain' => 'text',
        'text/csv' => 'text',
        'application/json' => 'text',
        'text/html' => 'text',
        'image/jpeg' => 'image',
        'image/png' => 'image',
        'image/tiff' => 'image',
    ],

    'rag_enabled' => env('GED_RAG_ENABLED', true),

    'sso_enabled' => env('GED_SSO_ENABLED', false),

    /*
    | Backup (CDG §41): paths to the MySQL binaries used for SQL dumps.
    | When unavailable, a portable JSON export is used instead.
    */
    'mysqldump_path' => env('GED_MYSQLDUMP_PATH', 'mysqldump'),
    'mysql_path' => env('GED_MYSQL_PATH', 'mysql'),

    'pagination' => 20,
];
