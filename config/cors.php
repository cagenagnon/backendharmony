<?php

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

    'allowed_origins' => [
        'http://localhost:3000',
        'http://localhost:5173',
        'harmonybackend-production.up.railway.app',
        'https://harmonybymdn.netlify.app',
    ],

    // Matches any Netlify domain: yourapp.netlify.app or custom domains.
    // Add your exact Netlify URL via the FRONTEND_URL Railway variable.
    'allowed_origins_patterns' => array_filter([
        '#^https://[\w-]+\.netlify\.app$#',
        env('FRONTEND_URL') ? '#^' . preg_quote(env('FRONTEND_URL'), '#') . '$#' : null,
    ]),

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
