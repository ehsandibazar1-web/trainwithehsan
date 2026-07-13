@extends('layouts.master-tr')

@section('title', 'İstanbul\'da Kendini Savunma ve BJJ Eğitimi — Ehsan Dibazar | Martial Intelligence')
@section('meta_description', 'Ehsan Dibazar ile İstanbul\'da kendini savunmayı öğrenin — Spor Bilimleri Yüksek Lisansı, 15+ yıl deneyim. Başlangıç seviyesi için yüz yüze veya uygulama üzerinden kurslar.')
@section('canonical', url('/tr'))

@section('json-ld')
<script type="application/ld+json">
{
  "@@context": "https://schema.org",
  "@@graph": [
    {
      "@@type": "Organization",
      "@@id": "https://trainwithehsan.com/#organization",
      "name": "Train with Ehsan",
      "url": "https://trainwithehsan.com/tr",
      "founder": { "@@id": "https://trainwithehsan.com/#person" },
      "areaServed": "Istanbul, Türkiye"
    },
    {
      "@@type": "Person",
      "@@id": "https://trainwithehsan.com/#person",
      "name": "Ehsan Dibazar",
      "jobTitle": "Self-Defense & Brazilian Jiu-Jitsu Instructor",
      "description": "Martial arts instructor with an MSc in Sport Science and 15+ years of teaching experience, based in Istanbul.",
      "knowsAbout": ["Self-defense", "Brazilian Jiu-Jitsu", "Martial Intelligence"],
      "url": "https://trainwithehsan.com/tr/about"
    }
  ]
}
</script>
@endsection

