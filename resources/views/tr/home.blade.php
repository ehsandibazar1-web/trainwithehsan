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
    .hero-slide{
        display:none;min-height:56vh;align-items:center;position:relative;
        background:linear-gradient(115deg,#2e2c28 0%,#1c1b18 55%,#0d0d0b 120%);
    }
    .hero-slide.active{display:flex}
    /* بافت پس‌زمینه شبیه پوستر تیره */
    .hero-slide::before{
        content:"DIBAZAR";position:absolute;left:-14px;bottom:-4vw;
        font-size:15vw;font-weight:800;color:rgba(255,255,255,.045);
        letter-spacing:.02em;pointer-events:none;line-height:1;
    }
    /* .main-text-slider {font-size:1.8rem; color:#fff} / .main-text-slider2 {color:#f3f3f3; 1.5rem} */
    .hero-slide-text{position:relative;z-index:1;max-width:600px}
    .hero-slide-text h1{font-size:clamp(1.5rem,3.4vw,1.8rem);color:#fff;font-weight:600;line-height:1.5}
    .hero-slide-text .sub{font-size:clamp(1rem,2.6vw,1.5rem);color:#f3f3f3;margin-top:10px;line-height:1.6}
    /* .slider .owl-dots — نقطه‌ها؛ فعال #d9bb75 */
    .hero-dots{position:absolute;bottom:24px;left:0;right:0;display:flex;justify-content:center;gap:9px;z-index:2}
    .hero-dot{width:14px;height:14px;border-radius:50%;background:#ffffffa8;border:0;cursor:pointer;padding:0}
    .hero-dot.active{background:var(--gold)}

    /* ===== ردیف ویدیو — .row-video {margin-top:-94px} overlap روی اسلایدر ===== */
    .video-section{padding-bottom:40px}
    .row-video{position:relative;z-index:1;margin-top:-94px;display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
    @@media (max-width:991px){.row-video{margin-top:-53px}}
    @@media (max-width:767px){.row-video{margin-top:-28px;grid-template-columns:1fr}}
    /* .owl-send .item {height:232px} + .video-icon + .text-video گرادیان پایین */
    .video-card{
        position:relative;height:232px;overflow:hidden;cursor:pointer;
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
        position:absolute;inset:0;display:flex;flex-direction:column-reverse;
        padding:20px;text-align:center;font-weight:600;color:#fff;font-size:14px;
        background:linear-gradient(to bottom,rgba(0,0,0,0) 3%,rgba(0,0,0,.36) 48%,rgba(0,0,0,.65) 85%,rgba(0,0,0,.65) 98%);
    }

    /* ===== درباره/اپلیکیشن — سفید؛ .abou-company {color:#393e40; 2.2rem} ===== */
    .about-section{padding:60px 0 40px;position:relative;background:#fff}
    .about-grid{display:grid;grid-template-columns:1fr 1fr;gap:40px;align-items:center}
    @@media (max-width:767px){.about-grid{grid-template-columns:1fr;text-align:center}}
    .abou-company{color:#393e40;font-weight:800;font-size:2.2rem;margin-bottom:10px;line-height:1.4}
    @@media (max-width:767px){.abou-company{font-size:20px;line-height:2}}
    .sub-title{color:#393e40;font-weight:500;font-size:16px}
    .about-text{color:#3b3b3b;line-height:2.2;font-size:13px;text-align:justify;margin:12px 0 8px}
    @@media (max-width:767px){.about-text{text-align:center}}
    .about-cta{margin-top:40px}
    .img-about-box{
        aspect-ratio:469/434;max-width:469px;justify-self:end;width:100%;
        background:linear-gradient(135deg,#f0ede4 0%,#e2d3a8 70%,var(--gold) 160%);
        display:flex;align-items:flex-end;padding:20px;
    }
    @@media (max-width:767px){.img-about-box{justify-self:center;margin-top:20px}}
    .img-about-box span{font-weight:800;font-size:34px;color:rgba(0,0,0,.18)}

    /* ===== دوره‌ها — .counter {background:#363636; min-height:508px; color:#fff} ===== */
    .counter{background:#363636;min-height:508px;color:#fff;padding:50px 0 60px}
    /* .title-counter {font-size:2rem} centered */
    .title-counter{font-size:2rem;text-align:center;color:#fff;font-weight:700}
    @@media (max-width:767px){.title-counter{font-size:1.5rem}}
    .sun-counter{text-align:center;color:#ddd;font-size:14px;margin-top:8px;max-width:44rem;margin-left:auto;margin-right:auto}
    .learn-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;margin-top:40px}
    @@media (max-width:820px){.learn-grid{grid-template-columns:1fr}}
    /* .img-learn (تصویر 362×241) + .l-title {color:#1e1e1e; background:#d9bb75; min-height:50px; 15px} */
    .l-box{display:block}
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
    .news-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;margin-top:32px}
    @@media (max-width:820px){.news-grid{grid-template-columns:1fr}}
    /* .owl-news .item {background:#fff} */
    .news-card{background:#fff;display:block}
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
        display:inline-block;width:110px;height:33px;text-align:center;line-height:33px;
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
        width:142px;height:142px;border-radius:100%;margin:0 auto 8px;max-width:100%;
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
    .insta-link{text-align:center}
    .insta-logo{
        width:120px;height:120px;border-radius:28px;margin:0 auto;
        background:linear-gradient(135deg,#f58529,#dd2a7b,#8134af,#515bd4);
        display:flex;align-items:center;justify-content:center;font-size:52px;color:#fff;
    }
    /* .text-link a {background:#252525; color:#d9bb75; padding:5px 31px} hover معکوس */
    .text-link{margin-top:16px}
    .text-link a{
        display:inline-block;background-color:#252525;color:var(--gold);
        padding:5px 31px;font-weight:500;transition:.2s linear;font-size:14px;
    }
    .text-link a:hover{background-color:var(--gold);color:#252525}
</style>
@endsection

@section('content')

    {{-- ============ اسلایدر هیرو ============ --}}
    <div class="hero-slider">
        <div class="hero-slide active">
            <div class="wrap">
                <div class="hero-slide-text">
                    <h1>Kendini Savunma ve Dövüş Sanatları Eğitimi</h1>
                    <div class="sub">Tam başlangıç seviyesi için — spor geçmişi ve yaş sınırı yok,
                        kadın ve erkekler için. İstanbul\'da veya online.</div>
                </div>
            </div>
        </div>
        <div class="hero-slide">
            <div class="wrap">
                <div class="hero-slide-text">
                    <h1>Brezilya Jiu-Jitsu: kaldıraç sanatı</h1>
                    <div class="sub">Küçük yapılı birinin daha güçlü bir saldırganı kontrol edebilmesi
                        için geliştirildi — kaba güç yerine teknik ve pozisyon.</div>
                </div>
            </div>
        </div>
        <div class="hero-slide">
            <div class="wrap">
                <div class="hero-slide-text">
                    <h1>Martial Intelligence</h1>
                    <div class="sub">Baskı altında karar verme — gerçek bir çatışmada en çok
                        önem taşıyan beceri.</div>
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
            <div class="row-video">
                <div class="video-card">
                    <span class="video-icon">▶</span>
                    <div class="text-video">Neden dövüş sanatları ve kendini savunma eğitimi almalısınız</div>
                </div>
                <div class="video-card">
                    <span class="video-icon">▶</span>
                    <div class="text-video">Eğitim nasıl işliyor</div>
                </div>
                <div class="video-card">
                    <span class="video-icon">▶</span>
                    <div class="text-video">Kendini savunma ve dövüş sporu nedir</div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ درباره / اپلیکیشن ============ --}}
    <section class="about-section">
        <div class="wrap">
            <div class="about-grid">
                <div>
                    <h2 class="abou-company">Ehsan Dibazar Kendini Savunma Akademisi uygulaması</h2>
                    <div class="sub-title">Adım adım video eğitimi, her yerde</div>
                    <div class="about-text">
                        Eğitim uygulaması, kendini savunma sürecini adım adım öğreten
                        yapılandırılmış video kursları içerir; böylece kendi hızınızda
                        öğrenebilirsiniz. Amacımız, dövüş sanatları ve kendini savunmada
                        gerçek kalitede, doğru sırayla en etkili eğitim programlarını
                        size sunmaktır — böylece hedefinize gerçekten ulaşırsınız.
                    </div>
                    <div class="about-cta">
                        <a href="{{ url('/tr/courses') }}" class="show-more">Uygulamayı indirin</a>
                    </div>
                </div>
                <div class="img-about-box"><span>Uygulama</span></div>
            </div>
        </div>
    </section>

    {{-- ============ دوره‌های آموزشی و محصولات (پس‌زمینه #363636) ============ --}}
    <section class="counter">
        <div class="wrap">
            <h2 class="title-counter">Kurslar ve Ürünler</h2>
            <div class="sun-counter">
                Size uygun formatı seçin — İstanbul\'da yüz yüze koçluk, uygulama
                üzerinden uzaktan eğitim veya Brezilya Jiu-Jitsu dersleri.
            </div>
            <div class="learn-grid">
                <a href="{{ url('/tr/courses') }}" class="l-box">
                    <div class="img-learn"><b>Yüz Yüze</b></div>
                    <span class="l-title">Yüz Yüze Koçluk</span>
                </a>
                <a href="{{ url('/tr/courses') }}" class="l-box">
                    <div class="img-learn"><b>Uzaktan</b></div>
                    <span class="l-title">Uzaktan Eğitim (Uygulama)</span>
                </a>
                <a href="{{ url('/tr/courses') }}" class="l-box">
                    <div class="img-learn"><b>BJJ</b></div>
                    <span class="l-title">Brezilya Jiu-Jitsu</span>
                </a>
            </div>
        </div>
    </section>

    {{-- ============ مطالب آموزشی (پس‌زمینه #e1e1e1، کارت سفید) ============ --}}
    <section class="section-news">
        <div class="wrap">
            <h3 class="title-section">Eğitim Makaleleri</h3>
            <div class="sub-title-section">
                <a href="{{ url('/tr/blog') }}">Tüm arşivi görüntüle ⟶</a>
            </div>
            {{-- بعداً این بخش با دیتای دیتابیس داینامیک می‌شود --}}
            <div class="news-grid">
                <a class="news-card" href="{{ url('/tr/blog') }}">
                    <div class="img-news"><b>01</b></div>
                    <div class="title-news">Sadece teknik neden sizi kurtarmaz</div>
                    <div class="news-short-text">
                        Gerçek bir çatışmanın ilk üç saniyesinde bedeninize ve zihninize ne olur
                        — ve sonucu belirleyen becerinin neden ezberlenmiş hareketler değil,
                        karar verme olduğu. Bunun için nasıl antrenman yapılır.
                    </div>
                    <div class="news-more-row"><span class="more-news">Devamı</span></div>
                </a>
                <a class="news-card" href="{{ url('/tr/blog') }}">
                    <div class="img-news"><b>02</b></div>
                    <div class="title-news">Küçük yapılılar için BJJ: dürüst gerçek</div>
                    <div class="news-short-text">
                        55 kiloluk bir başlangıç seviyesi, 90 kiloluk bir saldırganı gerçekten
                        kontrol edebilir mi? Kaldıraç, pozisyon ve tekniğin neyi mümkün kıldığına
                        — ve neyi kılmadığına — gerçek eğitim deneyimine dayanarak net bir bakış.
                    </div>
                    <div class="news-more-row"><span class="more-news">Devamı</span></div>
                </a>
                <a class="news-card" href="{{ url('/tr/blog') }}">
                    <div class="img-news"><b>03</b></div>
                    <div class="title-news">Kadınlar için kendini savunma: nereden başlamalı</div>
                    <div class="news-short-text">
                        Kadınların gerçek hayatta karşılaştığı en yaygın tehdit senaryoları ve
                        her şeyden önce öğrenilmeye değer ilk üç beceri — spor geçmişi
                        gerektirmez.
                    </div>
                    <div class="news-more-row"><span class="more-news">Devamı</span></div>
                </a>
            </div>
        </div>
    </section>

    {{-- ============ نتایج اعضا (سفید، عکس دایره‌ای) ============ --}}
    <section class="result-section">
        <div class="wrap">
            <div class="result-grid">
                <div>
                    <h2 class="abou-company">Üye Sonuçları</h2>
                    <div class="sub-title">
                        Gerçek yetenek kazandıran dövüş sanatları ve kendini savunma eğitimi —
                        insanlara daha güçlü ve özgüvenli bir yaşam sunar.
                    </div>
                    <div class="about-cta">
                        <a href="{{ url('/tr/about') }}" class="show-more">Tüm üye sonuçlarını görüntüle</a>
                    </div>
                </div>
                <div>
                    <ul class="user-list">
                        <li><div class="img-user">S</div>Sajjad</li>
                        <li><div class="img-user">D</div>Davoud</li>
                        <li><div class="img-user">O</div>Omid</li>
                        <li><div class="img-user">M</div>Mohammad</li>
                        <li><div class="img-user">A</div>Amir</li>
                        <li><div class="img-user">S</div>Sara</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ اینستاگرام — نوار اول (#ebebeb) ============ --}}
    <section class="inst2">
        <div class="wrap">
            <div class="inst-grid">
                <div class="insta-link">
                    <div class="insta-logo">◎</div>
                    <div class="text-link">
                        <a href="https://instagram.com" rel="noopener">Instagram’da takip edin</a>
                    </div>
                </div>
                <div class="bg-ins"></div>
            </div>
        </div>
    </section>

    {{-- ============ اینستاگرام — نوار دوم (سفید) ============ --}}
    <section class="inst">
        <div class="wrap">
            <div class="inst-grid">
                <div class="bg-ins"></div>
                <div class="insta-link">
                    <div class="insta-logo">◎</div>
                    <div class="text-link">
                        <a href="https://instagram.com" rel="noopener">@@ehsandibazar</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

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
</script>
@endsection
