<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Internal API key (X-Internal-Key header)
    |--------------------------------------------------------------------------
    | When set, POST /api/ta/blocks/{id}/refresh and .../apartments/{id}/refresh
    | require this header. When empty, those routes return 403.
    */
    'api_key' => env('INTERNAL_API_KEY', ''),
];
