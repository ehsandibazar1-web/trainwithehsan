@extends('layouts.master-tr')

@section('title', "Sadece Teknik Neden Sizi Kurtarmaz — Ehsan Dibazar")
@section('meta_description', 'Gerçek bir çatışmanın ilk üç saniyesinde bedeninize ve zihninize ne olur — ve sonucu belirleyen becerinin neden ezberlenmiş teknik değil, karar verme olduğu.')
@section('canonical', url('/tr/blog/teknik-tek-basina-seni-kurtarmaz'))

@section('json-ld')
<script type="application/ld+json">
{
  "@@context": "https://schema.org",
  "@@type": "Article",
  "@@id": "https://trainwithehsan.com/tr/blog/teknik-tek-basina-seni-kurtarmaz#article",
  "headline": "Sadece Teknik Neden Sizi Kurtarmaz",
  "url": "https://trainwithehsan.com/tr/blog/teknik-tek-basina-seni-kurtarmaz",
  "author": {"@@id": "https://trainwithehsan.com/tr/about#person"},
  "publisher": {"@@id": "https://trainwithehsan.com/#organization"}
}
</script>
<script type="application/ld+json">
{
  "@@context": "https://schema.org",
  "@@type": "BreadcrumbList",
  "itemListElement": [
    {"@@type": "ListItem", "position": 1, "name": "Ana Sayfa", "item": "https://trainwithehsan.com/tr"},
    {"@@type": "ListItem", "position": 2, "name": "Blog", "item": "https://trainwithehsan.com/tr/blog"},
    {"@@type": "ListItem", "position": 3, "name": "Sadece Teknik Neden Sizi Kurtarmaz", "item": "https://trainwithehsan.com/tr/blog/teknik-tek-basina-seni-kurtarmaz"}
  ]
}
</script>
@endsection

