@extends('layouts.master-tr')

{{-- فقط پرسش‌های کامل (سؤال و پاسخِ هر دو پرشده) — هم برای نمایش، هم برای JSON-LD --}}
@php($faqs = collect($page->faqs ?? [])->filter(fn ($f) => filled($f['question'] ?? null) && filled($f['answer'] ?? null))->values())

@section('title', ($page->seo_title ?: $page->title) . ' — Ehsan Dibazar')
@section('meta_description', $page->meta_description ?: Str::limit(trim(strip_tags($page->body)), 150))
@section('meta_keywords', $page->meta_keywords ?: '')
@section('canonical', $page->canonical_url ?: url('/tr/' . $page->slug))
@section('robots', $page->robots ?: 'index,follow')
@section('og_title', ($page->og_title ?: $page->seo_title ?: $page->title) . ' — Ehsan Dibazar')
@section('og_description', $page->og_description ?: $page->meta_description ?: Str::limit(trim(strip_tags($page->body)), 150))
@section('og_image', $page->image_path ? asset('storage/' . $page->image_path) : '')

@section('json-ld')
<script type="application/ld+json">
{
  "@@context": "https://schema.org",
  "@@type": "WebPage",
  "name": @json($page->title),
  "url": @json(url('/tr/' . $page->slug)),
  "dateModified": @json(optional($page->updated_at)->toIso8601String()),
  "isPartOf": {"@@id": "https://trainwithehsan.com/#organization"}
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
@endsection

@section('page-css')
<style>
    .site-page{background-color:#f6f6f6;padding:0 0 50px}
    .site-page__path{padding:14px 0;font-size:13px;color:#666}
    .site-page__path a{color:#666}
    .site-page__path a:hover{color:var(--gold-dark,#c09d4c)}
    .site-page__path .sep{margin:0 6px;color:#bbb}
    .site-page__box{box-shadow:2px 5px 14px -5px #dbdbdb;background:#fff;padding:26px}

    .page-hero-image{
        aspect-ratio:800/450;margin-bottom:14px;border-radius:4px;overflow:hidden;
        background-size:cover;background-position:center;
    }
    .page-title h1{font-weight:700;font-size:26px;color:#222;margin-bottom:10px;line-height:1.4}

    .page-meta{display:flex;flex-wrap:wrap;align-items:center;gap:14px;padding:10px 0;border-top:1px solid #eee;border-bottom:1px solid #eee;margin-bottom:1.2rem;font-size:13px;color:#666}
    .page-meta .lang-switch{margin-left:auto}
    .page-meta .lang-switch a{color:var(--gold-dark,#c09d4c);font-weight:600}

    .page-body p{text-align:justify;font-size:16px;font-weight:300;line-height:2;color:#555;margin-bottom:1.1rem}
    .page-body h2{font-size:20px;font-weight:700;color:#222;margin:2rem 0 1rem}
    .page-body h3{font-size:17px;font-weight:700;color:#333;margin:1.6rem 0 .8rem}
    .page-body img{max-width:100%;height:auto;border-radius:6px;margin:1rem 0}
    .page-body ul,.page-body ol{padding-left:22px;margin-bottom:1.1rem;color:#555}
    .page-body a{color:var(--gold-dark,#c09d4c)}

    /* ===== پرسش‌های متداول — عیناً همان الگوی blog-post.blade.php: آکاردئون نیتیو، بدون JS ===== */
    .faq-section{margin:1.8rem 0}
    .faq-section h2{font-size:20px;font-weight:700;color:#222;margin-bottom:1rem}
    .faq-item{border:1px solid #e8e3d5;border-radius:10px;margin-bottom:10px;background:#fff;overflow:hidden}
    .faq-item summary{cursor:pointer;padding:14px 18px;font-weight:600;font-size:15px;color:#2a2a2a;list-style:none;display:flex;justify-content:space-between;align-items:center;gap:12px}
    .faq-item summary::-webkit-details-marker{display:none}
    .faq-item summary::after{content:"+";font-size:20px;line-height:1;color:var(--gold-dark,#c09d4c);flex-shrink:0;transition:transform .2s}
    .faq-item[open] summary::after{content:"\2212"}
    .faq-item summary:hover{color:var(--gold-dark,#c09d4c)}
    .faq-answer{padding:0 18px 16px;font-size:14px;line-height:1.9;color:#555;white-space:pre-line}

    /* ===== iletişim formu — sadece contact sayfasında render edilir (aşağıdaki koşula bakın) ===== */
    .contact-info{display:flex;flex-wrap:wrap;gap:18px;margin:1.4rem 0;font-size:14px;color:#444}
    .contact-info a{color:var(--gold-dark,#c09d4c);font-weight:600}
    .contact-form{max-width:560px;margin-top:1.6rem}
    .contact-form label{display:block;font-size:13px;font-weight:600;color:#333;margin-bottom:5px}
    .contact-form .field{margin-bottom:16px}
    .contact-form input[type=text],.contact-form input[type=email],.contact-form textarea{
        width:100%;border:1px solid #ddd;border-radius:6px;padding:11px 14px;font-size:14px;
        font-family:inherit;outline:0;
    }
    .contact-form input:focus,.contact-form textarea:focus{border-color:var(--gold-dark,#c09d4c)}
    .contact-form textarea{resize:vertical;min-height:120px}
    .contact-form button{
        border:0;background:var(--gold);color:#000;font-weight:600;padding:12px 30px;
        border-radius:25px;cursor:pointer;font-family:inherit;font-size:14px;transition:.2s;
    }
    .contact-form button:hover{background:#000;color:var(--gold)}
    .contact-form button:disabled{opacity:.6;cursor:default}
    .contact-form-msg{display:none;margin-top:12px;font-size:13px;font-weight:600}
    .contact-form-msg.show{display:block}
    .contact-form-msg.ok{color:#14421c}
    .contact-form-msg.err{color:#7a1414}
</style>
@endsection

@section('content')
<div class="site-page">
    <div class="wrap">
        <div class="site-page__path">
            <a href="{{ url('/tr') }}">Ana Sayfa</a><span class="sep">/</span>
            <span>{{ $page->title }}</span>
        </div>

        <div class="site-page__box">
            @if($page->image_path)
            <div class="page-hero-image" style="background-image:url('{{ $page->optimized_image_url ?? asset('storage/' . $page->image_path) }}')"></div>
            @endif

            <div class="page-title"><h1>{{ $page->title }}</h1></div>

            @if($translation || $page->updated_at)
            <div class="page-meta">
                @if($page->updated_at)
                <span>Son güncelleme: {{ $page->updated_at->format('j F Y') }}</span>
                @endif
                @if($translation)
                <span class="lang-switch">
                    <a href="{{ url($translation->path()) }}">
                        {{ $translation->locale === 'tr' ? 'Türkçe →' : 'English →' }}
                    </a>
                </span>
                @endif
            </div>
            @endif

            <div class="page-body reveal">
                {!! $page->body !!}
            </div>

            @if($page->slug === 'contact')
            {{-- $fv از layouts.master-tr نیست چون بخش‌های فرزند پیش از رندر <head> والد اجرا می‌شوند —
                 همون فیلدهای موجودِ Footer Settings (SiteSetting) اینجا مستقیم خونده می‌شن --}}
            @php($contactSettings = \App\Models\SiteSetting::whereIn('key', ['footer.tr.contact_email', 'footer.tr.contact_phone', 'footer.tr.contact_address'])->pluck('value', 'key'))
            @php($contactEmail = $contactSettings['footer.tr.contact_email'] ?? null)
            @php($contactPhone = $contactSettings['footer.tr.contact_phone'] ?? null)
            @php($contactAddress = $contactSettings['footer.tr.contact_address'] ?? null)
            @if($contactEmail || $contactPhone || $contactAddress)
            <div class="contact-info reveal">
                @if($contactEmail)<span>✉️ <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a></span>@endif
                @if($contactPhone)<span>📞 <a href="tel:{{ preg_replace('/\s+/', '', $contactPhone) }}">{{ $contactPhone }}</a></span>@endif
                @if($contactAddress)<span>📍 {{ $contactAddress }}</span>@endif
            </div>
            @endif

            <form class="contact-form reveal js-contact-form" method="post" action="{{ url('/contact') }}" novalidate
                  data-msg-toomany="{{ __('contact.too_many', [], 'tr') }}"
                  data-msg-error="{{ __('contact.error', [], 'tr') }}">
                <input type="hidden" name="locale" value="tr">
                {{-- سد زمانی: مُهر زمانی رمزشدهٔ لحظهٔ رندر — ارسالِ زودتر از ۳ ثانیه یعنی بات --}}
                <input type="hidden" name="_ct_ts" value="{{ \Illuminate\Support\Facades\Crypt::encryptString((string) now()->timestamp) }}">
                {{-- هانی‌پات: برای انسان‌ها نامرئی است؛ پر شدنش یعنی بات --}}
                <input type="text" name="website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;height:0;width:0;border:0;padding:0">
                <div class="field">
                    <label for="contact-name">Ad Soyad</label>
                    <input type="text" id="contact-name" name="name" required>
                </div>
                <div class="field">
                    <label for="contact-email">E-posta</label>
                    <input type="email" id="contact-email" name="email" required>
                </div>
                <div class="field">
                    <label for="contact-message">Mesaj</label>
                    <textarea id="contact-message" name="message" required></textarea>
                </div>
                <button type="submit">Mesaj gönder</button>
                <div class="contact-form-msg" role="status" aria-live="polite"></div>
            </form>
            @endif

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
        </div>
    </div>
</div>
@endsection

@if($page->slug === 'contact')
@section('page-js')
<script>
    // ===== iletişim formu — newsletter formuyla aynı AJAX deseni (master-tr.blade.php) =====
    (function () {
        document.querySelectorAll('.js-contact-form').forEach(function (form) {
            var msg = form.querySelector('.contact-form-msg');
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
                    if (r.ok) form.reset();
                }).catch(function () {
                    show(form.dataset.msgError, false);
                }).finally(function () {
                    if (btn) btn.disabled = false;
                });
            });
        });
    })();
</script>
@endsection
@endif
