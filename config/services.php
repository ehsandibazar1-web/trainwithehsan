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

    // App\Services\AiAssistant — AI Content Assistant's live LLM provider (App\Providers\AppServiceProvider
    // binds App\Services\AiAssistant\Contracts\AiProvider to NullProvider when 'key' is empty)
    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-5'),
        'driver' => env('AI_ASSISTANT_DRIVER', 'anthropic'),
    ],

    // مصرف‌شده در layouts/master.blade.php و master-tr.blade.php — بنر رضایت کوکی فقط وقتی رندر
    // می‌شود که حداقل یکی از این دو مقدار پر باشد؛ اگر خالی باشند نه بنری هست نه اسکریپت ردیابی‌ای
    // لود می‌شود (رفتار امن پیش‌فرض روی هر نصب/محیطی که این دو env را تنظیم نکرده)
    'google_analytics' => [
        'id' => env('GOOGLE_ANALYTICS_ID'),
    ],

    'microsoft_clarity' => [
        'id' => env('MICROSOFT_CLARITY_ID'),
    ],

];
