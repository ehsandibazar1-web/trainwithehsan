@extends('layouts.master-tr')

@php($about = $about ?? [])
@php($stats = $stats ?? [])
@php($certificates = $certificates ?? [])
@php($gallery = $gallery ?? [])
@php($timeline = $timeline ?? [])
@php($v = fn($k, $d = '') => (($about[$k] ?? null) !== null && ($about[$k] ?? '') !== '') ? $about[$k] : $d)
{{-- URLِ بهینه‌ی تصویر: WebPِ مشتقِ کتابخانه‌ی رسانه اگر موجود باشد، وگرنه فایلِ اصلی (Section 21) --}}
@php($optImg = fn($path) => \App\Models\Media::optimizedUrl($path))

@section('title', $v('seo_title', 'Ehsan Dibazar | Muay Thai ve Kendini Savunma Eğitmeni'))
@section('meta_description', $v('seo_description', 'Ehsan Dibazar — 12 yıllık eğitim deneyimine sahip Muay Thai, Brezilya Jiu-Jitsu ve kendini savunma eğitmeni, Bangkok\'tan uluslararası Muay Thai sertifikası ve Spor Fizyolojisi Yüksek Lisansı sahibi.'))
@section('canonical', url('/tr/about'))
@section('og_title', $v('seo_title', 'Ehsan Dibazar | Muay Thai ve Kendini Savunma Eğitmeni'))
@section('og_description', $v('seo_description', 'Ehsan Dibazar — 12 yıllık eğitim deneyimine sahip Muay Thai, Brezilya Jiu-Jitsu ve kendini savunma eğitmeni, Bangkok\'tan uluslararası Muay Thai sertifikası ve Spor Fizyolojisi Yüksek Lisansı sahibi.'))
@section('og_image', $v('seo_og_image') ? asset('storage/' . $v('seo_og_image')) : '')
@section('og_image_width', (string) $v('seo_og_image_width', ''))
@section('og_image_height', (string) $v('seo_og_image_height', ''))
@section('og_image_type', $v('seo_og_image_mime', ''))

@section('json-ld')
<script type="application/ld+json">
{
  "@@context": "https://schema.org",
  "@@type": "Person",
  "@@id": "https://trainwithehsan.com/tr/about#person",
  "name": "Ehsan Dibazar",
  "url": "https://trainwithehsan.com/tr/about",
  "jobTitle": "Martial Arts & Self-Defense Instructor",
  "description": "Ehsan Dibazar, dövüş sanatları ve kendini savunma eğitmeni, Spor Bilimleri Yüksek Lisans derecesine sahip, 12 yıllık eğitim deneyimiyle.",
  "alumniOf": {"@@type": "CollegeOrUniversity", "name": "Fenerbahçe University"},
  "knowsAbout": ["Muay Thai", "Kendini Savunma", "Brezilya Jiu-Jitsu", "Korumalık", "Spor Bilimleri"],
  @if($v('hero_image') && $v('hero_image_width') && $v('hero_image_height'))
  "image": {
    "@@type": "ImageObject",
    "contentUrl": @json(asset('storage/' . $v('hero_image'))),
    "url": @json(asset('storage/' . $v('hero_image'))),
    "width": {{ (int) $v('hero_image_width') }},
    "height": {{ (int) $v('hero_image_height') }},
    "caption": @json($v('hero_name', 'Ehsan Dibazar')),
    "creator": {"@@id": "https://trainwithehsan.com/tr/about#person"},
    "license": @json(url('/tr/terms-and-conditions')),
    "acquireLicensePage": @json(url('/tr/contact')),
    "copyrightNotice": "\u00a9 Ehsan Dibazar",
    "creditText": "Ehsan Dibazar"
  },
  @endif
  "sameAs": [
    "https://www.instagram.com/ehsandibazarcoaching/",
    "https://telegram.me/ehsandibazar",
    "https://youtube.com/channel/UCDT9EOHriR9sHvq0PBdmlog"
  ]
}
</script>
<script type="application/ld+json">
{
  "@@context": "https://schema.org",
  "@@type": "BreadcrumbList",
  "itemListElement": [
    {"@@type": "ListItem", "position": 1, "name": "Home", "item": @json(url('/tr'))},
    {"@@type": "ListItem", "position": 2, "name": "Hakkımda", "item": @json(url('/tr/about'))}
  ]
}
</script>
@endsection