@section('page-css')
<style>
    /* ===== عیناً از internal/style.css واقعی ===== */
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
        display:flex;align-items:center;justify-content:center;
    }
    .post-hero-image b{font-weight:800;font-size:30px;color:rgba(0,0,0,.2)}
    .post-title h1{font-weight:700;font-size:26px;color:#222;margin-bottom:10px;line-height:1.4}

    .article-meta{display:flex;flex-wrap:wrap;align-items:center;gap:14px;padding:10px 0;border-top:1px solid #eee;border-bottom:1px solid #eee;margin-bottom:1.2rem;font-size:13px;color:#666}
    .article-meta span{display:flex;align-items:center;gap:5px}

    .toc-box{background:#fafaf8;border:1px solid #e8e3d5;border-left:4px solid var(--gold);border-radius:8px;padding:18px 22px;margin:1.5rem 0}
    .toc-box h2{font-size:14px;font-weight:700;margin-bottom:10px;color:#333}
    .toc-box ul{list-style:decimal;padding-left:18px;margin:0}
    .toc-box ul li{padding:5px 0;font-size:13px;border-bottom:1px dashed #e5e5e5;color:#444}
    .toc-box ul li:last-child{border-bottom:none}
    .toc-box a{color:#444}
    .toc-box a:hover{color:var(--gold-dark,#c09d4c)}

    .article-body p{text-align:justify;font-size:16px;font-weight:300;line-height:2;color:#555;margin-bottom:1.1rem}
    .article-body h2{font-size:20px;font-weight:700;color:#222;margin:2rem 0 1rem}

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
    .hoosh-btn{display:inline-flex;align-items:center;gap:7px;background:var(--gold);color:#1a1a1a;font-size:14px;font-weight:700;padding:11px 22px;border-radius:8px;margin-top:8px;transition:.2s}
    .hoosh-btn:hover{background:#c9a227;color:#1a1a1a;transform:translateY(-2px)}

    .related-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:16px}
    @@media (max-width:820px){.related-grid{grid-template-columns:1fr}}
    .related-card{border:1px solid #eee}
    .related-thumb{height:130px;background:linear-gradient(135deg,#d8d3c4,#d9bb75);display:flex;align-items:center;justify-content:center}
    .related-thumb b{font-weight:800;color:rgba(0,0,0,.2)}
    .related-card h4{font-size:14px;padding:10px 10px 4px;color:#3a3a3a}
    .related-card p{font-size:12px;color:#666;padding:0 10px 10px}

    .sidebar-last-item{display:flex;gap:12px;border-bottom:1px dashed #d8d8d8;padding:12px 0}
    .sidebar-last-item .thumb{width:70px;height:60px;flex-shrink:0;border-radius:4px;background:linear-gradient(135deg,#d8d3c4,#d9bb75)}
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
            <span>Sadece Teknik Neden Sizi Kurtarmaz</span>
        </div>

        <div class="site-blog-post__box">
            <div>
                <div class="post-hero-image"><b>01</b></div>

                <div class="post-title"><h1>Sadece Teknik Neden Sizi Kurtarmaz</h1></div>

                <div class="article-meta">
                    <span>👤 Ehsan Dibazar</span>
                    <span>📅 Temmuz 2026</span>
                    <span>⏱ 4 dakika okuma</span>
                    <span>👁 1.240 görüntülenme</span>
                </div>

                <div class="toc-box" id="toc-container">
                    <h2>İçindekiler</h2>
                    <ul id="toc-list">
                        <li><a href="#the-first-three-seconds">İlk üç saniye</a></li>
                        <li><a href="#why-drilling-isnt-enough">Tekrar neden yeterli değil</a></li>
                        <li><a href="#training-the-decision">Sadece hareketi değil, kararı çalıştırmak</a></li>
                    </ul>
                </div>

                <div class="article-body" id="article-content">
                    <p>
                        Dövüş sanatlarına başlayan çoğu kişi, amacın teknik biriktirmek olduğunu
                        düşünür — daha geniş bir durum yelpazesi için daha büyük bir hareket
                        kutusu. Bu içgüdü yanlış değil, ama daha önemli bir şeyi kaçırıyor:
                        gerçek bir çatışmada sonuç, genellikle herhangi bir tekniğe ihtiyaç
                        duyulmadan önce belirlenir.
                    </p>

                    <h2 id="the-first-three-seconds">İlk üç saniye</h2>
                    <p>
                        Adrenalin, bedeninizin ve zihninizin çalışma şeklini neredeyse anında
                        değiştirir. İnce motor beceriler bozulur, tünel görüşü fark ettiklerinizi
                        daraltır ve seçenekleri düşünme yeteneğiniz basit, hızlı tepkilere
                        indirgenir. Bu, on yıldır antrenman yapıyor olun ya da hiç yapmamış olun
                        fark etmeksizin gerçekleşir — antrenmanın yarattığı fark, bu hızlı
                        tepkilerin varsayılan olarak neye döneceğidir.
                    </p>
                    <p>
                        Eğer tekniği yalnızca sakin, işbirlikçi bir sınıf ortamında çalıştıysanız,
                        sinir sisteminizin bu sakinlik ortadan kalktığında ne yapacağına dair
                        hiçbir referansı yoktur. İnsanları asıl donduran şey teknik eksikliği
                        değil, işte bu boşluktur.
                    </p>

                    <h2 id="why-drilling-isnt-enough">Tekrar neden yeterli değil</h2>
                    <p>
                        Tekrar, kas hafızası oluşturur ve kas hafızası önemlidir. Ancak tekrar tek
                        başına size bir hareketi yapmayı öğretir — baskı altında, direnç
                        karşısında, eksik bilgiyle ne zaman kullanacağınızı tanımayı öğretmez.
                        Bunlar farklı becerilerdir ve çoğu eğitim programı bunları hiç
                        çalıştırmaz.
                    </p>
                    <p>
                        Bu yüzden teknik açıdan çok yetenekli bazı dövüş sanatçıları gerçek bir
                        çatışmada donarken, çok daha az teknik cilaya sahip insanlar kendilerini
                        sakin bir şekilde idare edebilir. Fark teknik değildir. Baskı altında
                        karar vermedir — kendi koşullarında çalıştırılması gereken, özel ve
                        öğretilebilir bir beceridir.
                    </p>

                    <h2 id="training-the-decision">Sadece hareketi değil, kararı çalıştırmak</h2>
                    <p>
                        Etkili kendini savunma eğitimi üç şeyi sırayla katmanlar: farkındalık
                        (bir durumu erkenden okuyup ondan kaçınabilmek), simüle edilmiş baskı
                        altında karar verme (sadece koreografi değil, senaryo tabanlı
                        antrenmanlar) ve son olarak teknik — artık tek başına var olmak yerine
                        oturacağı bir bağlama sahip.
                    </p>
                    <p>
                        Burada öğretilen her kursun arkasındaki yapı budur — ister kadınlara
                        özel kendini savunma programı, ister tam yetişkin müfredatı, isterse
                        Brezilya Jiu-Jitsu olsun. Teknik hâlâ önemlidir — hem de çok — ama ancak
                        onu hiç gerektirip gerektirmeyeceğinize karar veren becerinin üzerine
                        inşa edildiğinde.
                    </p>
                </div>

                <div class="share-box">
                    <h3>Bu makaleyi paylaş</h3>
                    <div class="share-buttons">
                        <a href="https://t.me/share/url?url={{ urlencode(url('/tr/blog/teknik-tek-basina-seni-kurtarmaz')) }}&text={{ urlencode('Sadece Teknik Neden Sizi Kurtarmaz') }}" target="_blank" rel="noopener" class="share-btn share-btn-tg">Telegram</a>
                        <a href="https://wa.me/?text={{ urlencode('Sadece Teknik Neden Sizi Kurtarmaz ' . url('/tr/blog/teknik-tek-basina-seni-kurtarmaz')) }}" target="_blank" rel="noopener" class="share-btn share-btn-wa">WhatsApp</a>
                        <button class="share-btn share-btn-cp" onclick="copyLink(this)" data-url="{{ url('/tr/blog/teknik-tek-basina-seni-kurtarmaz') }}">Bağlantıyı kopyala</button>
                    </div>
                </div>

                <div class="hoosh-box">
                    <div style="display:flex;align-items:center;gap:16px;margin-bottom:18px">
                        <div class="hoosh-avatar">ED</div>
                        <div>
                            <div style="font-size:16px;font-weight:800;color:#fff">Merhaba, ben Ehsan Dibazar</div>
                            <div style="font-size:12px;color:var(--gold);margin-top:3px">Dövüş Sanatları ve Kendini Savunma Eğitmeni · Spor Bilimleri Yüksek Lisansı</div>
                        </div>
                    </div>
                    <p>
                        Yıllardır hayatımı dövüş sanatlarına bağladım — ama benim için madalyalardan
                        veya sertifikalardan her zaman daha önemli olan şey, insanların gelişimini
                        görmek oldu.
                    </p>
                    <p>
                        Dövüş sanatlarının sadece dövüşmekle ilgili olmadığına inanıyorum. Doğru
                        şekilde çalışıldığında gerçek özgüven oluşturur, karar verme yeteneğini
                        keskinleştirir, zihni baskı altında daha sakin tutar ve insanların zor
                        durumları daha iyi yönetmesine yardımcı olur.
                    </p>
                    <a href="{{ url('/tr/about') }}" class="hoosh-btn">Ehsan Dibazar hakkında daha fazla bilgi edinin</a>
                </div>

                {{-- ============ مقالات مرتبط ============ --}}
                <div class="site-blog__sidebar__item__header">
                    <fieldset style="border:0;border-bottom:4px solid var(--gold);padding:0 0 8px">
                        <legend style="font-size:17px;font-weight:800;color:#000;padding:0">İlgili Makaleler</legend>
                    </fieldset>
                </div>
                <div class="related-grid">
                    <a href="{{ url('/tr/blog') }}" class="related-card">
                        <div class="related-thumb"><b>02</b></div>
                        <h4>Küçük Yapılılar için BJJ: Dürüst Gerçek</h4>
                        <p>55 kiloluk bir başlangıç seviyesi 90 kiloluk bir saldırganı gerçekten kontrol edebilir mi?</p>
                    </a>
                    <a href="{{ url('/tr/blog') }}" class="related-card">
                        <div class="related-thumb"><b>03</b></div>
                        <h4>Kadınlar için Kendini Savunma: Nereden Başlamalı</h4>
                        <p>En yaygın tehdit senaryoları ve nereden başlamalı.</p>
                    </a>
                </div>
            </div>

            {{-- ============ سایدبار — آخرین مطالب ============ --}}
            <aside>
                <div class="site-blog__sidebar__item__header">
                    <fieldset style="border:0;border-bottom:4px solid var(--gold);padding:0 0 8px">
                        <legend style="font-size:17px;font-weight:800;color:#000;padding:0">Son Makaleler</legend>
                    </fieldset>
                </div>
                <div style="margin-top:10px">
                    <div class="sidebar-last-item">
                        <div class="thumb"></div>
                        <div>
                            <h5><a href="{{ url('/tr/blog/teknik-tek-basina-seni-kurtarmaz') }}" style="color:#3a3a3a">Sadece Teknik Neden Sizi Kurtarmaz</a></h5>
                            <span>Temmuz 2026</span>
                        </div>
                    </div>
                    <div class="sidebar-last-item">
                        <div class="thumb"></div>
                        <div>
                            <h5><a href="{{ url('/tr/blog') }}" style="color:#3a3a3a">Küçük Yapılılar için BJJ: Dürüst Gerçek</a></h5>
                            <span>Temmuz 2026</span>
                        </div>
                    </div>
                    <div class="sidebar-last-item">
                        <div class="thumb"></div>
                        <div>
                            <h5><a href="{{ url('/tr/blog') }}" style="color:#3a3a3a">Kadınlar için Kendini Savunma: Nereden Başlamalı</a></h5>
                            <span>Temmuz 2026</span>
                        </div>
                    </div>
                </div>
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

var tocList = document.getElementById('toc-list');
if (tocList) {
    tocList.querySelectorAll('a').forEach(function (a) {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            var target = document.querySelector(a.getAttribute('href'));
            if (target) target.scrollIntoView({ behavior: 'smooth' });
        });
    });
}

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
