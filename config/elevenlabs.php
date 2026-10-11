<?php

return [
    /*
    |--------------------------------------------------------------------------
    | ElevenLabs API Base URL
    |--------------------------------------------------------------------------
    */
    'base_url' => env('ELEVENLABS_BASE_URL', 'https://api.elevenlabs.io'),

    /*
    |--------------------------------------------------------------------------
    | ElevenLabs API Keys Pool
    |--------------------------------------------------------------------------
    | Comma-separated list of API keys in .env
    */
    'keys' => array_filter(
        array_map('trim', explode(',', env('ELEVENLABS_API_KEYS', '')))
    ),

    /*
    |--------------------------------------------------------------------------
    | Configurações Padrão de Voz e Modelo
    |--------------------------------------------------------------------------
    */
    'default_voice_id' => env('ELEVENLABS_DEFAULT_VOICE_ID', 'eUAnqvLQWNX29twcYLUM'),
    'default_model_id' => env('ELEVENLABS_DEFAULT_MODEL_ID', 'eleven_multilingual_v2'),

    /*
    |--------------------------------------------------------------------------
    | Configurações de Rotação e Cooldown
    |--------------------------------------------------------------------------
    */
    'cooldown_minutes' => (int) env('ELEVENLABS_COOLDOWN_MINUTES', 30),
    'timeout' => (int) env('ELEVENLABS_REQUEST_TIMEOUT', 60),
];