@section('page-css')
<style>
:root{--dark:#0e0e0e;--dark2:#161616}
body{background:var(--dark)!important}
/* overflow-x:hidden بدون overflow-y صریح باعث می‌شد طبق اسپک CSS مرورگر overflow-y را هم auto
   حساب کند؛ چون افکت‌های تزئینی (blur/transform روی .glow و مشابه) چند پیکسل از قاب بیرون
   می‌زدند، کل این wrapper یک اسکرول عمودیِ داخلیِ ناخواسته پیدا می‌کرد — دقیقاً همان باگی که
   روی کاروسل‌های صفحه‌ی اصلی بود، این‌جا برای کل صفحه‌ی درباره‌ی من */
.about-v5{overflow-x:hidden;overflow-y:hidden;max-width:100%;width:100%;background:var(--dark);color:#eee}
.about-v5 *{box-sizing:border-box}
.about-v5 section{padding:70px 20px;position:relative}
.about-v5 .hero + section{padding-top:30px}
.about-v5 .container{max-width:920px;margin:0 auto}
.about-v5 h1,.about-v5 h2{margin:0;color:#fff}

/* ===== هیرو ===== */
.about-v5 .hero{min-height:50vh;padding:40px 20px 40px;display:flex;flex-direction:column;align-items:center;justify-content:flex-start;text-align:center;position:relative}
@media(min-width:992px){.about-v5 .hero{padding-top:120px}}
.about-v5 .glow{position:absolute;width:min(360px,90vw);height:min(360px,90vw);border-radius:50%;background:radial-gradient(circle,rgba(217,187,117,.35),transparent 70%);filter:blur(10px);top:8%;left:50%;transform:translateX(-50%);animation:pulse 6s ease-in-out infinite}
@keyframes pulse{0%,100%{opacity:.6;transform:translateX(-50%) scale(1)}50%{opacity:1;transform:translateX(-50%) scale(1.15)}}
.about-v5 .hero-photo-wrap{width:140px;height:140px;perspective:600px;margin-bottom:28px;position:relative;z-index:2}
.about-v5 .hero-photo{width:100%;height:100%;border-radius:50%;object-fit:cover;border:3px solid var(--gold);transition:transform .15s ease-out;box-shadow:0 20px 50px rgba(0,0,0,.5)}
.about-v5 .hero h1{font-size:34px;font-weight:800;position:relative;z-index:2;opacity:0;animation:fadeUp .7s ease forwards .15s}
.about-v5 .hero .sub{color:var(--gold);font-weight:600;margin:10px 0 18px;font-size:15.5px;position:relative;z-index:2;opacity:0;animation:fadeUp .7s ease forwards .3s}
.about-v5 .hero .txt{max-width:600px;line-height:2;color:#cfcfcf;font-size:14.5px;position:relative;z-index:2;opacity:0;animation:fadeUp .7s ease forwards .45s}
.about-v5 .hero-cta{position:relative;z-index:2;opacity:0;animation:fadeUp .7s ease forwards .5s}
.about-v5 .stat-row{display:flex;gap:14px;justify-content:center;margin-top:34px;flex-wrap:wrap;position:relative;z-index:2}
.about-v5 .glass{background:rgba(255,255,255,.06);backdrop-filter:blur(12px);border:1px solid rgba(217,187,117,.35);border-radius:16px;padding:16px 22px;text-align:center;min-width:130px;opacity:0;animation:fadeUp .6s ease forwards}
.about-v5 .glass:nth-child(1){animation-delay:.6s}
.about-v5 .glass:nth-child(2){animation-delay:.75s}
.about-v5 .glass:nth-child(3){animation-delay:.9s}
@keyframes fadeUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
.about-v5 .glass b{display:block;color:var(--gold);font-size:22px}
.about-v5 .glass span{font-size:12px;color:#ccc}

/* ===== تایم‌لاین ===== */
.about-v5 .tl-wrap h2{text-align:center;font-size:24px;font-weight:700;margin-bottom:50px}
.about-v5 .tl-wrap h2::after{content:"";display:block;width:60px;height:3px;background:var(--gold);margin:14px auto 0;border-radius:3px}
.about-v5 .tl{position:relative;padding-left:26px;border-left:2px solid #2a2a2a;max-width:560px;margin:0 auto}
.about-v5 .tl-item{position:relative;padding-bottom:38px;opacity:0;transform:translateY(40px);transition:opacity .7s ease-out,transform .7s ease-out}
.about-v5 .tl-item.show{opacity:1;transform:translateY(0)}
.about-v5 .tl-item::before{content:"";position:absolute;left:-33px;top:3px;width:14px;height:14px;border-radius:50%;background:var(--gold);box-shadow:0 0 0 4px rgba(217,187,117,.2)}
.about-v5 .tl-year{color:var(--gold);font-weight:800;font-size:17px}
.about-v5 .tl-label{font-weight:600;font-size:14.5px;margin:2px 0 4px;color:#fff}
.about-v5 .tl-desc{font-size:13px;color:#999;line-height:1.8}

/* ===== مدارک ===== */
.about-v5 .gallery h2{text-align:center;font-size:24px;font-weight:700;margin-bottom:40px}
.about-v5 .gallery h2::after{content:"";display:block;width:60px;height:3px;background:var(--gold);margin:14px auto 0}
.about-v5 .masonry{column-count:1;column-gap:14px}
@media(min-width:640px){.about-v5 .masonry{column-count:2}}
.about-v5 .cred{break-inside:avoid;margin-bottom:14px;position:relative;border-radius:14px;overflow:hidden;cursor:pointer;transition:transform .3s,box-shadow .3s;aspect-ratio:4/3;background:linear-gradient(135deg,#2a2416,#8a6d1f)}
.about-v5 .cred:hover{transform:translateY(-6px);box-shadow:0 20px 40px rgba(217,187,117,.25)}
.about-v5 .cred .cap{position:absolute;bottom:0;right:0;left:0;background:linear-gradient(0deg,rgba(0,0,0,.9),transparent 80%);color:#fff;padding:20px 10px 8px;font-size:12px;font-weight:600}
/* اسکرول‌ریویل روی کارت‌های مدرک/گالری: چون .cred خودش transition برای hover دارد،
   با اختصاصیت برابر/بالاتر override می‌کنیم تا opacity هم انیمیت شود و افکت هاور سریع بماند */
.about-v5 .cred.reveal{transition:opacity .7s ease-out,transform .7s ease-out,box-shadow .3s ease}
.about-v5 .cred.reveal:hover{transition:transform .3s ease,box-shadow .3s ease}

/* ===== CTA مگنتیک ===== */
.about-v5 .cta{background:linear-gradient(135deg,#1a1a1a,#000);text-align:center;background-size:cover;background-position:center}
.about-v5 .cta h3{font-size:20px;font-weight:700;margin-bottom:20px;color:#fff}
.about-v5 .cta p{color:#ccc;font-size:14px;line-height:1.8;max-width:480px;margin:0 auto 20px}
.about-v5 .magnetic-wrap{display:flex;justify-content:center}
.about-v5 .magnetic{position:relative;display:inline-flex;align-items:center;gap:8px;background:var(--gold);color:#111;padding:14px 34px;border-radius:32px;font-weight:600;letter-spacing:.01em;text-decoration:none;font-size:15px;transition:transform .15s ease-out}

@media (prefers-reduced-motion: reduce){
    .about-v5 .glow{animation:none}
    .about-v5 .hero-photo{transition:none}
    .about-v5 .tl-item{transition:none;opacity:1;transform:none}
    .about-v5 .cred{transition:none}
    .about-v5 .cred:hover{transform:none}
    .about-v5 .magnetic{transition:none}
    .about-v5 .hero h1,.about-v5 .hero .sub,.about-v5 .hero .txt,.about-v5 .hero-cta,.about-v5 .glass{animation:none;opacity:1}
}
</style>
@endsection

@section('content')
<div class="about-v5">
<main>

    {{-- ============ هیرو ============ --}}
    <header class="hero">
        <div class="glow"></div>
        <div class="hero-photo-wrap">
            <img id="heroPhoto" class="hero-photo" src="{{ $v('hero_image') ? $optImg($v('hero_image')) : "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='140' height='140'%3E%3Crect width='140' height='140' fill='%232a2416'/%3E%3C/svg%3E" }}" alt="{{ $v('hero_name', 'Ehsan Dibazar') }}" fetchpriority="high" decoding="async">
        </div>
        <h1>{{ $v('hero_name', 'Ehsan Dibazar') }}</h1>
        <div class="sub">{{ $v('hero_title', 'Dövüş sanatları ve kendini savunma eğitmeni, Spor Bilimleri Yüksek Lisansı') }}</div>
        <div class="txt">
            {{ $v('hero_bio', 'Dövüş sanatları öğretmek ve insanların kendini savunma becerisini geliştirmek benim için her zaman en anlamlı işlerden biri oldu. İnsanların daha güçlü olmasına yardımcı olmak hayatıma anlam katıyor. En büyük önceliğim, özellikle başlangıç seviyesindekiler için dövüş sanatları ve kendini savunma eğitiminin kalitesini yükseltmek — böylece daha fazla keyif ve süreklilikle devam edip hayatlarında gerçek ve faydalı sonuçlar elde edebilsinler. Doğru şekilde çalışıldığında dövüş sanatlarının insanların hayatını gerçekten iyileştirdiğine inanıyorum.') }}
        </div>
        @if($v('hero_cta_text') && $v('hero_cta_url'))
        <div class="hero-cta">
            <a class="magnetic" href="{{ $v('hero_cta_url') }}">{{ $v('hero_cta_text') }}</a>
        </div>
        @endif
        @php($statsList = !empty($stats) ? $stats : [
            ['value' => '12+', 'label' => 'Yıllık eğitim deneyimi'],
            ['value' => 'Binlerce', 'label' => 'yüz yüze ve online öğrenci'],
            ['value' => 'Çeşitli', 'label' => 'uluslararası sertifikalar'],
        ])
        <div class="stat-row">
            @foreach($statsList as $stat)
            <div class="glass"><b>{{ $stat['value'] ?? '' }}</b><span>{{ $stat['label'] ?? '' }}</span></div>
            @endforeach
        </div>
    </header>

    {{-- ============ مدارک و افتخارات ============ --}}
    <section class="gallery" aria-label="{{ $v('certs_heading', 'Sertifikalar ve Başarılar') }}">
        <div class="container">
            <h2>{{ $v('certs_heading', 'Sertifikalar ve Başarılar') }}</h2>
            @php($certList = !empty($certificates) ? $certificates : [
                ['title' => 'Brezilya Jiu-Jitsu kendini savunma sertifikası, ABD'],
                ['title' => 'Muay Thai teknik sertifikası, Tayland Milli Eğitim Bakanlığı'],
                ['title' => 'Koruma diploması, Türk Askeri Akademisi'],
                ['title' => 'Temel Koruma Diploması'],
                ['title' => "Türkiye'de koruma sertifikasını aldıktan sonra"],
                ['title' => 'Muay Thai teknik sınavını geçtikten sonra eğitmenle, Tayland'],
                ['title' => "Brezilya Jiu-Jitsu Dünya Şampiyonası'nda rakibiyle, Rusya"],
                ['title' => "Brezilya Jiu-Jitsu Dünya Şampiyonası'nda, Rusya"],
                ['title' => 'Muay Thai teknik sınav belgesi, Bangkok Muay Thai Üniversitesi'],
                ['title' => 'Muay Thai eğitim sertifikası, İstanbul'],
                ['title' => "Tahran Üniversitesi Beden Eğitimi Fakültesi'nde atölye çalışması"],
                ['title' => 'Muay Boran online seminerine katılım, ABD'],
            ])
            <div class="masonry reveal-group" id="masonry">
                @foreach($certList as $cert)
                @php($capText = implode(' — ', array_filter([$cert['title'] ?? null, $cert['subtitle'] ?? null, $cert['description'] ?? null])))
                <figure class="cred reveal" data-cap="{{ $capText }}" tabindex="0" role="button" @if(!empty($cert['image'])) data-full="{{ $optImg($cert['image']) }}" style="background:url('{{ $optImg($cert['image']) }}') center/cover no-repeat" @endif><figcaption class="cap">{{ $capText }}</figcaption></figure>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ============ گالری تصاویر (اختیاری — فقط وقتی حداقل یک عکس تنظیم شده باشد) ============ --}}
    @if(!empty($gallery))
    <section class="gallery" aria-label="{{ $v('gallery_heading', 'Galeri') }}">
        <div class="container">
            <h2>{{ $v('gallery_heading', 'Galeri') }}</h2>
            <div class="masonry reveal-group">
                @foreach($gallery as $img)
                @continue(empty($img['image']))
                <figure class="cred reveal" data-cap="{{ $img['alt'] ?? '' }}" tabindex="0" role="button" data-full="{{ $optImg($img['image']) }}" style="background:url('{{ $optImg($img['image']) }}') center/cover no-repeat"><figcaption class="cap">{{ $img['alt'] ?? '' }}</figcaption></figure>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    {{-- ============ تایم‌لاین ============ --}}
    <section class="tl-wrap">
        <div class="container">
            <h2>{{ $v('timeline_heading', 'Yolculuğum') }}</h2>
            @php($timelineList = !empty($timeline) ? $timeline : [
                ['year' => '2013', 'title' => 'Koruma ve Kendini Savunma Sertifikası', 'description' => 'Aylarca süren eğitim ve çabanın ardından, dönemin İstanbul yetkililerinden koruma ve kendini savunma sertifikası aldım — dövüş sanatları ve kendini savunma öğretme tutkumu daha da alevlendiren bir yolculuğun başlangıcı. En iyi dövüş sanatları koçları eşliğinde kendi eğitimime devam ederken, bu alana ilgi duyan diğer kişilere de eğitim vermeye başladım.'],
                ['year' => '2016', 'title' => 'Dövüş Sanatları Salonu Açtım', 'description' => 'Kendi özel dövüş sanatları salonumu açtım ve bununla birlikte bir turizm ve doğa gezileri ofisi kurdum. Her iki alandaki kesintisiz çalışmam — özellikle birçok öğrenciye eğitim vermem — sonunda beni büyük bir başarıya taşıdı.'],
                ['year' => '2019', 'title' => 'Muay Thai Teknik Sertifikası, Bangkok', 'description' => "Koç ve salon sahibi olarak yoğun çalışmaların ardından, Bangkok Muay Thai Üniversitesi'nden Muay Thai teknik sertifikamı aldım. Tayland'da yaşamak, antrenman yapmak ve orada müsabakalara katılmak, kendi salonumda verdiğim eğitimin kalitesini önemli ölçüde yükseltti. Aynı zamanda Tahran Üniversitesi Beden Eğitimi Fakültesi'nde spor bilimi ve koçluk eğitimi aldım."],
                ['year' => '2022', 'title' => 'Brezilya Jiu-Jitsu Kendini Savunma Teknik Sertifikası, Kaliforniya', 'description' => 'Amerika Birleşik Devletleri\'ndeki en saygın kendini savunma kurumlarından birinden çok değerli bir teknik sertifika aldım ve öğrencilerimi de bu yolda yanımda getirebildim. Bu işe olan sevgim, derslerimde zamanın nasıl geçtiğini hiç fark etmememi sağladı.'],
                ['year' => '2024', 'title' => 'Spor Fizyolojisi Yüksek Lisansı', 'description' => "Modern spor bilimini dövüş sanatlarıyla birleştirme hedefiyle, İstanbul Fenerbahçe Üniversitesi'nde Spor Fizyolojisi alanında yüksek lisans eğitimine başladım ve sportif antrenmanlarda fizyolojik göstergelerin değerlendirilmesine odaklandım."],
            ])
            <div class="tl" id="tlList">
                @foreach($timelineList as $item)
                <div class="tl-item">
                    <div class="tl-year">{{ $item['year'] ?? '' }}</div>
                    <div class="tl-label">{{ $item['title'] ?? '' }}</div>
                    <div class="tl-desc">{{ $item['description'] ?? '' }}</div>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ============ CTA مگنتیک ============ --}}
    <section class="cta reveal" @if($v('cta_bg_image')) style="background-image:linear-gradient(135deg,rgba(26,26,26,.85),rgba(0,0,0,.85)),url('{{ $optImg($v('cta_bg_image')) }}')" @endif>
        @if($v('cta_title'))
        <h3>{{ $v('cta_title') }}</h3>
        @endif
        @if($v('cta_description'))
        <p>{{ $v('cta_description') }}</p>
        @endif
        <div class="magnetic-wrap">
            <a class="magnetic" id="magneticBtn" href="{{ $v('cta_button_url', 'https://www.instagram.com/ehsandibazarcoaching') }}" target="_blank" rel="noopener">{{ $v('cta_button_text', "Instagram’da takip edin") }}</a>
        </div>
    </section>

</main>
</div>

{{-- مودال نمایش بزرگ مدرک --}}
<div class="modal" id="modal" role="dialog" aria-modal="true" aria-label="Büyütülmüş sertifika görünümü" style="position:fixed;inset:0;background:rgba(0,0,0,.9);display:none;align-items:center;justify-content:center;z-index:1000;padding:20px">
    <button class="modal-close" id="modalClose" aria-label="Kapat" style="position:absolute;top:20px;left:20px;color:#fff;font-size:30px;background:none;border:none;cursor:pointer">&times;</button>
    <div>
        <img id="modalImg" alt="" style="max-width:100%;max-height:80vh;border-radius:10px;display:none;margin:0 auto">
        <div class="cap2" id="modalCap" style="color:var(--gold);text-align:center;margin-top:14px;font-size:15px;font-weight:700"></div>
    </div>
</div>
@endsection

@section('page-js')
<script>
(function () {
    var isTouch = matchMedia('(pointer:coarse)').matches;
    var reduceMotion = matchMedia('(prefers-reduced-motion: reduce)').matches;

    // ===== تایم‌لاین: نمایش تدریجی هنگام اسکرول =====
    var tlItems = document.querySelectorAll('.tl-item');
    if ('IntersectionObserver' in window) {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) { if (en.isIntersecting) en.target.classList.add('show'); });
        }, { threshold: .3 });
        tlItems.forEach(function (i) { io.observe(i); });
    } else {
        tlItems.forEach(function (i) { i.classList.add('show'); });
    }

    // ===== مودال مدارک/گالری =====
    var modal = document.getElementById('modal');
    var modalCap = document.getElementById('modalCap');
    var lastFocused = null;
    document.querySelectorAll('.cred').forEach(function (c) {
        c.addEventListener('click', function () {
            lastFocused = document.activeElement;
            var modalImg = document.getElementById('modalImg');
            if (c.dataset.full) { modalImg.src = c.dataset.full; modalImg.alt = c.dataset.cap || ''; modalImg.style.display = 'block'; }
            else { modalImg.removeAttribute('src'); modalImg.style.display = 'none'; }
            modalCap.textContent = c.dataset.cap || '';
            modal.classList.add('open');
            modal.style.display = 'flex';
            document.getElementById('modalClose').focus();
        });
        c.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); c.click(); }
        });
    });
    var modalCloseBtn = document.getElementById('modalClose');
    modalCloseBtn && modalCloseBtn.addEventListener('click', closeModal);
    modal && modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal && modal.style.display === 'flex') closeModal();
    });
    function closeModal() {
        if (!modal) return;
        modal.style.display = 'none';
        modal.classList.remove('open');
        if (lastFocused) lastFocused.focus();
    }

    // ===== دکمه مگنتیک =====
    document.querySelectorAll('.magnetic').forEach(function (btn) {
        if (!isTouch && !reduceMotion) {
            btn.addEventListener('mousemove', function (e) {
                var r = btn.getBoundingClientRect();
                var x = e.clientX - r.left - r.width / 2;
                var y = e.clientY - r.top - r.height / 2;
                btn.style.transform = 'translate(' + (x * 0.3) + 'px,' + (y * 0.3) + 'px)';
            });
            btn.addEventListener('mouseleave', function () { btn.style.transform = 'translate(0,0)'; });
        }
    });
})();
</script>
@endsection
