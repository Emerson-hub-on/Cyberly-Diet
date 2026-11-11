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

    /*
    | Intensidade (MET) de cada atividade. Calorias = MET × peso (kg) × horas.
    | Valores de referência do Compendium of Physical Activities (intensidade moderada).
    */
    'activities' => [
        'Academia' => 5.0,
        'Musculação' => 5.0,
        'Caminhada' => 3.5,
        'Caminhada rápida' => 4.3,
        'Corrida' => 9.8,
        'Bicicleta' => 7.5,
        'Bicicleta ergométrica' => 6.8,
        'Natação' => 7.0,
        'Funcional' => 8.0,
        'Crossfit' => 8.0,
        'HIIT' => 8.0,
        'Futebol' => 7.0,
        'Dança' => 5.0,
        'Pilates' => 3.0,
        'Yoga' => 2.5,
        'Alongamento' => 2.3,
        'Escada / Step' => 8.8,
        'Elíptico' => 5.0,
    ],
    'default_met' => 4.0,

    // ≈ 7.700 kcal de déficit para perder 1 kg de gordura corporal
    'kcal_per_kg' => 7700,

    'defaults' => [
        'calorie_goal' => 2000,
        'water_goal_ml' => 2000,
    ],
];
