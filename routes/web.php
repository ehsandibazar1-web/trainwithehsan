<?php

use App\Http\Controllers\BlogController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
});

Route::get('/tr', function () {
    return view('tr.home');
});

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

Route::get('/system-cache-flush-7k2p9x', function () {
    Artisan::call('view:clear');
    Artisan::call('cache:clear');
    Artisan::call('config:clear');
    Artisan::call('route:clear');

    return '<pre>Cache cleared successfully.</pre>';
});
Route::get('/system-storage-link-4m8kw1', function () {
        $dir = '/home/u2772578/public_html/storage';

            if (! is_dir($dir)) {
                    mkdir($dir, 0755, true);
                        }

                            return '<pre>Storage directory ready: ' . $dir . '</pre>';
                            });
