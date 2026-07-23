<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// اندپوینتِ کوچکِ «توکنِ تازه‌ی CSRF» — وقتی HTMLِ صفحه از لبه‌ی Cloudflare کش می‌شود، توکنِ داخلِ
// <meta name="csrf-token"> مشترک/کهنه است و برای همه‌ی بازدیدکننده‌ها یکی است. این مسیر عمداً کش
// نمی‌شود (Cache-Control: no-store + خارج از قانونِ کشِ Cloudflare) و از میدلورِ web عبور می‌کند،
// پس سشنِ واقعیِ همین بازدیدکننده را برقرار می‌کند و توکنِ متناظرِ همان سشن را برمی‌گرداند. فرم‌ها
// (خبرنامه/تماس) پیش از ارسال این را صدا می‌زنند تا توکنشان همیشه با سشنِ خودشان بخواند.
//
// عمداً کنترلرِ invokable است، نه closure در routes/web.php — چون این پروژه هنگام دیپلوی
// `php artisan route:cache` اجرا می‌کند و مسیرهای closure کشِ route را می‌شکنند.
class CsrfTokenController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()
            ->json(['token' => csrf_token()])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }
}
