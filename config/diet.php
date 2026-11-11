<?php

return [

    /*
    | Disco onde as fotos dos pratos são guardadas.
    | Local: "local" (storage/app/private). Vercel: "s3" apontando para o Supabase Storage.
    */
    'photos_disk' => env('MEAL_PHOTOS_DISK', 'local'),

    /*
    | Permite criar novas contas pela tela de cadastro.
    | Mesmo desligado, o primeiro usuário sempre consegue se cadastrar.
    */
    'allow_registration' => (bool) env('ALLOW_REGISTRATION', false),

    /*
    | IA usada para analisar as fotos: "gemini" (nível gratuito do Google) ou "anthropic" (Claude, pago).
    */
    'ai_provider' => env('AI_PROVIDER', 'gemini'),

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        // Tentados em ordem: se um estiver sem cota gratuita, usa o próximo
        'models' => array_values(array_filter(array_map('trim', explode(',', env('GEMINI_MODELS', 'gemini-3.8-flash,gemini-3.5-flash-lite,gemini-2.5-flash'))))),
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-opus-5-5'),
        // low | medium | high — profundidade do raciocínio na análise da foto
        'effort' => env('ANTHROPIC_EFFORT', 'medium'),
    ],

    'meal_types' => [
        'cafe_da_manha' => 'Café da manhã',
        'lanche' => 'Lanche',
        'almoco' => 'Almoço',
        'jantar' => 'Jantar',
    ],

    'defaults' => [
        'calorie_goal' => 2000,
        'water_goal_ml' => 2000,
    ],
];
