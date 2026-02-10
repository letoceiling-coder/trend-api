<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SSO API base URL
    |--------------------------------------------------------------------------
    */
    'sso_base' => env('TRENDAGENT_SSO_BASE', 'https://sso-api.trend.tech'),

    /*
    |--------------------------------------------------------------------------
    | SSO web base (login page, Origin/Referer)
    |--------------------------------------------------------------------------
    */
    'sso_web_base' => env('TRENDAGENT_SSO_WEB_BASE', 'https://sso.trend.tech'),

    /*
    |--------------------------------------------------------------------------
    | SSL verify (set false only for local dev if needed)
    |--------------------------------------------------------------------------
    */
    'sso_verify' => filter_var(env('TRENDAGENT_SSO_VERIFY', true), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | User-Agent for SSO requests (browser-like)
    |--------------------------------------------------------------------------
    */
    'user_agent' => env('TRENDAGENT_USER_AGENT', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'),

    /*
    |--------------------------------------------------------------------------
    | Application id for SSO (fallback if not extracted from login page)
    |--------------------------------------------------------------------------
    */
    'app_id' => env('TRENDAGENT_APP_ID'),

    /*
    |--------------------------------------------------------------------------
    | Alternative app_id (e.g. from AL project) — used for retry on 403
    |--------------------------------------------------------------------------
    */
    'app_id_alternative' => env('TRENDAGENT_APP_ID_ALTERNATIVE', '66d84f584c0168b8ccd281c3'),

    /*
    |--------------------------------------------------------------------------
    | Regex to extract app_id from HTML (first capture group = app_id)
    |--------------------------------------------------------------------------
    */
    'app_id_regex' => [
        '/app_id["\']?\s*[:=]\s*["\']([a-f0-9]{24})["\']/i',
        '/["\']([a-f0-9]{24})["\'].*app_id/i',
        '/login\?app_id=([a-f0-9]{24})/i',
        '/app_id=([a-f0-9]{24})/i',
    ],

    /*
    |--------------------------------------------------------------------------
    | Defaults (optional; can be overridden by command options)
    |--------------------------------------------------------------------------
    */
    'default_city_id' => env('TRENDAGENT_DEFAULT_CITY_ID'),
    'default_lang' => env('TRENDAGENT_DEFAULT_LANG', 'ru'),
    'default_phone' => env('TRENDAGENT_DEFAULT_PHONE'),
    'default_password' => env('TRENDAGENT_DEFAULT_PASSWORD'),

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

