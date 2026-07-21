@php
    // زبان از پیشوندِ URL — این صفحه عمداً به layoutِ اصلی extend نمی‌شود و هیچ کوئریِ
    // دیتابیسی ندارد (منو/فوترِ CMSدار می‌خواهد)؛ صفحه‌ی خطا باید حتی وقتی دیتابیس هم
    // مشکل دارد سالم رندر شود
    $isTr = request()->is('tr') || request()->is('tr/*');
    $t = $isTr
        ? [
            'title' => 'Sayfa bulunamadı — Ehsan Dibazar',
            'heading' => 'Sayfa bulunamadı',
            'message' => 'Aradığınız sayfa taşınmış veya hiç var olmamış olabilir.',
            'home' => 'Ana Sayfa',
            'blog' => 'Blog',
            'home_url' => url('/tr'),
            'blog_url' => url('/tr/blog'),
        ]
        : [
            'title' => 'Page not found — Ehsan Dibazar',
            'heading' => 'Page not found',
            'message' => 'The page you are looking for may have been moved, or it never existed.',
            'home' => 'Home',
            'blog' => 'Blog',
            'home_url' => url('/'),
            'blog_url' => url('/blog'),
        ];
@endphp
<!DOCTYPE html>
<html lang="{{ $isTr ? 'tr' : 'en' }}" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>{{ $t['title'] }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}?v={{ @filemtime(public_path('favicon.ico')) ?: '1' }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}?v={{ @filemtime(public_path('favicon-32x32.png')) ?: '1' }}">
    <link rel="preload" as="font" type="font/woff2" href="{{ asset('fonts/manrope-latin.woff2') }}" crossorigin>
    <style>
        /* همان دو @font-faceِ self-hostedِ layoutهای اصلی (وزنِ متغیر ۴۰۰ تا ۸۰۰) */
        @@font-face{
            font-family:'Manrope';font-style:normal;font-weight:400 800;font-display:swap;
            src:url('{{ asset('fonts/manrope-latin.woff2') }}') format('woff2');
            unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD;
        }
        @@font-face{
            font-family:'Manrope';font-style:normal;font-weight:400 800;font-display:swap;
            src:url('{{ asset('fonts/manrope-latin-ext.woff2') }}') format('woff2');
            unicode-range:U+0100-02BA,U+02BD-02C5,U+02C7-02CC,U+02CE-02D7,U+02DD-02FF,U+0304,U+0308,U+0329,U+1D00-1DBF,U+1E00-1E9F,U+1EF2-1EFF,U+2020,U+20A0-20AB,U+20AD-20C0,U+2113,U+2C60-2C7F,U+A720-A7FF;
        }
        *{margin:0;padding:0;box-sizing:border-box}
        body{
            min-height:100vh;display:flex;align-items:center;justify-content:center;
            background:#1d1d1d;color:#fff;text-align:center;padding:24px;
            font-family:'Manrope',system-ui,sans-serif;line-height:1.7;
        }
        .code{font-size:96px;font-weight:800;color:#d9bb75;line-height:1;letter-spacing:.02em}
        h1{font-size:24px;font-weight:700;margin:14px 0 8px}
        p{color:#b5b5b5;font-size:15px;max-width:420px;margin:0 auto}
        .links{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-top:26px}
        .links a{
            display:inline-flex;align-items:center;justify-content:center;min-height:44px;
            padding:0 26px;border-radius:25px;text-decoration:none;font-weight:700;font-size:14px;
            background:#d9bb75;color:#1d1d1d;transition:opacity .2s;
        }
        .links a.alt{background:transparent;color:#d9bb75;border:1px solid #d9bb75}
        .links a:hover{opacity:.85}
        a:focus-visible{outline:2px solid #d9bb75;outline-offset:2px}
        @@media (prefers-reduced-motion: reduce){*{transition:none!important}}
    </style>
</head>
<body>
    <main>
        <div class="code" aria-hidden="true">404</div>
        <h1>{{ $t['heading'] }}</h1>
        <p>{{ $t['message'] }}</p>
        <div class="links">
            <a href="{{ $t['home_url'] }}">{{ $t['home'] }}</a>
            <a class="alt" href="{{ $t['blog_url'] }}">{{ $t['blog'] }}</a>
        </div>
    </main>
</body>
</html>
