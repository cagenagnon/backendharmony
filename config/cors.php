<?php

$allowedOrigins = [
    'http://localhost:3000',
    'http://localhost:5173',
    'https://harmonybymdn.netlify.app',
    'https://harmonyby-mdn.netlify.app',
];

if ($customOrigins = env('CORS_ALLOWED_ORIGINS')) {
    $allowedOrigins = array_merge(
        $allowedOrigins,
        array_filter(array_map('trim', explode(',', (string) $customOrigins)))
    );
}

if ($frontendUrl = env('FRONTEND_URL')) {
    $allowedOrigins[] = trim($frontendUrl);
}

$allowedOrigins = array_values(array_unique(array_filter($allowedOrigins)));

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowedOrigins,

    // Matches any Netlify domain: yourapp.netlify.app or custom domains.
    // Add your exact Netlify URL via the FRONTEND_URL Railway variable.
    'allowed_origins_patterns' => array_filter([
        '#^https://[\w-]+\.netlify\.app$#',
        '#^https://[\w-]+\.netlify\.com$#',
        env('FRONTEND_URL') ? '#^' . preg_quote(env('FRONTEND_URL'), '#') . '$#' : null,
    ]),

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
