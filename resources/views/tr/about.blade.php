@extends('layouts.master-tr')

@section('title', 'Ehsan Dibazar | Muay Thai ve Kendini Savunma Eğitmeni')
@section('meta_description', 'Ehsan Dibazar — 12 yıllık eğitim deneyimine sahip Muay Thai ve kendini savunma eğitmeni, Bangkok\'tan uluslararası Muay Thai sertifikası sahibi.')
@section('canonical', url('/tr/about'))

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
  "alumniOf": {"@@type": "CollegeOrUniversity", "name": "Fenerbahçe University, Istanbul"},
  "knowsAbout": ["Muay Thai", "Kendini Savunma", "Brezilya Jiu-Jitsu", "Korumalık", "Spor Bilimleri"],
  "sameAs": [
    "https://www.instagram.com/ehsandibazarcoaching/",
    "https://telegram.me/ehsandibazar",
    "https://youtube.com/channel/UCDT9EOHriR9sHvq0PBdmlog"
  ]
}
</script>
@endsection

@section('page-css')
<style>
:root{--dark:#0e0e0e;--dark2:#161616}
body{background:var(--dark)!important}
.about-v5{overflow-x:hidden;max-width:100%;width:100%;background:var(--dark);color:#eee}
.about-v5 *{box-sizing:border-box}
.about-v5 section{padding:70px 20px;position:relative}
.about-v5 .container{max-width:920px;margin:0 auto}
.about-v5 h1,.about-v5 h2{margin:0;color:#fff}

/* ===== هیرو ===== */
.about-v5 .hero{min-height:50vh;padding:40px 20px 100px;display:flex;flex-direction:column;align-items:center;justify-content:flex-start;text-align:center;position:relative}
@@media(min-width:992px){.about-v5 .hero{padding-top:120px}}
.about-v5 .glow{position:absolute;width:min(360px,90vw);height:min(360px,90vw);border-radius:50%;background:radial-gradient(circle,rgba(217,187,117,.35),transparent 70%);filter:blur(10px);top:8%;left:50%;transform:translateX(-50%);animation:pulse 6s ease-in-out infinite}
@@keyframes pulse{0%,100%{opacity:.6;transform:translateX(-50%) scale(1)}50%{opacity:1;transform:translateX(-50%) scale(1.15)}}
.about-v5 .hero-photo-wrap{width:140px;height:140px;perspective:600px;margin-bottom:28px;position:relative;z-index:2}
.about-v5 .hero-photo{width:100%;height:100%;border-radius:50%;object-fit:cover;border:3px solid var(--gold);transition:transform .15s ease-out;box-shadow:0 20px 50px rgba(0,0,0,.5)}
.about-v5 .hero h1{font-size:34px;font-weight:800;position:relative;z-index:2;opacity:0;animation:fadeUp .7s ease forwards .15s}
.about-v5 .hero .sub{color:var(--gold);font-weight:600;margin:10px 0 18px;font-size:15.5px;position:relative;z-index:2;opacity:0;animation:fadeUp .7s ease forwards .3s}
.about-v5 .hero .txt{max-width:600px;line-height:2;color:#cfcfcf;font-size:14.5px;position:relative;z-index:2;opacity:0;animation:fadeUp .7s ease forwards .45s}
.about-v5 .stat-row{display:flex;gap:14px;justify-content:center;margin-top:34px;flex-wrap:wrap;position:relative;z-index:2}
.about-v5 .glass{background:rgba(255,255,255,.06);backdrop-filter:blur(12px);border:1px solid rgba(217,187,117,.35);border-radius:16px;padding:16px 22px;text-align:center;min-width:130px;opacity:0;animation:fadeUp .6s ease forwards}
.about-v5 .glass:nth-child(1){animation-delay:.6s}
.about-v5 .glass:nth-child(2){animation-delay:.75s}
.about-v5 .glass:nth-child(3){animation-delay:.9s}
@@keyframes fadeUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
.about-v5 .glass b{display:block;color:var(--gold);font-size:22px}
.about-v5 .glass span{font-size:12px;color:#ccc}

/* ===== تایم‌لاین ===== */
.about-v5 .tl-wrap h2{text-align:center;font-size:24px;font-weight:800;margin-bottom:50px}
.about-v5 .tl-wrap h2::after{content:"";display:block;width:60px;height:3px;background:var(--gold);margin:14px auto 0;border-radius:3px}
.about-v5 .tl{position:relative;padding-left:26px;border-left:2px solid #2a2a2a;max-width:560px;margin:0 auto}
.about-v5 .tl-item{position:relative;padding-bottom:38px;opacity:0;transform:translateY(24px);transition:opacity .6s ease,transform .6s ease}
.about-v5 .tl-item.show{opacity:1;transform:translateY(0)}
.about-v5 .tl-item::before{content:"";position:absolute;left:-33px;top:3px;width:14px;height:14px;border-radius:50%;background:var(--gold);box-shadow:0 0 0 4px rgba(217,187,117,.2)}
.about-v5 .tl-year{color:var(--gold);font-weight:800;font-size:17px}
.about-v5 .tl-label{font-weight:700;font-size:14.5px;margin:2px 0 4px;color:#fff}
.about-v5 .tl-desc{font-size:13px;color:#999;line-height:1.8}

/* ===== مدارک ===== */
.about-v5 .gallery h2{text-align:center;font-size:24px;font-weight:800;margin-bottom:40px}
.about-v5 .gallery h2::after{content:"";display:block;width:60px;height:3px;background:var(--gold);margin:14px auto 0}
.about-v5 .masonry{column-count:1;column-gap:14px}
@@media(min-width:640px){.about-v5 .masonry{column-count:2}}
.about-v5 .cred{break-inside:avoid;margin-bottom:14px;position:relative;border-radius:14px;overflow:hidden;cursor:pointer;transition:transform .3s,box-shadow .3s;aspect-ratio:4/3;background:linear-gradient(135deg,#2a2416,#8a6d1f)}
.about-v5 .cred:hover{transform:translateY(-6px);box-shadow:0 20px 40px rgba(217,187,117,.25)}
.about-v5 .cred .cap{position:absolute;bottom:0;right:0;left:0;background:linear-gradient(0deg,rgba(0,0,0,.9),transparent 80%);color:#fff;padding:20px 10px 8px;font-size:12px;font-weight:600}

/* ===== CTA مگنتیک ===== */
.about-v5 .cta{background:linear-gradient(135deg,#1a1a1a,#000);text-align:center}
.about-v5 .cta h3{font-size:20px;font-weight:800;margin-bottom:20px;color:#fff}
.about-v5 .magnetic-wrap{display:flex;justify-content:center}
.about-v5 .magnetic{position:relative;display:inline-flex;align-items:center;gap:8px;background:var(--gold);color:#111;padding:14px 34px;border-radius:32px;font-weight:800;text-decoration:none;font-size:15px;transition:transform .15s ease-out}

@@media (prefers-reduced-motion: reduce){
    .about-v5 .glow{animation:none}
    .about-v5 .hero-photo{transition:none}
    .about-v5 .tl-item{transition:none;opacity:1;transform:none}
    .about-v5 .cred{transition:none}
    .about-v5 .cred:hover{transform:none}
    .about-v5 .magnetic{transition:none}
    .about-v5 .hero h1,.about-v5 .hero .sub,.about-v5 .hero .txt,.about-v5 .glass{animation:none;opacity:1}
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
            <img id="heroPhoto" class="hero-photo" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='140' height='140'%3E%3Crect width='140' height='140' fill='%232a2416'/%3E%3C/svg%3E" alt="Ehsan Dibazar" fetchpriority="high" decoding="async">
        </div>
        <h1>Ehsan Dibazar</h1>
        <div class="sub">Dövüş sanatları ve kendini savunma eğitmeni, Spor Bilimleri Yüksek Lisansı</div>
        <div class="txt">
            Dövüş sanatları öğretmek ve insanların kendini savunma becerisini geliştirmek benim
            için her zaman en anlamlı işlerden biri oldu. İnsanların daha güçlü olmasına yardımcı
            olmak hayatıma anlam katıyor. En büyük önceliğim, özellikle başlangıç seviyesindekiler
            için dövüş sanatları ve kendini savunma eğitiminin kalitesini yükseltmek — böylece
            daha fazla keyif ve süreklilikle devam edip hayatlarında gerçek ve faydalı sonuçlar
            elde edebilsinler. Doğru şekilde çalışıldığında dövüş sanatlarının insanların
            hayatını gerçekten iyileştirdiğine inanıyorum.
        </div>
        <div class="stat-row">
            <div class="glass"><b>12+</b><span>Yıllık eğitim deneyimi</span></div>
            <div class="glass"><b>Binlerce</b><span>yüz yüze ve online öğrenci</span></div>
            <div class="glass"><b>Çeşitli</b><span>uluslararası sertifikalar</span></div>
        </div>
    </header>

    {{-- ============ مدارک و افتخارات ============ --}}
    <section class="gallery" aria-label="Sertifikalar ve başarılar">
        <div class="container">
            <h2>Sertifikalar ve Başarılar</h2>
            {{-- placeholder گرادیانی — بعداً با عکس واقعی مدارک جایگزین می‌شود --}}
            <div class="masonry" id="masonry">
                <figure class="cred" data-cap="Brezilya Jiu-Jitsu kendini savunma sertifikası, ABD" tabindex="0" role="button"><figcaption class="cap">Brezilya Jiu-Jitsu kendini savunma sertifikası, ABD</figcaption></figure>
                <figure class="cred" data-cap="Muay Thai teknik sertifikası, Tayland Milli Eğitim Bakanlığı" tabindex="0" role="button"><figcaption class="cap">Muay Thai teknik sertifikası, Tayland Milli Eğitim Bakanlığı</figcaption></figure>
                <figure class="cred" data-cap="Koruma diploması, Türk Askeri Akademisi" tabindex="0" role="button"><figcaption class="cap">Koruma diploması, Türk Askeri Akademisi</figcaption></figure>
                <figure class="cred" data-cap="Temel Koruma Diploması" tabindex="0" role="button"><figcaption class="cap">Temel Koruma Diploması</figcaption></figure>
                <figure class="cred" data-cap="Türkiye'de koruma sertifikasını aldıktan sonra" tabindex="0" role="button"><figcaption class="cap">Türkiye'de koruma sertifikasını aldıktan sonra</figcaption></figure>
                <figure class="cred" data-cap="Muay Thai teknik sınavını geçtikten sonra eğitmenle, Tayland" tabindex="0" role="button"><figcaption class="cap">Muay Thai teknik sınavını geçtikten sonra eğitmenle, Tayland</figcaption></figure>
                <figure class="cred" data-cap="Brezilya Jiu-Jitsu Dünya Şampiyonası'nda rakibiyle, Rusya" tabindex="0" role="button"><figcaption class="cap">Brezilya Jiu-Jitsu Dünya Şampiyonası'nda rakibiyle, Rusya</figcaption></figure>
                <figure class="cred" data-cap="Brezilya Jiu-Jitsu Dünya Şampiyonası'nda, Rusya" tabindex="0" role="button"><figcaption class="cap">Brezilya Jiu-Jitsu Dünya Şampiyonası'nda, Rusya</figcaption></figure>
                <figure class="cred" data-cap="Muay Thai teknik sınav belgesi, Bangkok Muay Thai Üniversitesi" tabindex="0" role="button"><figcaption class="cap">Muay Thai teknik sınav belgesi, Bangkok Muay Thai Üniversitesi</figcaption></figure>
                <figure class="cred" data-cap="Muay Thai eğitim sertifikası, İstanbul" tabindex="0" role="button"><figcaption class="cap">Muay Thai eğitim sertifikası, İstanbul</figcaption></figure>
                <figure class="cred" data-cap="Tahran Üniversitesi Beden Eğitimi Fakültesi'nde atölye çalışması" tabindex="0" role="button"><figcaption class="cap">Tahran Üniversitesi Beden Eğitimi Fakültesi'nde atölye çalışması</figcaption></figure>
                <figure class="cred" data-cap="Muay Boran online seminerine katılım, ABD" tabindex="0" role="button"><figcaption class="cap">Muay Boran online seminerine katılım, ABD</figcaption></figure>
            </div>
        </div>
    </section>

    {{-- ============ تایم‌لاین ============ --}}
    <section class="tl-wrap">
        <div class="container">
            <h2>Yolculuğum</h2>
            <div class="tl" id="tlList">
                <div class="tl-item">
                    <div class="tl-year">2013</div>
                    <div class="tl-label">Koruma ve Kendini Savunma Sertifikası</div>
                    <div class="tl-desc">Aylarca süren eğitim ve çabanın ardından, dönemin İstanbul
                        yetkililerinden koruma ve kendini savunma sertifikası aldım — dövüş
                        sanatları ve kendini savunma öğretme tutkumu daha da alevlendiren bir
                        yolculuğun başlangıcı. En iyi dövüş sanatları koçları eşliğinde kendi
                        eğitimime devam ederken, bu alana ilgi duyan diğer kişilere de eğitim
                        vermeye başladım.</div>
                </div>
                <div class="tl-item">
                    <div class="tl-year">2016</div>
                    <div class="tl-label">Dövüş Sanatları Salonu Açtım</div>
                    <div class="tl-desc">Kendi özel dövüş sanatları salonumu açtım ve bununla birlikte
                        bir turizm ve doğa gezileri ofisi kurdum. Her iki alandaki kesintisiz
                        çalışmam — özellikle birçok öğrenciye eğitim vermem — sonunda beni büyük
                        bir başarıya taşıdı.</div>
                </div>
                <div class="tl-item">
                    <div class="tl-year">2019</div>
                    <div class="tl-label">Muay Thai Teknik Sertifikası, Bangkok</div>
                    <div class="tl-desc">Koç ve salon sahibi olarak yoğun çalışmaların ardından,
                        Bangkok Muay Thai Üniversitesi'nden Muay Thai teknik sertifikamı aldım.
                        Tayland'da yaşamak, antrenman yapmak ve orada müsabakalara katılmak, kendi
                        salonumda verdiğim eğitimin kalitesini önemli ölçüde yükseltti. Aynı zamanda
                        Tahran Üniversitesi Beden Eğitimi Fakültesi'nde spor bilimi ve koçluk
                        eğitimi aldım.</div>
                </div>
                <div class="tl-item">
                    <div class="tl-year">2022</div>
                    <div class="tl-label">Brezilya Jiu-Jitsu Kendini Savunma Teknik Sertifikası, Kaliforniya</div>
                    <div class="tl-desc">Amerika Birleşik Devletleri'ndeki en saygın kendini savunma
                        kurumlarından birinden çok değerli bir teknik sertifika aldım ve
                        öğrencilerimi de bu yolda yanımda getirebildim. Bu işe olan sevgim,
                        derslerimde zamanın nasıl geçtiğini hiç fark etmememi sağladı.</div>
                </div>
                <div class="tl-item">
                    <div class="tl-year">2024</div>
                    <div class="tl-label">Spor Fizyolojisi Yüksek Lisansı</div>
                    <div class="tl-desc">Modern spor bilimini dövüş sanatlarıyla birleştirme
                        hedefiyle, İstanbul Fenerbahçe Üniversitesi'nde Spor Fizyolojisi alanında
                        yüksek lisans eğitimine başladım ve sportif antrenmanlarda fizyolojik
                        göstergelerin değerlendirilmesine odaklandım.</div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ CTA مگنتیک ============ --}}
    <section class="cta">
        <div class="magnetic-wrap">
            <a class="magnetic" id="magneticBtn" href="https://www.instagram.com/ehsandibazarcoaching" target="_blank" rel="noopener">Instagram’da takip edin</a>
        </div>
    </section>

</main>
</div>

{{-- مودال نمایش بزرگ مدرک --}}
<div class="modal" id="modal" role="dialog" aria-modal="true" aria-label="Büyütülmüş sertifika görünümü" style="position:fixed;inset:0;background:rgba(0,0,0,.9);display:none;align-items:center;justify-content:center;z-index:1000;padding:20px">
    <button class="modal-close" id="modalClose" aria-label="Kapat" style="position:absolute;top:20px;left:20px;color:#fff;font-size:30px;background:none;border:none;cursor:pointer">&times;</button>
    <div>
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

    // ===== مودال مدارک (فعلاً فقط کپشن، چون عکس واقعی هنوز نداریم) =====
    var modal = document.getElementById('modal');
    var modalCap = document.getElementById('modalCap');
    var lastFocused = null;
    document.querySelectorAll('.cred').forEach(function (c) {
        c.addEventListener('click', function () {
            lastFocused = document.activeElement;
            modalCap.textContent = c.dataset.cap;
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
