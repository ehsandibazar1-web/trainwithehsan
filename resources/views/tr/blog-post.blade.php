@extends('layouts.master-tr')

{{-- فقط پرسش‌های کامل (سؤال و پاسخِ هر دو پرشده) — هم برای نمایش، هم برای JSON-LD --}}
@php($faqs = collect($article->faqs ?? [])->filter(fn ($f) => filled($f['question'] ?? null) && filled($f['answer'] ?? null))->values())

@section('title', ($article->seo_title ?: $article->title) . ' — Ehsan Dibazar')
@section('meta_description', $article->meta_description ?: ($article->excerpt ?? Str::limit(strip_tags($article->body), 150)))
@section('canonical', url('/tr/blog/' . $article->slug))
@section('og_title', ($article->og_title ?: $article->seo_title ?: $article->title) . ' — Ehsan Dibazar')
@section('og_description', $article->og_description ?: $article->meta_description ?: ($article->excerpt ?? Str::limit(strip_tags($article->body), 150)))
@section('og_image', $article->image_path ? asset('storage/' . $article->image_path) : '')

{{-- hreflang اختصاصی: اسلاگ EN/TR یک مقاله لزوماً یکی نیست (مثلاً dovus-zekasi در برابر
     combat-intelligence)، پس نمی‌توان مثل صفحات ثابت از یک path_suffix مشترک استفاده کرد. اگر
     ترجمه‌ای برای این مقاله ثبت نشده باشد، اصلاً ادعای hreflang به زبان دیگر نمی‌کنیم (بهتر از
     لینک‌دادن به یک اسلاگ حدسی که وجود ندارد) --}}
@php($translation = $article->translation ?: $article->translations->first())
@section('hreflang')
@if($translation)
<link rel="alternate" hreflang="en" href="{{ url($translation->path()) }}">
@endif
<link rel="alternate" hreflang="tr" href="{{ url($article->path()) }}">
<link rel="alternate" hreflang="x-default" href="{{ url($translation ? $translation->path() : $article->path()) }}">
@endsection

@section('json-ld')
<script type="application/ld+json">
{
  "@@context": "https://schema.org",
  "@@type": "Article",
  "headline": @json($article->title),
  "url": @json(url('/tr/blog/' . $article->slug)),
  "datePublished": @json(optional($article->published_at)->toIso8601String()),
  @if($article->image_path)"image": @json($article->optimized_image_url ?? asset('storage/' . $article->image_path)),@endif
  "author": {"@@type": "Person", "name": @json($article->author_name)},
  "publisher": {"@@id": "https://trainwithehsan.com/#organization"}
}
</script>
@if($faqs->isNotEmpty())
<script type="application/ld+json">
{
  "@@context": "https://schema.org",
  "@@type": "FAQPage",
  "mainEntity": [
    @foreach($faqs as $faq)
    {
      "@@type": "Question",
      "name": @json($faq['question']),
      "acceptedAnswer": {"@@type": "Answer", "text": @json($faq['answer'])}
    }@unless($loop->last),@endunless
    @endforeach
  ]
}
</script>
@endif
@include('partials.video-schema')
@endsection

@section('social_video')
@include('partials.video-og-tags')
@endsection

