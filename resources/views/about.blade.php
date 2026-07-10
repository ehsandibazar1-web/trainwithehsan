@extends('layouts.master')

@section('title', 'Ehsan Dibazar | Muay Thai, Self-Defense Instructor & Founder of Martial Intelligence')
@section('meta_description', 'Ehsan Dibazar — Muay Thai and self-defense instructor with 12 years of teaching experience, holder of an international Muay Thai certificate from Bangkok, and founder of the Martial Intelligence concept.')
@section('canonical', url('/about'))

@section('json-ld')
<script type="application/ld+json">
{
  "@@context": "https://schema.org",
  "@@type": "Person",
  "@@id": "https://trainwithehsan.com/about#person",
  "name": "Ehsan Dibazar",
  "url": "https://trainwithehsan.com/about",
  "jobTitle": "Martial Arts & Self-Defense Instructor",
  "description": "Ehsan Dibazar, martial arts and self-defense instructor, MSc in Sport Science, and developer of the Martial Intelligence concept, with 12 years of teaching experience.",
  "alumniOf": {"@@type": "CollegeOrUniversity", "name": "Fenerbahçe University, Istanbul"},
  "knowsAbout": ["Muay Thai", "Self-Defense", "Brazilian Jiu-Jitsu", "Bodyguarding", "Sport Science", "Martial Intelligence"],
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
        <div class="sub">Martial arts &amp; self-defense instructor, MSc in Sport Science, and developer of the Martial Intelligence concept</div>
        <div class="txt">
            Teaching martial arts and building people's self-defense capability has always been
            one of the most rewarding things I do. Helping people become stronger gives my life
            meaning. My biggest concern is raising the quality of training and teaching in martial
            arts and self-defense — especially for beginners — so they keep going with more
            enjoyment and consistency, and get real, useful results in their lives. I believe
            martial arts, trained the right way, genuinely make people's lives better.
        </div>
        <div class="stat-row">
            <div class="glass"><b>12+</b><span>Years teaching experience</span></div>
            <div class="glass"><b>Thousands</b><span>of in-person &amp; online students</span></div>
            <div class="glass"><b>Several</b><span>international certifications</span></div>
        </div>
    </header>

    {{-- ============ مدارک و افتخارات ============ --}}
    <section class="gallery" aria-label="Credentials and achievements">
        <div class="container">
            <h2>Credentials &amp; Achievements</h2>
            {{-- placeholder گرادیانی — بعداً با عکس واقعی مدارک جایگزین می‌شود --}}
            <div class="masonry" id="masonry">
                <figure class="cred" data-cap="Brazilian Jiu-Jitsu self-defense certificate, USA" tabindex="0" role="button"><figcaption class="cap">Brazilian Jiu-Jitsu self-defense certificate, USA</figcaption></figure>
                <figure class="cred" data-cap="Muay Thai technical certificate, Thailand Ministry of Education" tabindex="0" role="button"><figcaption class="cap">Muay Thai technical certificate, Thailand Ministry of Education</figcaption></figure>
                <figure class="cred" data-cap="Bodyguard diploma, Turkish Military Academy" tabindex="0" role="button"><figcaption class="cap">Bodyguard diploma, Turkish Military Academy</figcaption></figure>
                <figure class="cred" data-cap="Basic Bodyguard Diploma" tabindex="0" role="button"><figcaption class="cap">Basic Bodyguard Diploma</figcaption></figure>
                <figure class="cred" data-cap="After receiving bodyguard certification in Turkey" tabindex="0" role="button"><figcaption class="cap">After receiving bodyguard certification in Turkey</figcaption></figure>
                <figure class="cred" data-cap="With instructor after passing the Muay Thai technical exam, Thailand" tabindex="0" role="button"><figcaption class="cap">With instructor after passing the Muay Thai technical exam, Thailand</figcaption></figure>
                <figure class="cred" data-cap="With an opponent at the Brazilian Jiu-Jitsu World Championship, Russia" tabindex="0" role="button"><figcaption class="cap">With an opponent at the Brazilian Jiu-Jitsu World Championship, Russia</figcaption></figure>
                <figure class="cred" data-cap="Competing at the Brazilian Jiu-Jitsu World Championship, Russia" tabindex="0" role="button"><figcaption class="cap">Competing at the Brazilian Jiu-Jitsu World Championship, Russia</figcaption></figure>
                <figure class="cred" data-cap="Muay Thai technical exam transcript, Bangkok Muay Thai University" tabindex="0" role="button"><figcaption class="cap">Muay Thai technical exam transcript, Bangkok Muay Thai University</figcaption></figure>
                <figure class="cred" data-cap="Muay Thai training certificate, Istanbul" tabindex="0" role="button"><figcaption class="cap">Muay Thai training certificate, Istanbul</figcaption></figure>
                <figure class="cred" data-cap="Workshop at the Faculty of Physical Education, University of Tehran" tabindex="0" role="button"><figcaption class="cap">Workshop at the Faculty of Physical Education, University of Tehran</figcaption></figure>
                <figure class="cred" data-cap="Attending the Muay Boran online seminar, USA" tabindex="0" role="button"><figcaption class="cap">Attending the Muay Boran online seminar, USA</figcaption></figure>
            </div>
        </div>
    </section>

    {{-- ============ تایم‌لاین ============ --}}
    <section class="tl-wrap">
        <div class="container">
            <h2>My Journey</h2>
            <div class="tl" id="tlList">
                <div class="tl-item">
                    <div class="tl-year">2013</div>
                    <div class="tl-label">Bodyguard &amp; Self-Defense Certificate</div>
                    <div class="tl-desc">After months of training and effort, I received a bodyguard and
                        self-defense certificate from the Istanbul authorities of the time — the
                        starting point of a path that made my passion for martial arts and
                        self-defense teaching burn even brighter. Alongside continuing my own
                        training under the best martial arts coaches, I began teaching others
                        interested in the field.</div>
                </div>
                <div class="tl-item">
                    <div class="tl-year">2016</div>
                    <div class="tl-label">Opened a Martial Arts Gym</div>
                    <div class="tl-desc">I opened my own private martial arts gym, and alongside it,
                        launched a tourism and nature-travel office. My round-the-clock work across
                        both fields — especially teaching many students — eventually led me to a
                        major success.</div>
                </div>
                <div class="tl-item">
                    <div class="tl-year">2019</div>
                    <div class="tl-label">Muay Thai Technical Certificate, Bangkok</div>
                    <div class="tl-desc">After extensive work as a coach and gym owner, I received my
                        Muay Thai technical certificate from Bangkok Muay Thai University. Living
                        and training in Thailand and competing there raised the quality of the
                        training I offered at my own gym considerably. At the same time, I pursued
                        sport science and coaching studies at the University of Tehran's Faculty
                        of Physical Education.</div>
                </div>
                <div class="tl-item">
                    <div class="tl-year">2022</div>
                    <div class="tl-label">Brazilian Jiu-Jitsu Self-Defense Technical Certificate, California</div>
                    <div class="tl-desc">I received a highly respected technical certificate from one
                        of the most reputable self-defense institutions in the United States, and
                        was able to bring my students along with me on this path. My love for this
                        work meant I never noticed time passing in my own classes.</div>
                </div>
                <div class="tl-item">
                    <div class="tl-year">2024</div>
                    <div class="tl-label">MSc in Sport Physiology</div>
                    <div class="tl-desc">With the goal of connecting modern sport science with martial
                        arts, I began a master's degree in Sport Physiology at Fenerbahçe University
                        in Istanbul, focusing on evaluating physiological indicators in athletic
                        training.</div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ CTA مگنتیک ============ --}}
    <section class="cta">
        <div class="magnetic-wrap">
            <a class="magnetic" id="magneticBtn" href="https://www.instagram.com/ehsandibazarcoaching" target="_blank" rel="noopener">Follow on Instagram</a>
        </div>
    </section>

</main>
</div>

{{-- مودال نمایش بزرگ مدرک --}}
<div class="modal" id="modal" role="dialog" aria-modal="true" aria-label="Enlarged credential view" style="position:fixed;inset:0;background:rgba(0,0,0,.9);display:none;align-items:center;justify-content:center;z-index:1000;padding:20px">
    <button class="modal-close" id="modalClose" aria-label="Close" style="position:absolute;top:20px;left:20px;color:#fff;font-size:30px;background:none;border:none;cursor:pointer">&times;</button>
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
