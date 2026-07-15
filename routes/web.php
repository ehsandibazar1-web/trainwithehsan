<?php

use App\Http\Controllers\BlogController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PreviewController;
use App\Http\Controllers\SeoController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::get('/', [BlogController::class, 'home']);

Route::get('/tr', [BlogController::class, 'homeTr']);

Route::get('/about', [BlogController::class, 'about']);

Route::get('/tr/about', [BlogController::class, 'aboutTr']);

Route::get('/blog', [BlogController::class, 'index']);
Route::get('/blog/{slug}', [BlogController::class, 'show']);

Route::get('/tr/blog', [BlogController::class, 'indexTr']);
Route::get('/tr/blog/{slug}', [BlogController::class, 'showTr']);

Route::get('/preview/article/{article}', [PreviewController::class, 'show'])
    ->name('articles.preview')
    ->middleware('signed');

Route::get('/sitemap.xml', [SeoController::class, 'sitemap']);
Route::get('/feed', [SeoController::class, 'feed']);
Route::get('/tr/feed', [SeoController::class, 'feedTr']);

Route::get('/system-cache-flush-7k2p9x', function () {
    Artisan::call('view:clear');
    Artisan::call('cache:clear');
    Artisan::call('config:clear');
    Artisan::call('route:clear');

    return '<pre>Cache cleared successfully.</pre>';
});
Route::get('/system-migrate-9x4kq2', function () {
    Artisan::call('migrate', ['--force' => true]);

    return '<pre>'.Artisan::output().'</pre>';
});

// صفحات مستقل (Privacy, Terms, FAQ, ...) — این دو مسیر باید همیشه آخرِ فایل بمانند تا
// مسیرهای بالاتر (که زودتر ثبت می‌شوند) اول match شوند. lookahead هم به‌عنوان لایه‌ی دوم
// ایمنی، اسلاگ‌های رزروشده (از جمله /admin که Filament جدا ثبت می‌کند) را رد می‌کند.
Route::get('/tr/{slug}', [PageController::class, 'showTr'])
    ->where('slug', '(?!blog$|about$|feed$)[A-Za-z0-9-]+');

Route::get('/{slug}', [PageController::class, 'show'])
    ->where('slug', '(?!admin$|blog$|about$|tr$|feed$|preview$|storage$|livewire$|system-)[A-Za-z0-9-]+');