@section('page-css')
<style>
    /* ===== اسلایدر — .slider {background:#252525} + متن روی تصویر ===== */
    .hero-slider{position:relative;background:#252525;overflow:hidden}
    /* نسبت واقعی عکس سایت اصلی: 1349x529 — به‌جای vh، از aspect-ratio واقعی استفاده می‌کنیم
       تا روی موبایل هم دقیقاً همون تناسب (نه خیلی بلند، پهن و طبیعی) حفظ بشه */
    .hero-slide{
        display:none;align-items:center;position:relative;
        aspect-ratio:1349/529;min-height:220px;
        background:linear-gradient(115deg,#2e2c28 0%,#1c1b18 55%,#0d0d0b 120%);
    }
    @@media (max-width:640px){.hero-slide{min-height:180px}}
    .hero-slide.active{display:flex}
    /* بافت پس‌زمینه شبیه پوستر تیره — فقط وقتی عکس واقعی نیست */
    .hero-slide::before{
        content:"DIBAZAR";position:absolute;left:-14px;bottom:-4vw;
        font-size:15vw;font-weight:800;color:rgba(255,255,255,.045);
        letter-spacing:.02em;pointer-events:none;line-height:1;
    }
    /* .main-text-slider {font-size:1.8rem; color:#fff} / .main-text-slider2 {color:#f3f3f3; 1.5rem} */
    .hero-slide-text{position:relative;z-index:1;max-width:600px}
    .hero-slide-text h1{font-size:28px;color:#fff;font-weight:600;line-height:1.5;text-shadow:0 2px 10px rgba(0,0,0,.55),0 1px 3px rgba(0,0,0,.7)}
    .hero-slide-text .sub{font-size:18px;color:#f3f3f3;margin-top:10px;line-height:1.6;text-shadow:0 2px 8px rgba(0,0,0,.6),0 1px 3px rgba(0,0,0,.75)}
    @@media (max-width:767px){
        .hero-slide-text h1{font-size:22px}
        .hero-slide-text .sub{font-size:15px}
    }
    /* .slider .owl-dots — نقطه‌ها؛ فعال #d9bb75 */
    .hero-dots{position:absolute;bottom:24px;left:20px;display:flex;justify-content:flex-start;gap:9px;z-index:2}
    .hero-dot{width:18px;height:18px;border-radius:50%;background:#a3a5a8;border:0;cursor:pointer;padding:0}
    .hero-dot.active{background:var(--gold)}

    /* ===== ردیف ویدیو — .row-video {margin-top:-94px} overlap روی اسلایدر ===== */
    .video-section{padding-bottom:40px}
    .row-video{position:relative;z-index:1;margin-top:-94px;display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
    @@media (max-width:991px){.row-video{margin-top:-53px}}
    /* موبایل — دقیقاً مثل سایت اصلی: یکی‌یکی، تمام‌عرض، اسلاید افقی (نه ۳تا فشرده) */
    @@media (max-width:640px){
        .row-video{
            margin-top:-28px;display:flex;gap:15px;padding:0 15px;overflow-x:auto;
            scroll-snap-type:x mandatory;-webkit-overflow-scrolling:touch;scrollbar-width:none;
        }
        .row-video::-webkit-scrollbar{display:none}
        .row-video>.video-card{flex:0 0 85%;scroll-snap-align:start}
        .video-icon{width:52px;height:52px;font-size:18px}
        .text-video{font-size:14px;padding:14px 10px}
    }
    .video-section{background:#fff}
    /* .owl-send .item {height:232px}: عکس بالا با آیکون روش، کپشن جداگانه زیرِ عکس (مثل سایت اصلی) */
    .video-card{display:block;cursor:pointer;background:#fff}
    .video-card__img{
        position:relative;aspect-ratio:389/232;overflow:hidden;
        background:linear-gradient(135deg,#2c2c2c 0%,#3a3222 60%,#8a6d1f 170%);
    }
    .video-icon{
        position:absolute;inset:0;margin:auto;width:47px;height:46px;
        display:flex;align-items:center;justify-content:center;
        background:rgba(0,0,0,.5);border:2px solid #fff;border-radius:50%;
        color:#fff;font-size:16px;z-index:2;transition:.2s;
    }
    .video-card:hover .video-icon{background:var(--gold);border-color:var(--gold);color:#000}
    .text-video{
        padding:12px 6px;text-align:center;font-weight:600;color:#2b2b2b;font-size:14px;
        background:#fff;line-height:1.5;
    }

    /* یکپارچه در همه‌ی سایزها: عکس همیشه چسبیده به پایین-چپ بخش، متن سمت راستش با تورفتگی */
    .about-section{padding:60px 0;position:relative;background:#fff;overflow:hidden;min-height:460px}
    @@media (max-width:640px){.about-section{padding:40px 0;min-height:420px}}
    .about-text-col{max-width:560px;position:relative;z-index:1;margin-left:48%}
    @@media (max-width:640px){.about-text-col{margin-left:56%}}
    .abou-company{color:#393e40;font-weight:800;font-size:2.2rem;margin-bottom:10px;line-height:1.4}
    @@media (max-width:767px){.abou-company{font-size:20px;line-height:2}}
    .sub-title{color:#393e40;font-weight:500;font-size:16px}
    .about-text{color:#3b3b3b;line-height:2.2;font-size:13px;text-align:justify;margin:12px 0 8px}
    .about-cta{margin-top:40px}
    @@media (max-width:640px){.about-cta{margin-top:24px}}
    .about-bleed-img{
        position:absolute;left:0;top:0;bottom:0;width:44%;max-width:469px;
        height:100%;object-fit:cover;object-position:left bottom;
    }
    @@media (max-width:640px){.about-bleed-img{width:52%;max-width:280px}}
    .img-about-box{
        position:absolute;left:0;top:0;bottom:0;width:44%;max-width:469px;
        background:linear-gradient(135deg,#f0ede4 0%,#e2d3a8 70%,var(--gold) 160%);
        display:flex;align-items:flex-end;padding:20px;
    }
    @@media (max-width:640px){.img-about-box{width:52%;max-width:280px}}
    .img-about-box span{font-weight:800;font-size:34px;color:rgba(0,0,0,.18)}

    /* ===== دوره‌ها — .counter {background:#363636; min-height:508px; color:#fff} ===== */
    .counter{background:#363636;min-height:508px;color:#fff;padding:50px 0 60px}
    /* .title-counter {font-size:2rem} centered */
    .title-counter{font-size:2rem;text-align:center;color:#fff;font-weight:700}
    @@media (max-width:767px){.title-counter{font-size:1.5rem}}
    .sun-counter{text-align:center;color:#ddd;font-size:14px;margin-top:8px;max-width:44rem;margin-left:auto;margin-right:auto}
    .courses-carousel{position:relative;margin-top:40px}
    .learn-grid{
        display:flex;gap:20px;overflow-x:auto;
        scroll-snap-type:x mandatory;-webkit-overflow-scrolling:touch;
        padding-bottom:10px;scrollbar-width:thin;
    }
    .learn-grid::-webkit-scrollbar{height:6px}
    .learn-grid::-webkit-scrollbar-thumb{background:rgba(255,255,255,.25);border-radius:3px}
    /* فلش‌ها روی این بخش تیره‌ست — رنگ‌شان را روشن می‌کنیم */
    .courses-carousel .car-arrow{color:#ccc}
    .courses-carousel .car-arrow:hover{color:var(--gold)}
    /* .img-learn (تصویر 362×241) + .l-title {color:#1e1e1e; background:#d9bb75; min-height:50px; 15px} */
    .l-box{display:block;flex:0 0 260px;scroll-snap-align:start}
    @@media (max-width:600px){.l-box{flex-basis:85%}}
    .img-learn{
        position:relative;overflow:hidden;aspect-ratio:362/241;
        background:linear-gradient(135deg,#4a4a4a 0%,#5d5137 60%,#8a6d1f 170%);
        display:flex;align-items:center;justify-content:center;
    }
    .img-learn b{font-weight:800;font-size:24px;color:rgba(255,255,255,.35);letter-spacing:.04em}
    /* افکت هاور سفید wipe مثل .img-learn::before/::after */
    .img-learn::before{
        content:"";position:absolute;inset:0;background:#fff;opacity:0;
        transform:scaleX(1);transform-origin:90%;transition:.5s;z-index:2;
        transform:scaleX(0);
    }
    .l-box:hover .img-learn::before{transform:scaleX(1);opacity:.25}
    .l-title{
        display:block;color:#1e1e1e;background-color:var(--gold);font-weight:500;
        font-size:15px;min-height:50px;padding-top:11px;text-align:center;
    }

    /* ===== مقالات — .section-news {background:#e1e1e1} + کارت سفید ===== */
    .section-news{background:#e1e1e1;padding:56px 0 64px}
    /* .title-section {color:#000; font-size:20px; font-weight:500} */
    .title-section{color:#000;font-size:20px;font-weight:500;text-align:center;line-height:1.5}
    /* .sub-title-section a {color:#353535; font-weight:500; 15px} */
    .sub-title-section{text-align:center;margin-top:8px}
    .sub-title-section a{color:#353535;font-weight:500;font-size:15px}
    .articles-carousel{position:relative;margin-top:32px}
    .articles-carousel .car-arrow{color:#555}
    .articles-carousel .car-arrow:hover{color:var(--gold-dark,#c09d4c)}
    .news-grid{
        display:flex;gap:20px;overflow-x:auto;
        scroll-snap-type:x mandatory;-webkit-overflow-scrolling:touch;
        padding-bottom:10px;scrollbar-width:thin;
    }
    .news-grid::-webkit-scrollbar{height:6px}
    .news-grid::-webkit-scrollbar-thumb{background:rgba(0,0,0,.2);border-radius:3px}
    /* .owl-news .item {background:#fff} */
    .news-card{background:#fff;display:block;flex:0 0 270px;scroll-snap-align:start}
    @@media (max-width:600px){.news-card{flex-basis:85%}}
    /* .img-news {height:170px} */
    .img-news{
        height:170px;overflow:hidden;
        background:linear-gradient(135deg,#d8d3c4 0%,#cdb87f 80%,var(--gold) 160%);
        display:flex;align-items:center;justify-content:center;
    }
    .img-news b{font-weight:800;font-size:26px;color:rgba(0,0,0,.2)}
    /* .title-news {color:#3e4949; font-weight:600; min-height:40px} */
    .title-news{color:#3e4949;padding:0 10px;font-weight:600;margin-top:15px;min-height:40px;line-height:1.5;font-size:14px}
    /* .news-short-text {color:#525050; 12px; justify; max-height:100px} */
    .news-short-text{color:#525050;padding:5px 10px;text-align:justify;font-size:12px;min-height:100px;max-height:100px;overflow:hidden;line-height:1.6}
    /* .more-news {80×33; gold} hover: bg #000 / gold  — راست‌چین در LTR */
    .news-more-row{text-align:right}
    .more-news{
        display:inline-block;width:80px;height:33px;text-align:center;line-height:33px;
        background-color:var(--gold);color:#000;font-size:13px;font-weight:500;
        margin:0 10px 15px 0;transition:.2s linear;
    }
    .news-card:hover .more-news{background-color:#000;color:var(--gold)}

    /* ===== نتایج اعضا — .result-section {background:#fff} + عکس دایره‌ای 142px ===== */
    .result-section{background:#fff;padding:70px 0 50px}
    .result-grid{display:grid;grid-template-columns:1fr 1fr;gap:40px;align-items:center}
    @@media (max-width:767px){.result-grid{grid-template-columns:1fr;text-align:center}}
    /* .user-list li {inline-block; 32%; center; color:#222020} + .img-user img {142px; دایره} */
    .user-list{list-style:none;display:flex;flex-wrap:wrap;padding:0}
    .user-list li{width:32%;text-align:center;color:#222020;font-weight:500;margin-top:10px;font-size:13px}
    .img-user{
        width:150px;height:150px;border-radius:100%;margin:0 auto 8px;max-width:100%;
        background:linear-gradient(135deg,#e8e2d2,var(--gold));
        display:flex;align-items:center;justify-content:center;
        font-weight:700;font-size:30px;color:rgba(0,0,0,.25);
    }
    @@media (max-width:991px){.img-user{width:100px;height:100px;font-size:22px}}

    /* ===== اینستاگرام — دو نوار: .inst2 {#ebebeb; border-top:#c2c2c2} و .inst {#fff} ===== */
    .inst2{background:#ebebeb;border-top:1px solid #c2c2c2;padding:48px 0 32px}
    .inst{background:#fff;padding:48px 0 32px}
    .inst-grid{display:grid;grid-template-columns:1fr 1fr;gap:30px;align-items:center}
    @@media (max-width:767px){.inst-grid{grid-template-columns:1fr}}
    .bg-ins{
        aspect-ratio:502/477;max-width:502px;width:100%;
        background:linear-gradient(160deg,#2a2416,#8a6d1f);
        border-radius:6px;
    }
    .inst .bg-ins{aspect-ratio:646/424;max-width:646px}
    .bg-ins-img{width:100%;max-width:502px;height:auto;border-radius:6px;display:block;margin:0 auto}
    .inst .bg-ins-img{max-width:646px}
    .insta-link{text-align:center}
    .insta-logo{
        width:120px;height:120px;border-radius:28px;margin:0 auto;
        background:linear-gradient(135deg,#f58529,#dd2a7b,#8134af,#515bd4);
        display:flex;align-items:center;justify-content:center;font-size:52px;color:#fff;
    }
    .insta-small-img{width:149px;height:134px;object-fit:cover;border-radius:10px;margin:0 auto;display:block}
    /* .text-link a {background:#252525; color:#d9bb75; padding:5px 31px} hover معکوس */
    .text-link{margin-top:16px}
    .text-link a{
        display:inline-block;background-color:#252525;color:var(--gold);
        padding:5px 31px;font-weight:500;transition:.2s linear;font-size:14px;
    }
    .text-link a:hover{background-color:var(--gold);color:#252525}

    /* ===== مودال ویدیو ===== */
    .video-modal{position:fixed;inset:0;background:rgba(0,0,0,.88);display:none;align-items:center;justify-content:center;z-index:9999;padding:20px}
    .video-modal.open{display:flex}
    .video-modal__inner{width:min(860px,100%);aspect-ratio:16/9;background:#000;position:relative}
    .video-modal__inner iframe,.video-modal__inner video{width:100%;height:100%;border:0}
    .video-modal__close{position:absolute;top:-42px;right:0;background:none;border:0;color:#fff;font-size:30px;cursor:pointer}
    .js-video[data-embed=""][data-file=""]{cursor:default}
    .hero-slide.has-bg::before{display:none}
</style>
@endsection

@section('content')
    @php($s = $s ?? [])
    @php($members = $members ?? [])
    @php($v = fn($k, $d = '') => (($s[$k] ?? null) !== null && ($s[$k] ?? '') !== '') ? $s[$k] : $d)

    {{-- ============ اسلایدر هیرو ============ --}}
    <div class="hero-slider">
        <div class="hero-slide active @if($v('hero1_image')) has-bg @endif" @if($v('hero1_image')) style="background:url('{{ asset('storage/' . $v('hero1_image')) }}') center/cover no-repeat" @endif>
            <div class="wrap">
                <div class="hero-slide-text">
                    <h1>{{ $v('hero1_title', 'Kendini Savunma ve Dövüş Sanatları Eğitimi') }}</h1>
                    <div class="sub">{{ $v('hero1_sub', 'Tam başlangıç seviyesi için — spor geçmişi ve yaş sınırı yok, kadın ve erkekler için. İstanbul\'da veya online.') }}</div>
                </div>
            </div>
        </div>
        <div class="hero-slide @if($v('hero2_image')) has-bg @endif" @if($v('hero2_image')) style="background:url('{{ asset('storage/' . $v('hero2_image')) }}') center/cover no-repeat" @endif>
            <div class="wrap">
                <div class="hero-slide-text">
                    <h1>{{ $v('hero2_title', 'Brezilya Jiu-Jitsu: kaldıraç sanatı') }}</h1>
                    <div class="sub">{{ $v('hero2_sub', 'Küçük yapılı birinin daha güçlü bir saldırganı kontrol edebilmesi için geliştirildi — kaba güç yerine teknik ve pozisyon.') }}</div>
                </div>
            </div>
        </div>
        <div class="hero-slide @if($v('hero3_image')) has-bg @endif" @if($v('hero3_image')) style="background:url('{{ asset('storage/' . $v('hero3_image')) }}') center/cover no-repeat" @endif>
            <div class="wrap">
                <div class="hero-slide-text">
                    <h1>{{ $v('hero3_title', 'Martial Intelligence') }}</h1>
                    <div class="sub">{{ $v('hero3_sub', 'Baskı altında karar verme — gerçek bir çatışmada en çok önem taşıyan beceri.') }}</div>
                </div>
            </div>
        </div>
        <div class="hero-dots">
            <button class="hero-dot active" data-slide="0" aria-label="Slide 1"></button>
            <button class="hero-dot" data-slide="1" aria-label="Slide 2"></button>
            <button class="hero-dot" data-slide="2" aria-label="Slide 3"></button>
        </div>
    </div>

    {{-- ============ ردیف ویدیو (overlap روی اسلایدر) ============ --}}
    <section class="video-section">
        <div class="wrap">
            @php($videoDefaults = ['Neden dövüş sanatları ve kendini savunma eğitimi almalısınız', 'Eğitim nasıl işliyor', 'Kendini savunma ve dövüş sporu nedir'])
            <div class="row-video">
                @foreach([1, 2, 3] as $i)
                @php($vEmbed = $v("video{$i}_embed"))
                @php($vFile = $v("video{$i}_file"))
                @php($vThumb = $v("video{$i}_thumb"))
                <div class="video-card js-video" data-embed="{{ $vEmbed }}" data-file="{{ $vFile ? asset('storage/' . $vFile) : '' }}">
                    <div class="video-card__img" @if($vThumb) style="background:url('{{ asset('storage/' . $vThumb) }}') center/cover no-repeat" @endif>
                        <span class="video-icon">▶</span>
                    </div>
                    <div class="text-video">{{ $v("video{$i}_caption", $videoDefaults[$i - 1]) }}</div>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ============ درباره / اپلیکیشن ============ --}}
    <section class="about-section">
        <div class="wrap">
            <div class="about-text-col">
                <h2 class="abou-company">{{ $v('app_title', 'Ehsan Dibazar Kendini Savunma Akademisi uygulaması') }}</h2>
                <div class="sub-title">{{ $v('app_subtitle', 'Adım adım video eğitimi, her yerde') }}</div>
                <div class="about-text">{{ $v('app_text', 'Eğitim uygulaması, kendini savunma sürecini adım adım öğreten yapılandırılmış video kursları içerir; böylece kendi hızınızda öğrenebilirsiniz. Amacımız, dövüş sanatları ve kendini savunmada gerçek kalitede, doğru sırayla en etkili eğitim programlarını size sunmaktır.') }}</div>
                <div class="about-cta">
                    <a href="{{ url('/tr/courses') }}" class="show-more">{{ $v('app_button_label', 'Uygulamayı indirin') }}</a>
                </div>
            </div>
            @if($v('app_image'))
                <img src="{{ asset('storage/' . $v('app_image')) }}" alt="{{ $v('app_title', 'App') }}" class="about-bleed-img">
            @else
                <div class="img-about-box"><span>Uygulama</span></div>
            @endif
        </div>
    </section>

    {{-- ============ دوره‌های آموزشی و محصولات ============ --}}
    <section class="counter">
        <div class="wrap">
            <h2 class="title-counter">{{ $v('courses_title', 'Kurslar ve Ürünler') }}</h2>
            <div class="sun-counter">{{ $v('courses_subtitle', 'Size uygun formatı seçin — İstanbul\'da yüz yüze koçluk, uygulama üzerinden uzaktan eğitim veya Brezilya Jiu-Jitsu dersleri.') }}</div>
            @php($courseDefaults = [['Yüz Yüze', 'Yüz Yüze Koçluk'], ['Uzaktan', 'Uzaktan Eğitim (Uygulama)'], ['BJJ', 'Brezilya Jiu-Jitsu']])
            <div class="courses-carousel" data-carousel>
                <button class="car-arrow car-prev" aria-label="Previous">‹</button>
                <div class="learn-grid carousel-track">
                @foreach([1, 2, 3] as $i)
                <a href="{{ url('/tr/courses') }}" class="l-box">
                    <div class="img-learn" @if($v("course{$i}_image")) style="background-image:url('{{ asset('storage/' . $v("course{$i}_image")) }}');background-size:cover;background-position:center" @endif>
                        @unless($v("course{$i}_image"))<b>{{ $courseDefaults[$i - 1][0] }}</b>@endunless
                    </div>
                    <span class="l-title">{{ $v("course{$i}_label", $courseDefaults[$i - 1][1]) }}</span>
                </a>
                @endforeach
                </div>
                <button class="car-arrow car-next" aria-label="Next">›</button>
            </div>
        </div>
    </section>

    {{-- ============ مطالب آموزشی (داینامیک از دیتابیس) ============ --}}
    <section class="section-news">
        <div class="wrap">
            <h3 class="title-section">Eğitim Makaleleri</h3>
            <div class="sub-title-section">
                <a href="{{ url('/tr/blog') }}">Tüm arşivi görüntüle ⟶</a>
            </div>
            <div class="articles-carousel" data-carousel>
                <button class="car-arrow car-prev" aria-label="Previous">‹</button>
                <div class="news-grid carousel-track">
                @forelse($latestArticles ?? collect() as $article)
                <a class="news-card" href="{{ url('/tr/blog/' . $article->slug) }}">
                    <div class="img-news" @if($article->image_path) style="background-image:url('{{ asset('storage/' . $article->image_path) }}');background-size:cover;background-position:center" @endif>
                        @unless($article->image_path)<b>{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</b>@endunless
                    </div>
                    <div class="title-news">{{ $article->title }}</div>
                    <div class="news-short-text">{{ $article->excerpt }}</div>
                    <div class="news-more-row"><span class="more-news">Devamı</span></div>
                </a>
                @empty
                <p style="flex:1 0 100%;text-align:center;color:#888;font-size:13px;padding:20px 0">Yakında yeni makaleler eklenecek.</p>
                @endforelse
                </div>
                <button class="car-arrow car-next" aria-label="Next">›</button>
            </div>
        </div>
    </section>

    {{-- ============ نتایج اعضا ============ --}}
    <section class="result-section">
        <div class="wrap">
            <div class="result-grid">
                <div>
                    <h2 class="abou-company">{{ $v('members_title', 'Üye Sonuçları') }}</h2>
                    <div class="sub-title">{{ $v('members_subtitle', 'Gerçek yetenek kazandıran dövüş sanatları ve kendini savunma eğitimi — insanlara daha güçlü ve özgüvenli bir yaşam sunar.') }}</div>
                    <div class="about-cta">
                        <a href="{{ url('/tr/about') }}" class="show-more">{{ $v('members_button_label', 'Tüm üye sonuçlarını görüntüle') }}</a>
                    </div>
                </div>
                <div>
                    @php($membersList = !empty($members) ? $members : [['name' => 'Sajjad'], ['name' => 'Davoud'], ['name' => 'Omid'], ['name' => 'Mohammad'], ['name' => 'Amir'], ['name' => 'Sara']])
                    <ul class="user-list">
                        @foreach($membersList as $m)
                        <li>
                            <div class="img-user" @if(!empty($m['photo'])) style="background-image:url('{{ asset('storage/' . $m['photo']) }}');background-size:cover;background-position:center" @endif>
                                @if(empty($m['photo'])){{ mb_substr($m['name'] ?? '', 0, 1) }}@endif
                            </div>{{ $m['name'] ?? '' }}
                        </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ اینستاگرام — نوار اول ============ --}}
    <section class="inst2">
        <div class="wrap">
            <div class="inst-grid">
                <div class="insta-link">
                    <a href="{{ $v('insta_url', 'https://instagram.com') }}" rel="noopener">
                        @if($v('insta1_small_image'))
                            <img src="{{ asset('storage/' . $v('insta1_small_image')) }}" alt="Instagram" class="insta-small-img">
                        @else
                            <div class="insta-logo">◎</div>
                        @endif
                    </a>
                    <div class="text-link">
                        <a href="{{ $v('insta_url', 'https://instagram.com') }}" rel="noopener">Instagram\'da takip edin</a>
                    </div>
                </div>
                @if($v('insta1_image'))
                    <img src="{{ asset('storage/' . $v('insta1_image')) }}" alt="Instagram" class="bg-ins-img">
                @else
                    <div class="bg-ins"></div>
                @endif
            </div>
        </div>
    </section>

    {{-- ============ اینستاگرام — نوار دوم ============ --}}
    <section class="inst">
        <div class="wrap">
            <div class="inst-grid">
                @if($v('insta2_image'))
                    <img src="{{ asset('storage/' . $v('insta2_image')) }}" alt="Instagram" class="bg-ins-img">
                @else
                    <div class="bg-ins"></div>
                @endif
                <div class="insta-link">
                    <a href="{{ $v('insta_url', 'https://instagram.com') }}" rel="noopener">
                        @if($v('insta2_small_image'))
                            <img src="{{ asset('storage/' . $v('insta2_small_image')) }}" alt="Instagram" class="insta-small-img">
                        @else
                            <div class="insta-logo">◎</div>
                        @endif
                    </a>
                    <div class="text-link">
                        <a href="{{ $v('insta_url', 'https://instagram.com') }}" rel="noopener">@@ehsandibazar</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ مودال پخش ویدیو ============ --}}
    <div class="video-modal" id="videoModal">
        <div class="video-modal__inner">
            <button class="video-modal__close" aria-label="Close">×</button>
        </div>
    </div>

@endsection

@section('page-js')
<script>
    (function () {
        var slides = document.querySelectorAll('.hero-slide');
        var dots = document.querySelectorAll('.hero-dot');
        var idx = 0, timer;
        function show(i) {
            slides.forEach(function (s, n) { s.classList.toggle('active', n === i); });
            dots.forEach(function (d, n) { d.classList.toggle('active', n === i); });
            idx = i;
        }
        function next() { show((idx + 1) % slides.length); }
        dots.forEach(function (d, n) {
            d.addEventListener('click', function () { show(n); reset(); });
        });
        function reset() { clearInterval(timer); timer = setInterval(next, 5000); }
        if (slides.length) { show(0); reset(); }
    })();

    (function () {
        var modal = document.getElementById('videoModal');
        if (!modal) return;
        var inner = modal.querySelector('.video-modal__inner');
        var closeBtn = modal.querySelector('.video-modal__close');
        function close() {
            modal.classList.remove('open');
            inner.querySelectorAll('iframe,video').forEach(function (el) { el.remove(); });
        }
        document.querySelectorAll('.js-video').forEach(function (card) {
            card.addEventListener('click', function () {
                var embed = card.getAttribute('data-embed');
                var file = card.getAttribute('data-file');
                if (!embed && !file) return;
                var el;
                if (embed) {
                    el = document.createElement('iframe');
                    el.src = embed;
                    el.setAttribute('allow', 'autoplay; fullscreen');
                    el.setAttribute('allowfullscreen', '');
                } else {
                    el = document.createElement('video');
                    el.src = file;
                    el.controls = true;
                    el.autoplay = true;
                }
                inner.appendChild(el);
                modal.classList.add('open');
            });
        });
        closeBtn.addEventListener('click', close);
        modal.addEventListener('click', function (e) { if (e.target === modal) close(); });
    })();
</script>
@endsection
