<?php

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\AuthenticateAiImportToken;
use App\Http\Middleware\EdgeCache;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'ai.import.token' => AuthenticateAiImportToken::class,
        ]);

        $middleware->web(append: [AddSecurityHeaders::class]);

        // EdgeCache باید «بیرونی‌تر» از StartSession باشد تا در فازِ پاسخ، کوکیِ سشنی که StartSession
        // اضافه کرده را ببیند و برای پاسخِ ناشناسِ کش‌پذیر حذفش کند — پس prepend، نه append. پیش‌فرض
        // خاموش است (کلید در تنظیمات)، پس تا روشن‌نشدن هیچ اثری روی سایت ندارد.
        $middleware->web(prepend: [EdgeCache::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
