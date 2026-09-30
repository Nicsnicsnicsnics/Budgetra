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

    'ocr' => [
        'key'      => env('OCR_API_KEY', ''),
        'endpoint' => 'https://api.ocr.space/parse/image',
    ],

    'gemini' => [
        'key'      => env('GEMINI_API_KEY', ''),
        // Deliberately the "latest" alias rather than a pinned version:
        // gemini-2.0-flash was decommissioned and 404'd on every call, and
        // gemini-2.5-flash is already 404ing too. The alias tracks whichever
        // flash model is current, so a retirement stops silently killing this
        // link in the fallback chain.
        'endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent',
    ],

    'groq' => [
        'key' => env('GROQ_API_KEY', ''),
    ],

    'cerebras' => [
        'key' => env('CEREBRAS_API_KEY', ''),
    ],

    'mistral' => [
        'key' => env('MISTRAL_API_KEY', ''),
    ],

    'openrouter' => [
        'key' => env('OPENROUTER_API_KEY', ''),
    ],

    'serpapi' => [
        'key' => env('SERPAPI_KEY', ''),

        // Whether the nightly photo backfill is scheduled (routes/console.php).
        // Off by default: a pass spends up to 20 requests from the same 80/day
        // pool as live flight, hotel and restaurant lookups. Run the commands
        // by hand when you want the photos, or set SERPAPI_IMAGE_BACKFILL=true.
        'image_backfill' => env('SERPAPI_IMAGE_BACKFILL', false),
    ],

    'serper' => [
        'key' => env('SERPER_KEY', ''),
    ],

    'currency_converter' => [
        'key' => env('CURRENCY_CONVERTER_KEY', ''),
    ],

];
