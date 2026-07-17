<?php

use App\Http\Controllers\BlogController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PreviewController;
use App\Http\Controllers\SeoController;
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

// خبرنامه — ثبت‌نام AJAX از فوتر (CSRF از میدلور پیش‌فرض web + محدودیت نرخ named limiter)
Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe'])
    ->middleware('throttle:newsletter');
Route::post('/newsletter/resend', [NewsletterController::class, 'resend'])
    ->middleware('throttle:newsletter');
// تأیید و لغو اشتراک با توکن ۶۴کاراکتری اختصاصی هر مشترک — بدون نیاز به لاگین
Route::get('/newsletter/verify/{token}', [NewsletterController::class, 'verify']);
Route::get('/newsletter/unsubscribe/{token}', [NewsletterController::class, 'unsubscribe']);

// فرم تماس صفحه‌ی Contact — همون الگوی ضدهرزنامه‌ی خبرنامه (هانی‌پات + سد زمانی + محدودیت نرخ).
// GET /contact خودش از طریق مسیر کلی Page در پایین این فایل حل می‌شود؛ این فقط ارسال فرم است.
Route::post('/contact', [ContactController::class, 'submit'])
    ->middleware('throttle:contact');

// صفحات مستقل (Privacy, Terms, FAQ, ...) — این دو مسیر باید همیشه آخرِ فایل بمانند تا
// مسیرهای بالاتر (که زودتر ثبت می‌شوند) اول match شوند. lookahead هم به‌عنوان لایه‌ی دوم
// ایمنی، اسلاگ‌های رزروشده (از جمله /admin که Filament جدا ثبت می‌کند) را رد می‌کند.
Route::get('/tr/{slug}', [PageController::class, 'showTr'])
    ->where('slug', '(?!blog$|about$|feed$)[A-Za-z0-9-]+');

Route::get('/{slug}', [PageController::class, 'show'])
    ->where('slug', '(?!admin$|blog$|about$|tr$|feed$|preview$|storage$|livewire$|system-)[A-Za-z0-9-]+');
