<!DOCTYPE html>
<html lang="@yield('lang', 'en')" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Ehsan Dibazar — Self-Defense & Martial Intelligence Training in Istanbul')</title>
    <meta name="description" content="@yield('meta_description', 'Self-defense and Brazilian Jiu-Jitsu training in Istanbul for complete beginners. Learn to make the right decision under pressure — the Martial Intelligence method by Ehsan Dibazar.')">
    @php($metaKeywords = trim($__env->yieldContent('meta_keywords')))
    @if($metaKeywords)
    <meta name="keywords" content="{{ $metaKeywords }}">
    @endif
    <meta name="robots" content="@yield('robots', 'index,follow')">
    <link rel="canonical" href="@yield('canonical', url()->current())">

    {{-- hreflang: فعال‌سازی بعد از آماده شدن نسخهٔ ترکی --}}
    {{-- <link rel="alternate" hreflang="en" href="https://trainwithehsan.com@yield('path_suffix')"> --}}
    {{-- <link rel="alternate" hreflang="tr" href="https://trainwithehsan.com/tr@yield('path_suffix')"> --}}
    {{-- <link rel="alternate" hreflang="x-default" href="https://trainwithehsan.com@yield('path_suffix')"> --}}

    <meta property="og:site_name" content="Train with Ehsan">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:title" content="@yield('og_title', 'Ehsan Dibazar — Self-Defense & Martial Intelligence')">
    <meta property="og:description" content="@yield('og_description', 'Self-defense training for complete beginners in Istanbul. Decision-making under pressure, not just technique.')">
    <meta property="og:url" content="@yield('canonical', url()->current())">
    @php($ogImage = trim($__env->yieldContent('og_image')))
    @if($ogImage)
    <meta property="og:image" content="{{ $ogImage }}">
    @php($ogImageWidth = trim($__env->yieldContent('og_image_width')))
    @php($ogImageHeight = trim($__env->yieldContent('og_image_height')))
    @php($ogImageType = trim($__env->yieldContent('og_image_type')))
    @if($ogImageWidth)
    <meta property="og:image:width" content="{{ $ogImageWidth }}">
    @endif
    @if($ogImageHeight)
    <meta property="og:image:height" content="{{ $ogImageHeight }}">
    @endif
    @if($ogImageType)
    <meta property="og:image:type" content="{{ $ogImageType }}">
    @endif
    @endif
    <meta name="theme-color" content="#d9bb75">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @yield('json-ld')

    {{-- تنظیمات فوتر از CMS (یک کوئری) — پیش از <style> چون تصویر پس‌زمینه داخل CSS استفاده می‌شود.
         مقدار فقط-فاصله عمداً «پر» حساب می‌شود (همان قرارداد مخفی‌کردن متنِ پیش‌فرض) --}}
    @php($footerRaw = \App\Models\SiteSetting::where('key', 'like', 'footer.en.%')->pluck('value', 'key'))
    @php($fv = fn($k, $d = '') => (($footerRaw["footer.en.$k"] ?? null) !== null && ($footerRaw["footer.en.$k"] ?? '') !== '') ? $footerRaw["footer.en.$k"] : $d)
    @php($footerColumns = json_decode($footerRaw['footer.en.columns'] ?? '[]', true) ?: [])
    @php($footerSocials = json_decode($footerRaw['footer.en.socials'] ?? '[]', true) ?: [])

    <style>
        /* ===== مقادیر عیناً از site.min.css سایت فارسی ===== */
        :root{
            --gold:#d9bb75;
            --header:#1d1d1d;
            --footer:#0a0809;
            --counter:#363636;
            --news-bg:#e1e1e1;
            --inst2-bg:#ebebeb;
            --title:#393e40;
            --text:#3b3b3b;
        }
        *{margin:0;padding:0;box-sizing:border-box}
        html{scroll-behavior:smooth}
        @@media (prefers-reduced-motion: reduce){
            html{scroll-behavior:auto}
            *,*::before,*::after{animation:none!important;transition:none!important}
        }
        body{
            background:#fff;color:var(--text);
            font-family:'Poppins',system-ui,sans-serif;
            font-size:14px;line-height:1.9;-webkit-font-smoothing:antialiased;
        }
        img{max-width:100%;display:block}
        a{text-decoration:none;color:inherit}
        a:focus-visible,button:focus-visible{outline:2px solid var(--gold);outline-offset:2px}
        .wrap{max-width:1140px;margin:0 auto;padding:0 15px}
        h1,h2,h3{line-height:1.4;color:var(--title)}

        /* ===== Header — .c-header {background:#1d1d1d} ===== */
        .site-header{background:var(--header);position:sticky;top:0;z-index:100}
        .nav-bar{display:flex;align-items:center;justify-content:space-between;padding:14px 0}
        .brand-badge{display:flex;align-items:center;gap:12px;order:2}
        .brand-logo-img{height:60px;width:auto;display:block}
        /* .cssmenu>ul>li>a {color:#fff; font-size:15px; font-weight:500} + hover gold */
        .nav-links{display:flex;gap:2px;list-style:none;align-items:center;order:1}
        .nav-links>li>a{
            display:block;color:#fff;font-size:14px;font-weight:500;
            padding:9px 13px;border-radius:9px;transition:.25s;
        }
        .nav-links>li>a:hover,.nav-links>li>a[aria-current="page"]{background-color:var(--gold);color:#fff}
        .nav-cta{background:var(--gold);color:#000!important;font-weight:600}
        .nav-toggle{
            display:none;background:none;border:0;color:#fff;
            width:44px;height:44px;font-size:24px;cursor:pointer;order:3;
        }
        @@media (max-width:900px){
            .nav-links{display:none}
            .nav-toggle{display:block}
            .nav-bar{justify-content:flex-end;gap:14px}
        }

        /* ===== Mobile panel — .panel-menu {background:#171717; width:300px; right} ===== */
        .panel-menu{
            position:fixed;top:0;right:-100%;width:100%;max-width:320px;height:100%;
            background:#171717;z-index:999999;transition:.5s;overflow-y:auto;
        }
        .panel-menu.open{right:0;box-shadow:0 5px 15px 0 rgba(0,0,0,.3)}
        .panel-menu-head{display:flex;justify-content:flex-end;padding:14px}
        .panel-close{background:none;border:0;color:#fff;font-size:28px;cursor:pointer}
        .panel-menu ul{list-style:none}
        /* .panel-menu ul li {background:#171717; border-bottom:1px solid #2a2929; color:#fff; font-size:14px} */
        .panel-menu li a{
            display:block;padding:0 20px;line-height:50px;color:#fff;font-size:14px;font-weight:500;
            background-color:#171717;border-bottom:1px solid #2a2929;
        }
        .panel-menu li a:hover,.panel-menu li a[aria-current="page"]{background:var(--gold);color:#000}
        .panel-overlay{
            position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999998;
            opacity:0;pointer-events:none;transition:.3s;
        }
        .panel-overlay.open{opacity:1;pointer-events:auto}

        /* ===== .show-more — عیناً: bg #d9bb75, color #000, padding 12px 15px 12px 67px (آینه‌ی LTR)، hover معکوس ===== */
        .show-more{
            position:relative;display:inline-block;
            background-color:var(--gold);color:#000;
            padding:12px 67px 12px 15px;font-weight:500;font-size:17px;
            transition:.2s linear;
        }
        .show-more::after{content:"⟶";position:absolute;top:12px;right:14px;font-size:15px}
        .show-more:hover{background-color:#000;color:var(--gold)}

        /* ===== Carousel (جایگزین سبک Owl) ===== */
        .carousel{position:relative}
        .carousel-track{
            display:flex;gap:20px;overflow-x:auto;scroll-snap-type:x mandatory;
            scrollbar-width:none;padding-bottom:4px;
            -webkit-overflow-scrolling:touch;scroll-padding-left:15px;
        }
        .carousel-track::-webkit-scrollbar{display:none}
        .carousel-track>*{scroll-snap-align:start;flex:0 0 auto}
        /* .owl-nav span {color:#5a5a5a; font-size:33px} */
        .car-arrow{
            position:absolute;top:40%;transform:translateY(-50%);
            width:40px;height:40px;background:none;border:0;
            color:#5a5a5a;font-size:33px;line-height:1;cursor:pointer;z-index:2;
        }
        .car-arrow:hover{color:var(--gold)}
        .car-prev{left:-44px}
        .car-next{right:-44px}
        @@media (max-width:1280px){.car-prev{left:-10px}.car-next{right:-10px}}
        @@media (max-width:600px){.car-arrow{display:none}}

        /* ===== Newsletter — فرم طلایی بالای فوتر ===== */
        .newsletter{background:var(--gold);padding:34px 0;color:#000}
        .newsletter-row{display:flex;align-items:center;justify-content:space-between;gap:24px;flex-wrap:wrap}
        .newsletter-row>div:first-child{min-width:0;flex:1 1 260px}
        .newsletter h3{color:#000;font-size:19px;font-weight:700}
        .newsletter p{font-size:13px}
        .newsletter-form{display:flex;max-width:420px;width:100%;min-width:0;flex:1 1 260px}
        .newsletter-form input{
            flex:1;min-width:0;border:0;padding:13px 16px;font-size:14px;font-family:inherit;
            border-radius:25px 0 0 25px;outline:0;
        }
        .newsletter-form button{
            border:0;background:#000;color:var(--gold);padding:13px 26px;font-weight:600;
            border-radius:0 25px 25px 0;cursor:pointer;font-family:inherit;font-size:14px;
            flex-shrink:0;white-space:nowrap;
        }
        /* پیام نتیجهٔ فرم خبرنامه — تا وقتی پیامی نیست هیچ فضایی نمی‌گیرد */
        .newsletter-form{position:relative}
        .newsletter-msg{display:none;flex-basis:100%;width:100%;font-size:13px;font-weight:600;margin-top:10px}
        .newsletter-msg.show{display:block}
        .newsletter-msg.ok{color:#14421c}
        .newsletter-msg.err{color:#7a1414}

        /* ===== Footer — عیناً مطابق site.min.css سایت اصلی: .footer{background:url(bg-footer.png) #0a0809} ===== */
        .site-footer{
            background:url('{{ asset('storage/' . $fv('bg_image', 'homepage/bg-footer.jpg')) }}') var(--footer);
            padding:40px 0 24px;color:#fff;
        }
        .footer-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:40px;margin-bottom:36px;text-align:center}
        @@media (max-width:720px){.footer-grid{grid-template-columns:1fr 1fr;gap:24px}}
        @@media (max-width:480px){.footer-grid{grid-template-columns:1fr}}
        .footer-brand{text-align:center;margin-bottom:24px}
        .footer-logo-img{height:130px;width:auto;margin:0 auto;display:block}
        /* .title-footer span {font-size:15px; color:#fff; font-weight:500} */
        .site-footer h4{font-weight:600;font-size:15px;color:#fff;margin-bottom:12px}
        .site-footer ul{list-style:none}
        /* .lnk-footers li a {color:#ebebeb; font-size:12px; padding + گلوله طلایی} */
        .site-footer li{line-height:2.5}
        .site-footer li::before{content:"";display:inline-block;width:4px;height:4px;border-radius:50%;background-color:var(--gold);margin-right:6px;vertical-align:middle}
        .site-footer li a{color:#ebebeb;font-size:12px}
        .site-footer li a:hover{color:var(--gold);transition:.5s linear}
        /* .copy {font-size:12px; color:#fff} + .copy-right .color {color:#d9bb75} */
        .footer-note{
            border-top:1px solid #222;padding-top:16px;font-size:12px;color:#fff;
            text-align:center;
        }
        .footer-note .color{color:var(--gold);font-weight:600}
        /* بلوک‌های اختیاری فوتر (توضیح/شبکه‌های اجتماعی/اطلاعات تماس) — تا وقتی در پنل پر نشوند اصلاً رندر نمی‌شوند */
        .footer-desc{color:#ebebeb;font-size:12px;line-height:2;max-width:480px;margin:12px auto 0;text-align:center}
        .footer-socials{margin-top:12px;text-align:center}
        .footer-socials li{display:inline-block;margin:0 8px}
        .footer-contact{margin-top:10px;font-size:12px;color:#ebebeb;line-height:2;text-align:center}
        .footer-contact a{color:#ebebeb}
        .footer-contact a:hover{color:var(--gold);transition:.5s linear}
        .footer-contact span{margin:0 8px}

        /* ===== انیمیشن ورود هنگام اسکرول (fade-in-up) — سراسر سایت؛ فقط opacity/transform (GPU)، یک‌بار با IntersectionObserver ===== */
        .reveal{opacity:0;transform:translateY(40px);transition:opacity .7s ease-out,transform .7s ease-out;will-change:transform,opacity}
        .reveal.is-visible{opacity:1;transform:translateY(0)}
        .reveal-group>.reveal:nth-child(1){transition-delay:0ms}
        .reveal-group>.reveal:nth-child(2){transition-delay:90ms}
        .reveal-group>.reveal:nth-child(3){transition-delay:180ms}
        .reveal-group>.reveal:nth-child(4){transition-delay:270ms}
        .reveal-group>.reveal:nth-child(5){transition-delay:360ms}
        .reveal-group>.reveal:nth-child(6){transition-delay:450ms}
        .reveal-group>.reveal:nth-child(7){transition-delay:540ms}
        .reveal-group>.reveal:nth-child(8){transition-delay:630ms}
        @@media (prefers-reduced-motion: reduce){.reveal{opacity:1!important;transform:none!important}}

        /* ===== بنر رضایت کوکی (GA4/Clarity) — طبق KVKK/GDPR: تا کلیک «Accept» هیچ اسکریپت ردیابی لود نمی‌شود ===== */
        .cookie-consent{
            position:fixed;left:0;right:0;bottom:0;z-index:999997;
            background:#171717;color:#fff;padding:16px 20px;
            display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:16px;
            box-shadow:0 -2px 10px rgba(0,0,0,.25);
        }
        .cookie-consent[hidden]{display:none}
        .cookie-consent__text{font-size:13px;line-height:1.6;flex:1 1 320px;max-width:640px}
        .cookie-consent__text a{color:var(--gold);text-decoration:underline}
        .cookie-consent__actions{display:flex;gap:10px;flex-shrink:0}
        .cookie-consent__btn{
            border:0;border-radius:25px;padding:10px 22px;font-size:13px;font-weight:600;
            cursor:pointer;font-family:inherit;background:var(--gold);color:#000;
        }
        .cookie-consent__btn--ghost{background:transparent;border:1px solid #555;color:#fff}
        .cookie-consent__btn:hover{opacity:.85}
        @@media (max-width:600px){.cookie-consent{flex-direction:column;align-items:stretch;text-align:center}.cookie-consent__actions{justify-content:center}}
    </style>
    {{-- بدون جاوااسکریپت: محتوا هرگز نباید مخفی بماند --}}
    <noscript><style>.reveal{opacity:1!important;transform:none!important}</style></noscript>
    @yield('page-css')
</head>
<body>

{{-- بنر رضایت کوکی (KVKK/GDPR) — اگر هیچ‌کدام از این دو env تنظیم نشده باشد اصلاً رندر نمی‌شود
     و هیچ اسکریپت ردیابی‌ای هم لود نمی‌شود (نگاه کنید به config/services.php). GA4 دیگر مستقیم
     لود نمی‌شود — از داخل همین GTM container به‌عنوان یک تگ تنظیم می‌شود (در پنل GTM، نه این کد) --}}
@php($gtmId = config('services.google_tag_manager.id'))
@php($clarityId = config('services.microsoft_clarity.id'))
@if($gtmId || $clarityId)
<div class="cookie-consent" id="cookieConsent" hidden role="dialog" aria-live="polite" aria-label="Cookie consent">
    <div class="cookie-consent__text">
        We use cookies to understand how visitors use this site (Google Analytics, Microsoft Clarity). You can accept or decline non-essential cookies.
        <a href="{{ url('/privacy-policy') }}">Learn more</a>
    </div>
    <div class="cookie-consent__actions">
        <button type="button" id="cookieDecline" class="cookie-consent__btn cookie-consent__btn--ghost">Decline</button>
        <button type="button" id="cookieAccept" class="cookie-consent__btn">Accept</button>
    </div>
</div>
@endif

{{-- منو: از پنل مدیریت (Menu Settings) — در صورت خالی بودن، منوی پیش‌فرض --}}
@php($menuItems = \App\Models\SiteSetting::getJson('menu.en.items'))
@php($menuItems = !empty($menuItems) ? $menuItems : [['label' => 'Home', 'url' => '/', 'highlight' => false], ['label' => 'About', 'url' => '/about', 'highlight' => false], ['label' => 'Martial Intelligence', 'url' => '/martial-intelligence', 'highlight' => false], ['label' => 'Courses', 'url' => '/courses', 'highlight' => false], ['label' => 'Blog', 'url' => '/blog', 'highlight' => false], ['label' => 'Contact', 'url' => '/contact', 'highlight' => true]])
@php($isActive = fn ($u) => ($p = ltrim($u ?? '', '/')) === '' ? request()->is('/') : (request()->is($p) || request()->is($p . '/*')))

<div class="panel-overlay" id="panelOverlay"></div>
<nav class="panel-menu" id="panelMenu">
    <div class="panel-menu-head">
        <button class="panel-close" id="panelClose" aria-label="Close menu">×</button>
    </div>
    <ul>
        @foreach($menuItems as $item)
        <li><a href="{{ url($item['url'] ?? '/') }}" @if($isActive($item['url'] ?? '')) aria-current="page" @endif>{{ $item['label'] ?? '' }}</a></li>
        @endforeach
        <li><a href="{{ url('/tr') }}" rel="noopener">Türkçe</a></li>
    </ul>
</nav>

<header class="site-header">
    <div class="wrap nav-bar">
        <a href="{{ url('/') }}" class="brand-badge">
            <img src="{{ asset('storage/homepage/logo.header.png') }}" alt="Ehsan Dibazar - Defensive Tactics" class="brand-logo-img">
        </a>
        <ul class="nav-links">
            @foreach($menuItems as $item)
            @continue(!empty($item['highlight']))
            <li><a href="{{ url($item['url'] ?? '/') }}" @if($isActive($item['url'] ?? '')) aria-current="page" @endif>{{ $item['label'] ?? '' }}</a></li>
            @endforeach
            <li><a href="{{ url('/tr') }}" rel="noopener" style="font-size:12px;border:1px solid #3a3a3a">TR</a></li>
            @foreach($menuItems as $item)
            @continue(empty($item['highlight']))
            <li><a href="{{ url($item['url'] ?? '/') }}" class="nav-cta">{{ $item['label'] ?? '' }}</a></li>
            @endforeach
        </ul>
        <button class="nav-toggle" id="navToggle" aria-label="Open menu">☰</button>
    </div>
</header>

<main>
    @yield('content')
</main>

{{-- خبرنامه طلایی — مطابق فوتر سایت فارسی --}}
<div class="newsletter reveal">
    <div class="wrap newsletter-row">
        <div>
            <h3>{{ $fv('newsletter_title', 'Get the latest articles') }}</h3>
            <p>{{ $fv('newsletter_description', 'Subscribe to receive new articles and training tips by email.') }}</p>
        </div>
        <form class="newsletter-form js-newsletter-form" method="post" action="{{ url('/newsletter/subscribe') }}" novalidate
              data-msg-toomany="{{ __('newsletter.too_many', [], 'en') }}"
              data-msg-error="{{ __('newsletter.error', [], 'en') }}">
            <input type="hidden" name="locale" value="en">
            {{-- سد زمانی: مُهر زمانی رمزشدهٔ لحظهٔ رندر — ارسالِ زودتر از ۳ ثانیه یعنی بات --}}
            <input type="hidden" name="_nl_ts" value="{{ \Illuminate\Support\Facades\Crypt::encryptString((string) now()->timestamp) }}">
            {{-- هانی‌پات: برای انسان‌ها نامرئی است؛ پر شدنش یعنی بات --}}
            <input type="text" name="website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;height:0;width:0;border:0;padding:0">
            <input type="email" name="email" placeholder="{{ $fv('newsletter_placeholder', 'Enter your email') }}" required>
            <button type="submit">{{ $fv('newsletter_button', 'Subscribe') }}</button>
        </form>
        <div class="newsletter-msg" role="status" aria-live="polite"></div>
    </div>
</div>

<footer class="site-footer">
    <div class="wrap">
        @php($footerColumnsList = !empty($footerColumns) ? $footerColumns : [
            ['title' => 'About', 'links' => [
                ['label' => 'About', 'url' => '/about'],
                ['label' => 'Contact', 'url' => '/contact'],
            ]],
            ['title' => 'Training', 'links' => [
                ['label' => 'Courses', 'url' => '/courses'],
            ]],
            ['title' => 'Resources', 'links' => [
                ['label' => 'Blog', 'url' => '/blog'],
                ['label' => 'FAQ', 'url' => '/faq'],
            ]],
            ['title' => 'Legal', 'links' => [
                ['label' => 'Privacy Policy', 'url' => '/privacy-policy'],
                ['label' => 'Terms & Conditions', 'url' => '/terms-and-conditions'],
                ['label' => 'Cookie Policy', 'url' => '/cookie-policy'],
                ['label' => 'Disclaimer', 'url' => '/disclaimer'],
            ]],
        ])
        <div class="footer-grid">
            @foreach($footerColumnsList as $col)
            <div>
                <h4>{{ $col['title'] ?? '' }}</h4>
                <ul>
                    @foreach($col['links'] ?? [] as $lnk)
                    <li><a href="{{ str_starts_with($lnk['url'] ?? '', 'http') ? $lnk['url'] : url($lnk['url'] ?? '/') }}">{{ $lnk['label'] ?? '' }}</a></li>
                    @endforeach
                </ul>
            </div>
            @endforeach
        </div>
        <div class="footer-brand">
            <img src="{{ asset('storage/' . $fv('logo', 'homepage/logo.header.png')) }}" alt="Ehsan Dibazar - Defensive Tactics" class="footer-logo-img">
            @if($fv('description'))
            <p class="footer-desc">{{ $fv('description') }}</p>
            @endif
            @if(!empty($footerSocials))
            <ul class="footer-socials">
                @foreach($footerSocials as $soc)
                <li><a href="{{ $soc['url'] ?? '#' }}" target="_blank" rel="noopener">{{ $soc['label'] ?? '' }}</a></li>
                @endforeach
            </ul>
            @endif
            @if($fv('contact_email') || $fv('contact_phone') || $fv('contact_address'))
            <div class="footer-contact">
                @if($fv('contact_email'))<span><a href="mailto:{{ $fv('contact_email') }}">{{ $fv('contact_email') }}</a></span>@endif
                @if($fv('contact_phone'))<span><a href="tel:{{ preg_replace('/\s+/', '', $fv('contact_phone')) }}">{{ $fv('contact_phone') }}</a></span>@endif
                @if($fv('contact_address'))<span>{{ $fv('contact_address') }}</span>@endif
            </div>
            @endif
        </div>
        <div class="footer-note">
            @if($fv('copyright'))
            <span>© {{ date('Y') }} {{ $fv('copyright') }}</span>
            @else
            <span>© {{ date('Y') }} <span class="color">Ehsan Dibazar</span>. All rights reserved.</span>
            @endif
        </div>
    </div>
</footer>

<script>
    (function () {
        var toggle = document.getElementById('navToggle');
        var panel = document.getElementById('panelMenu');
        var overlay = document.getElementById('panelOverlay');
        var close = document.getElementById('panelClose');
        function open(){ panel.classList.add('open'); overlay.classList.add('open'); }
        function shut(){ panel.classList.remove('open'); overlay.classList.remove('open'); }
        toggle && toggle.addEventListener('click', open);
        close && close.addEventListener('click', shut);
        overlay && overlay.addEventListener('click', shut);
    })();

    (function () {
        document.querySelectorAll('[data-carousel]').forEach(function (car) {
            var track = car.querySelector('.carousel-track');
            var prev = car.querySelector('.car-prev');
            var next = car.querySelector('.car-next');
            if (!track) return;
            function step() {
                var card = track.querySelector(':scope > *');
                return card ? card.getBoundingClientRect().width + 20 : 300;
            }
            prev && prev.addEventListener('click', function () {
                track.scrollBy({ left: -step(), behavior: 'smooth' });
            });
            next && next.addEventListener('click', function () {
                track.scrollBy({ left: step(), behavior: 'smooth' });
            });
        });
    })();

    // ===== فرم خبرنامه — ارسال AJAX با CSRF؛ پیام موفق/خطا از سرور (ترجمه‌شده) نمایش داده می‌شود =====
    (function () {
        document.querySelectorAll('.js-newsletter-form').forEach(function (form) {
            var msg = form.parentElement.querySelector('.newsletter-msg');
            var btn = form.querySelector('button[type="submit"]');
            var csrf = document.querySelector('meta[name="csrf-token"]');
            function show(text, ok) {
                if (!msg) return;
                msg.textContent = text;
                msg.classList.add('show');
                msg.classList.toggle('ok', ok);
                msg.classList.toggle('err', !ok);
            }
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                if (btn) btn.disabled = true;
                fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrf ? csrf.content : '',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: new FormData(form)
                }).then(function (res) {
                    if (res.status === 429) { show(form.dataset.msgToomany, false); return null; }
                    return res.json().then(function (data) { return { ok: !!(data && data.ok), message: data && data.message }; });
                }).then(function (r) {
                    if (!r) return;
                    show(r.message || form.dataset.msgError, r.ok);
                    if (r.ok) { var em = form.querySelector('input[type="email"]'); if (em) em.value = ''; }
                }).catch(function () {
                    show(form.dataset.msgError, false);
                }).finally(function () {
                    if (btn) btn.disabled = false;
                });
            });
        });
    })();

    // ===== انیمیشن ورود هنگام اسکرول (fade-in-up) — یک‌بار، مدرن (IntersectionObserver، نه scroll listener)، با احترام به prefers-reduced-motion =====
    (function () {
        var items = document.querySelectorAll('.reveal');
        if (!items.length) return;
        var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (reduceMotion || typeof IntersectionObserver === 'undefined') {
            items.forEach(function (el) { el.classList.add('is-visible'); });
            return;
        }
        var io = new IntersectionObserver(function (entries, obs) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    obs.unobserve(entry.target);
                }
            });
        }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });
        items.forEach(function (el) { io.observe(el); });
    })();

    // ===== رضایت کوکی (GTM/Clarity) — طبق KVKK/GDPR: تا کلیک «Accept» هیچ اسکریپت ردیابی لود
    // نمی‌شود؛ تصمیم قبلی (accepted/declined) در localStorage نگه داشته می‌شود تا بنر دوباره
    // برای همان بازدیدکننده نمایش داده نشود. کل این IIFE عمداً پشت همان @@if بالا است — نه فقط
    // خودِ بنر — تا وقتی هیچ‌کدام تنظیم نشده حتی این کد بی‌اثر هم به بازدیدکننده فرستاده نشود.
    // GA4 دیگر جداگانه اینجا لود نمی‌شود — از داخل همین GTM container (به‌عنوان یک تگ «GA4
    // Configuration»، تنظیم‌شده در پنل tagmanager.google.com) فعال می‌شود؛ عمداً هیچ <noscript>
    // GTM اضافه نشده — بازدیدکننده‌ی بدون جاوااسکریپت اصلاً نمی‌تواند این بنر را ببیند/رضایت بدهد،
    // پس فایر کردنِ بی‌قیدوشرطِ آن iframe برای او دقیقاً همان کاری است که این کل مکانیزم رضایت
    // می‌خواهد از آن جلوگیری کند =====
    @if($gtmId || $clarityId)
    (function () {
        var GTM_ID = @json($gtmId);
        var CLARITY_ID = @json($clarityId);
        if (!GTM_ID && !CLARITY_ID) return;

        var STORAGE_KEY = 'twe_cookie_consent';
        var banner = document.getElementById('cookieConsent');

        function loadGtm() {
            if (!GTM_ID || window.__gtmLoaded) return;
            window.__gtmLoaded = true;
            window.dataLayer = window.dataLayer || [];

            // کانتینر کلاسیک Tag Manager همیشه با «GTM-» شروع می‌شود و از طریق gtm.js لود می‌شود.
            // هر شناسه‌ی دیگر («Google tag» با پیشوند GT-، یا شناسه‌ی مستقیم GA4/Ads با پیشوند
            // G-/AW-) محصول ساده‌شده‌ی جدید گوگل است و باید از طریق لودر gtag.js فعال شود — این
            // دو مکانیزم متفاوت‌اند، gtm.js برای شناسه‌ی GT-/G- کار نمی‌کند.
            if (GTM_ID.indexOf('GTM-') === 0) {
                window.dataLayer.push({ 'gtm.start': new Date().getTime(), event: 'gtm.js' });
                var f = document.getElementsByTagName('script')[0];
                var j = document.createElement('script');
                j.async = true;
                j.src = 'https://www.googletagmanager.com/gtm.js?id=' + GTM_ID;
                f.parentNode.insertBefore(j, f);
            } else {
                var g = document.createElement('script');
                g.async = true;
                g.src = 'https://www.googletagmanager.com/gtag/js?id=' + GTM_ID;
                document.head.appendChild(g);
                function gtag() { window.dataLayer.push(arguments); }
                gtag('js', new Date());
                gtag('config', GTM_ID);
            }
        }

        function loadClarity() {
            if (!CLARITY_ID || window.__clarityLoaded) return;
            window.__clarityLoaded = true;
            (function (c, l, a, r, i, t, y) {
                c[a] = c[a] || function () { (c[a].q = c[a].q || []).push(arguments); };
                t = l.createElement(r); t.async = 1; t.src = 'https://www.clarity.ms/tag/' + i;
                y = l.getElementsByTagName(r)[0]; y.parentNode.insertBefore(t, y);
            })(window, document, 'clarity', 'script', CLARITY_ID);
        }

        function loadTrackers() { loadGtm(); loadClarity(); }

        var consent = null;
        try { consent = window.localStorage.getItem(STORAGE_KEY); } catch (e) {}

        if (consent === 'accepted') {
            loadTrackers();
        } else if (consent !== 'declined' && banner) {
            banner.hidden = false;
        }

        if (banner) {
            var acceptBtn = document.getElementById('cookieAccept');
            var declineBtn = document.getElementById('cookieDecline');
            acceptBtn && acceptBtn.addEventListener('click', function () {
                try { window.localStorage.setItem(STORAGE_KEY, 'accepted'); } catch (e) {}
                banner.hidden = true;
                loadTrackers();
            });
            declineBtn && declineBtn.addEventListener('click', function () {
                try { window.localStorage.setItem(STORAGE_KEY, 'declined'); } catch (e) {}
                banner.hidden = true;
            });
        }
    })();
    @endif
</script>
@yield('page-js')
</body>
</html>
