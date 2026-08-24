<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Catalogue des fournisseurs LLM
    |--------------------------------------------------------------------------
    | Chaque entrée définit : label (UI), base_url (endpoint API), driver
    | (openai = natif OpenAI, anthropic = natif Claude, gemini = natif Google,
    | openai_compat = compatible OpenAI — 1 seul adaptateur pour tous),
    | default_model (modèle proposé par défaut).
    | Les clés API sont stockées chiffrées (plateforme héritées + surcharge tenant).
    */
    'providers' => [
        'openai' => [
            'label' => 'OpenAI',
            'base_url' => 'https://api.openai.com/v1',
            'driver' => 'openai',
            'default_model' => 'gpt-4o-mini',
        ],
        'anthropic' => [
            'label' => 'Anthropic Claude',
            'base_url' => 'https://api.anthropic.com/v1',
            'driver' => 'anthropic',
            'default_model' => 'claude-3-5-haiku',
        ],
        'gemini' => [
            'label' => 'Google Gemini',
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'driver' => 'gemini',
            'default_model' => 'gemini-2.0-flash',
        ],
        'groq' => [
            'label' => 'Groq',
            'base_url' => 'https://api.groq.com/openai/v1',
            'driver' => 'openai_compat',
            'default_model' => 'llama-3.3-70b-versatile',
        ],
        'deepseek' => [
            'label' => 'DeepSeek',
            'base_url' => 'https://api.deepseek.com/v1',
            'driver' => 'openai_compat',
            'default_model' => 'deepseek-chat',
        ],
        'mistral' => [
            'label' => 'Mistral AI',
            'base_url' => 'https://api.mistral.ai/v1',
            'driver' => 'openai_compat',
            'default_model' => 'mistral-large-latest',
        ],
        'openrouter' => [
            'label' => 'OpenRouter',
            'base_url' => 'https://openrouter.ai/api/v1',
            'driver' => 'openai_compat',
            'default_model' => 'openai/gpt-4o-mini',
        ],
        'ollama' => [
            'label' => 'Ollama (local)',
            'base_url' => 'http://localhost:11434/v1',
            'driver' => 'openai_compat',
            'default_model' => 'llama3',
        ],
        'xai' => [
            'label' => 'xAI (Grok)',
            'base_url' => 'https://api.x.ai/v1',
            'driver' => 'openai_compat',
            'default_model' => 'grok-2-latest',
        ],
        'azure' => [
            'label' => 'Azure OpenAI',
            'base_url' => 'https://YOUR_RESOURCE.openai.azure.com/openai/deployments/YOUR_DEPLOYMENT',
            'driver' => 'azure',
            'default_model' => 'gpt-4o-mini',
        ],
        'fireworks' => [
            'label' => 'Fireworks AI',
            'base_url' => 'https://api.fireworks.ai/inference/v1',
            'driver' => 'openai_compat',
            'default_model' => 'accounts/fireworks/models/llama-v3p3-70b-instruct',
        ],
        'together' => [
            'label' => 'Together AI',
            'base_url' => 'https://api.together.xyz/v1',
            'driver' => 'openai_compat',
            'default_model' => 'meta-llama/Llama-3.3-70B-Instruct-Turbo',
        ],
    ],

    /*
    | Fournisseur personnalisé (optionnel) : n'importe quel service compatible OpenAI.
    | Renseigné par le superadmin dans l'UI (nom + base_url + clé + modèle).
    */
    'custom' => null,
];
