<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SSO base URL
    |--------------------------------------------------------------------------
    |
    | Базовый URL для получения auth_token через SSO API.
    |
    */
    'sso_base' => env('TRENDAGENT_SSO_BASE', 'https://sso-api.trend.tech'),

    /*
    |--------------------------------------------------------------------------
    | Application id for SSO
    |--------------------------------------------------------------------------
    |
    | Идентификатор клиентского приложения, который передаётся в SSO при логине.
    |
    */
    'app_id' => env('TRENDAGENT_APP_ID'),

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    */
    'default_city_id' => env('TRENDAGENT_DEFAULT_CITY_ID'),
    'default_lang' => env('TRENDAGENT_DEFAULT_LANG', 'ru'),

    /*
    |--------------------------------------------------------------------------
    | API domains
    |--------------------------------------------------------------------------
    |
    | Основные домены API TrendAgent. Используются вызывающим кодом для
    | построения полных URL.
    |
    */
    'api' => [
        'core'       => env('TRENDAGENT_API_CORE', 'https://api.trendagent.ru'),
        'apartment'  => env('TRENDAGENT_API_APARTMENT', 'https://apartment-api.trendagent.ru'),
        'parkings'   => env('TRENDAGENT_API_PARKINGS', 'https://parkings-api.trendagent.ru'),
        'commerce'   => env('TRENDAGENT_API_COMMERCE', 'https://commerce-api.trendagent.ru'),
        'rewards'    => env('TRENDAGENT_API_REWARDS', 'https://rewards-api.trendagent.ru'),
    ],
];