@section('page-css')
<style>
    #reading-progress{position:fixed;top:0;left:0;height:3px;background:var(--gold);z-index:9999;width:0}
    .site-blog-post{background-color:#f6f6f6;padding:0 0 50px}
    .site-blog-post__path{padding:14px 0;font-size:13px;color:#666}
    .site-blog-post__path a{color:#666}
    .site-blog-post__path a:hover{color:var(--gold-dark,#c09d4c)}
    .site-blog-post__path .sep{margin:0 6px;color:#bbb}
    .site-blog-post__box{box-shadow:2px 5px 14px -5px #dbdbdb;background:#fff;padding:26px;display:grid;grid-template-columns:2fr 1fr;gap:30px}
    @@media (max-width:860px){.site-blog-post__box{grid-template-columns:1fr}}

    .post-hero-image{
        aspect-ratio:800/450;margin-bottom:14px;border-radius:4px;overflow:hidden;
        background:linear-gradient(135deg,#d8d3c4 0%,#cdb87f 80%,var(--gold) 160%);
        background-size:cover;background-position:center;
    }
    .post-hero-image img{width:100%;height:100%;object-fit:cover;display:block}
    .post-title h1{font-weight:800;font-size:26px;color:#222;margin-bottom:10px;line-height:1.2;letter-spacing:-.01em}

    .article-meta{display:flex;flex-wrap:wrap;align-items:center;gap:14px;padding:10px 0;border-top:1px solid #eee;border-bottom:1px solid #eee;margin-bottom:1.2rem;font-size:13px;color:#666}
    .article-meta span{display:flex;align-items:center;gap:5px}
    .lang-switch{margin-left:auto}
    .lang-switch a{color:var(--gold-dark,#c09d4c);font-weight:600}

    .article-body p{text-align:justify;font-size:16px;font-weight:400;line-height:2;color:#555;margin-bottom:1.1rem}
    .article-body h2{font-size:20px;font-weight:700;color:#222;margin:2rem 0 1rem}
    .article-body h3{font-size:17px;font-weight:700;color:#333;margin:1.6rem 0 .8rem}
    .article-body img{max-width:100%;height:auto;border-radius:6px;margin:1rem 0}
    .article-body ul,.article-body ol{padding-left:22px;margin-bottom:1.1rem;color:#555}
    .article-body a{color:var(--gold-dark,#c09d4c)}

    .share-box{background:#f9f7f2;border:1px solid #e8e3d5;border-radius:10px;padding:16px 18px;margin:1.5rem 0}
    .share-box h3{font-size:15px;color:#333;margin-bottom:10px}
    .share-buttons{display:flex;gap:10px;flex-wrap:wrap}
    .share-btn{display:inline-flex;align-items:center;gap:6px;padding:7px 16px;border-radius:25px;font-size:13px;font-weight:600;border:none;cursor:pointer;transition:opacity .2s,transform .15s}
    .share-btn:hover{opacity:.85;transform:translateY(-1px)}
    .share-btn-tg{background:#0088cc;color:#fff}
    .share-btn-wa{background:#25D366;color:#fff}
    .share-btn-cp{background:#f0f0f0;color:#333}
    .share-btn-cp.copied{background:var(--gold);color:#1a1a1a}

    .hoosh-box{background:linear-gradient(135deg,#1a1a1a 0%,#2d2410 100%);border-radius:14px;padding:26px;margin:1.5rem 0;color:#eee}
    .hoosh-box p{color:#ccc;font-size:14px;line-height:1.9;margin-bottom:.8rem}
    .hoosh-avatar{width:60px;height:60px;border-radius:50%;flex-shrink:0;border:2px solid var(--gold);background:linear-gradient(135deg,#2a2416,var(--gold-dark));display:flex;align-items:center;justify-content:center;font-weight:800;color:#111}
    .hoosh-btn{display:inline-flex;align-items:center;gap:7px;background:var(--gold);color:#1a1a1a;font-size:14px;font-weight:600;letter-spacing:.01em;padding:11px 22px;border-radius:8px;margin-top:8px;transition:.2s}
    .hoosh-btn:hover{background:#c9a227;color:#1a1a1a;transform:translateY(-2px)}
    /* عکس نویسنده (هیرو صفحه‌ی درباره) — برش طبیعی داخل دایره بدون کشیدگی */
    .hoosh-avatar--photo{background-size:cover;background-position:center;background-repeat:no-repeat}

    /* ===== پرسش‌های متداول — آکاردئون نیتیو (details/summary): دسترس‌پذیر، بدون JS ===== */
    .faq-section{margin:1.8rem 0}
    .faq-section h2{font-size:20px;font-weight:700;color:#222;margin-bottom:1rem}
    .faq-item{border:1px solid #e8e3d5;border-radius:10px;margin-bottom:10px;background:#fff;overflow:hidden}
    .faq-item summary{cursor:pointer;padding:14px 18px;font-weight:600;font-size:15px;color:#2a2a2a;list-style:none;display:flex;justify-content:space-between;align-items:center;gap:12px}
    .faq-item summary::-webkit-details-marker{display:none}
    .faq-item summary::after{content:"+";font-size:20px;line-height:1;color:var(--gold-dark,#c09d4c);flex-shrink:0;transition:transform .2s}
    .faq-item[open] summary::after{content:"\2212"}
    .faq-item summary:hover{color:var(--gold-dark,#c09d4c)}
    .faq-answer{padding:0 18px 16px;font-size:14px;line-height:1.9;color:#555;white-space:pre-line}

    .related-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:16px;margin-top:16px}
    @@media (max-width:820px){.related-grid{grid-template-columns:1fr}}
    .related-card{border:1px solid #eee;display:block}
    .related-thumb{height:130px;background:linear-gradient(135deg,#d8d3c4,#d9bb75);background-size:cover;background-position:center;display:flex;align-items:center;justify-content:center}
    .related-card h4{font-size:14px;padding:10px 10px 4px;color:#3a3a3a}
    .related-card p{font-size:12px;color:#666;padding:0 10px 10px}

    .sidebar-last-item{display:flex;gap:12px;border-bottom:1px dashed #d8d8d8;padding:12px 0}
    .sidebar-last-item .thumb{width:70px;height:60px;flex-shrink:0;border-radius:4px;background:linear-gradient(135deg,#d8d3c4,#d9bb75);background-size:cover;background-position:center}
    .sidebar-last-item h5{font-size:14px;color:#3a3a3a;margin-bottom:4px}
    .sidebar-last-item span{font-size:11px;color:#999}
</style>
@endsection

@section('content')
<div id="reading-progress"></div>

<div class="site-blog-post">
    <div class="wrap">
        <div class="site-blog-post__path">
            <a href="{{ url('/tr') }}">Ana Sayfa</a><span class="sep">/</span>
            <a href="{{ url('/tr/blog') }}">Blog</a><span class="sep">/</span>
            <span>{{ $article->title }}</span>
        </div>

        <div class="site-blog-post__box">
            <div>
                <div class="post-hero-image">
                    @if($article->image_path)
                    <img src="{{ $article->optimized_image_url ?? asset('storage/' . $article->image_path) }}" alt="{{ $article->image_alt ?: $article->title }}" width="800" height="450" loading="eager" decoding="async">
                    @endif
                </div>

                <div class="post-title"><h1>{{ $article->title }}</h1></div>

                <div class="article-meta">
                    <span>👤 {{ $article->author_name }}</span>
                    <span>📅 {{ optional($article->published_at)->format('F Y') }}</span>
                    @if($article->reading_time)
                    <span>⏱ {{ $article->reading_time }} dakika okuma</span>
                    @endif
                    <span>👁 {{ number_format($article->views) }} görüntülenme</span>
                    @if($translation)
                    <span class="lang-switch">
                        <a href="{{ url($translation->path()) }}">
                            {{ $translation->locale === 'en' ? 'English →' : 'Türkçe →' }}
                        </a>
                    </span>
                    @endif
                </div>

                <div class="article-body" id="article-content">
                    {{-- محتوا در ورودیِ AI Import هم پاک‌سازی می‌شود؛ این‌جا فقط یک لایه‌ی دفاعیِ
                         اضافه است (برای محتوای قدیمی یا ویرایش دستی) --}}
                    {!! app(\App\Services\Content\EmbedRenderer::class)->render(\Illuminate\Support\Str::sanitizeHtml($article->body)) !!}
                </div>

                @if($faqs->isNotEmpty())
                <section class="faq-section reveal" aria-label="Sıkça Sorulan Sorular">
                    <h2>Sıkça Sorulan Sorular</h2>
                    @foreach($faqs as $faq)
                    <details class="faq-item">
                        <summary>{{ $faq['question'] }}</summary>
                        <div class="faq-answer">{{ $faq['answer'] }}</div>
                    </details>
                    @endforeach
                </section>
                @endif

                <div class="share-box reveal">
                    <h3>Bu makaleyi paylaş</h3>
                    <div class="share-buttons">
                        <a href="https://t.me/share/url?url={{ urlencode(url('/tr/blog/' . $article->slug)) }}&text={{ urlencode($article->title) }}" target="_blank" rel="noopener" class="share-btn share-btn-tg">Telegram</a>
                        <a href="https://wa.me/?text={{ urlencode($article->title . ' ' . url('/tr/blog/' . $article->slug)) }}" target="_blank" rel="noopener" class="share-btn share-btn-wa">WhatsApp</a>
                        <button class="share-btn share-btn-cp" onclick="copyLink(this)" data-url="{{ url('/tr/blog/' . $article->slug) }}">Bağlantıyı kopyala</button>
                    </div>
                </div>

                <div class="hoosh-box reveal">
                    <div style="display:flex;align-items:center;gap:16px;margin-bottom:18px">
                        <div class="hoosh-avatar @if($authorPhoto) hoosh-avatar--photo @endif"
                             @if($authorPhoto) style="background-image:url('{{ asset('storage/' . $authorPhoto) }}')" @endif
                             role="img" aria-label="Ehsan Dibazar">{{ $authorPhoto ? '' : 'ED' }}</div>
                        <div>
                            <div style="font-size:16px;font-weight:800;color:#fff">Merhaba, ben Ehsan Dibazar</div>
                            <div style="font-size:12px;color:var(--gold);margin-top:3px">Dövüş Sanatları ve Kendini Savunma Eğitmeni · Spor Bilimleri Yüksek Lisansı</div>
                        </div>
                    </div>
                    <p>
                        Yıllardır hayatımın önemli bir parçası dövüş sanatları oldu. Ancak benim için
                        madalyalardan, sertifikalardan ve müsabakalardan daha değerli olan şey,
                        insanların gelişimine katkı sağlamaktır.
                    </p>
                    <p>
                        Dövüş sanatlarının yalnızca dövüşmek için olduğuna inanmıyorum. Doğru şekilde
                        öğretildiğinde özgüven kazandırır, karar verme becerisini geliştirir, baskı
                        altında sakin kalmayı öğretir ve insanların zor durumlarda daha doğru hareket
                        etmelerine yardımcı olur.
                    </p>
                    <a href="{{ url('/tr/about') }}" class="hoosh-btn">Ehsan Dibazar hakkında daha fazla bilgi</a>
                </div>

                @if($related->isNotEmpty())
                <div class="site-blog__sidebar__item__header">
                    <fieldset style="border:0;border-bottom:4px solid var(--gold);padding:0 0 8px">
                        <legend style="font-size:17px;font-weight:600;letter-spacing:.01em;color:#000;padding:0">İlgili Makaleler</legend>
                    </fieldset>
                </div>
                <div class="related-grid reveal-group">
                    @foreach($related as $rel)
                    <a href="{{ url('/tr/blog/' . $rel->slug) }}" class="related-card reveal">
                        <div class="related-thumb" @if($rel->image_path) style="background-image:url('{{ $rel->optimized_image_url ?? asset('storage/' . $rel->image_path) }}')" @endif></div>
                        <h4>{{ $rel->title }}</h4>
                        <p>{{ Str::limit($rel->excerpt, 80) }}</p>
                    </a>
                    @endforeach
                </div>
                @endif
            </div>

            {{-- ============ سایدبار ============ --}}
            <aside>
                @if($latest->isNotEmpty())
                <div class="site-blog__sidebar__item__header">
                    <fieldset style="border:0;border-bottom:4px solid var(--gold);padding:0 0 8px">
                        <legend style="font-size:17px;font-weight:600;letter-spacing:.01em;color:#000;padding:0">Son Makaleler</legend>
                    </fieldset>
                </div>
                <div class="reveal-group" style="margin-top:10px">
                    @foreach($latest as $item)
                    <div class="sidebar-last-item reveal">
                        <div class="thumb" @if($item->image_path) style="background-image:url('{{ $item->optimized_image_url ?? asset('storage/' . $item->image_path) }}')" @endif></div>
                        <div>
                            <h5><a href="{{ url('/tr/blog/' . $item->slug) }}" style="color:#3a3a3a">{{ $item->title }}</a></h5>
                            <span>{{ optional($item->published_at)->format('F Y') }}</span>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </aside>
        </div>
    </div>
</div>
@endsection

@section('page-js')
<script>
(function progressBar() {
    var bar = document.getElementById('reading-progress');
    if (!bar) return;
    function update() {
        var s = window.pageYOffset || document.documentElement.scrollTop;
        var d = document.documentElement.scrollHeight - window.innerHeight;
        bar.style.width = (d > 0 ? (s / d * 100) : 0) + 'vw';
        requestAnimationFrame(update);
    }
    requestAnimationFrame(update);
})();

function copyLink(btn) {
    var url = btn.getAttribute('data-url');
    if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(function () { showCopied(btn); });
    } else {
        var ta = document.createElement('textarea');
        ta.value = url; document.body.appendChild(ta); ta.select();
        document.execCommand('copy'); document.body.removeChild(ta);
        showCopied(btn);
    }
}
function showCopied(btn) {
    btn.classList.add('copied');
    var original = btn.textContent;
    btn.textContent = 'Kopyalandı!';
    setTimeout(function () { btn.classList.remove('copied'); btn.textContent = original; }, 2500);
}
</script>
@endsection
