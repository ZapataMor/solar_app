<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ambient Weather API Configuration
    |--------------------------------------------------------------------------
    |
    | Credentials and settings for the Ambient Weather REST API v1.
    | Docs: https://ambientweather.docs.apiary.io/
    |
    | AMBIENT_API_KEY        — Per-user API key (from ambientweather.net)
    | AMBIENT_APPLICATION_KEY — Application key registered for your app
    | AMBIENT_ENABLED        — Master switch; set false to skip all API calls
    | AMBIENT_REQUEST_TIMEOUT— HTTP timeout in seconds
    | AMBIENT_CACHE_MINUTES  — TTL for device-list and latest-data cache
    |
    */

    'api_key' => env('AMBIENT_API_KEY'),

    'application_key' => env('AMBIENT_APPLICATION_KEY'),

    'enabled' => (bool) env('AMBIENT_ENABLED', true),

    'request_timeout' => (int) env('AMBIENT_REQUEST_TIMEOUT', 20),

    'cache_minutes' => (int) env('AMBIENT_CACHE_MINUTES', 10),

    'base_url' => env('AMBIENT_BASE_URL', 'https://api.ambientweather.net/v1'),

    /*
    |--------------------------------------------------------------------------
    | Freshness Threshold
    |--------------------------------------------------------------------------
    |
    | A station is considered "online" if its last reading is no older than
    | this many minutes. Used by ClimateSourceFallbackService to determine
    | the active climate source shown on the dashboard.
    |
    */

    'online_threshold_minutes' => (int) env('AMBIENT_ONLINE_THRESHOLD_MINUTES', 30),

];
