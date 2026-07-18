{{-- Open Graph video + Twitter Player Card برای ویدیوی اصلیِ (اولین) صفحه — یک منبعِ واحد
     (VideoSchemaService::primarySocialVideo روی همان $videoSchemas که schema/سایت‌مپ هم می‌سازد).
     کاملاً افزایشی: صفحه‌ی بدونِ ویدیو هیچ تگی اضافه نمی‌کند، og:title/description/image دست‌نخورده. --}}
@php($__sv = app(\App\Services\Seo\VideoSchemaService::class)->primarySocialVideo($videoSchemas ?? []))
@if($__sv)
<meta property="og:video" content="{{ $__sv['url'] }}">
<meta property="og:video:url" content="{{ $__sv['url'] }}">
@if($__sv['secure'])
<meta property="og:video:secure_url" content="{{ $__sv['url'] }}">
@endif
<meta property="og:video:type" content="{{ $__sv['type'] }}">
<meta property="og:video:width" content="1280">
<meta property="og:video:height" content="720">
@if($__sv['is_embed'])
<meta name="twitter:card" content="player">
<meta name="twitter:title" content="{{ $__sv['title'] }}">
@if($__sv['description'] !== '')
<meta name="twitter:description" content="{{ \Illuminate\Support\Str::limit($__sv['description'], 200) }}">
@endif
@if($__sv['thumbnail'])
<meta name="twitter:image" content="{{ $__sv['thumbnail'] }}">
@endif
<meta name="twitter:player" content="{{ $__sv['url'] }}">
<meta name="twitter:player:width" content="1280">
<meta name="twitter:player:height" content="720">
@endif
@endif
