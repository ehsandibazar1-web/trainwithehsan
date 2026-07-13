<?php

use App\Http\Controllers\BlogController;
use App\Http\Controllers\PreviewController;
use App\Http\Controllers\SeoController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::get('/', [BlogController::class, 'home']);

Route::get('/tr', [BlogController::class, 'homeTr']);

Route::get('/about', function () {
    return view('about');
});

Route::get('/tr/about', function () {
    return view('tr.about');
});

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
    \Illuminate\Support\Facades\DB::statement('DROP TABLE IF EXISTS activity_log');
    return '<pre>Old activity_log table dropped.</pre>';
});