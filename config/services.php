<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'nasa_power' => [
        'verify_ssl' => env('NASA_POWER_VERIFY_SSL', false),
    ],

    'weather_station' => [
        'endpoint' => env('WEATHER_STATION_API_URL', env('METEO_API_ENDPOINT', 'https://meteoestacion.desarrollougmaicao.com/api_publica.php')),
        'device_code' => env('METEO_DEVICE_CODE', 'METEOESTACION'),
        'since_parameter' => env('METEO_API_SINCE_PARAMETER', 'fecha_desde'),
        'verify_ssl' => env('METEO_API_VERIFY_SSL', false),
        'api_key' => env('METEO_API_KEY'),
        'api_key_header' => env('METEO_API_KEY_HEADER', 'X-API-KEY'),
        'user_agent' => env('METEO_API_USER_AGENT', 'SolarApp/1.0 (+https://solar.local)'),
        'referer' => env('METEO_API_REFERER'),
        'schedule_timezone' => env('WEATHER_STATION_SCHEDULE_TIMEZONE', 'America/Bogota'),
    ],

    'openai_recommendations' => [
        'enabled' => env('OPENAI_RECOMMENDATIONS_ENABLED', false),
        'model' => env('OPENAI_RECOMMENDATIONS_MODEL', 'gpt-5-mini'),
        'max_output_tokens' => (int) env('OPENAI_RECOMMENDATIONS_MAX_OUTPUT_TOKENS', 400),
        'cache_ttl_minutes' => (int) env('OPENAI_RECOMMENDATIONS_CACHE_TTL_MINUTES', 30),
    ],

];
