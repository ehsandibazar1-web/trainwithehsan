@extends('layouts.master')

@section('title', 'Self-Defense & BJJ Training in Istanbul — Ehsan Dibazar | Martial Intelligence')
@section('meta_description', 'Learn self-defense in Istanbul with Ehsan Dibazar — MSc in Sport Science, 15+ years of experience. Courses for complete beginners, women and men, in person or through the training app.')
@section('canonical', url('/'))
@section('og_title', 'Self-Defense & BJJ Training in Istanbul — Ehsan Dibazar | Martial Intelligence')
@section('og_description', 'Learn self-defense in Istanbul with Ehsan Dibazar — MSc in Sport Science, 15+ years of experience. Courses for complete beginners, women and men, in person or through the training app.')

@section('json-ld')
<script type="application/ld+json">
{
  "@@context": "https://schema.org",
  "@@graph": [
    {
      "@@type": "Organization",
      "@@id": "https://trainwithehsan.com/#organization",
      "name": "Train with Ehsan",
      "url": "https://trainwithehsan.com",
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
      "url": "https://trainwithehsan.com/about"
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
    .hero-slide-text .hero-title{font-size:28px;color:#fff;font-weight:600;line-height:1.5;text-shadow:0 2px 10px rgba(0,0,0,.55),0 1px 3px rgba(0,0,0,.7)}
    .hero-slide-text .sub{font-size:18px;color:#f3f3f3;margin-top:10px;line-height:1.6;text-shadow:0 2px 8px rgba(0,0,0,.6),0 1px 3px rgba(0,0,0,.75)}
    @@media (max-width:767px){
        .hero-slide-text .hero-title{font-size:22px}
        .hero-slide-text .sub{font-size:15px}
    }
    /* .slider .owl-dots — نقطه‌ها؛ فعال #d9bb75 */
    .hero-dots{position:absolute;bottom:24px;left:20px;display:flex;justify-content:flex-start;gap:9px;z-index:2}
    .hero-dot{width:18px;height:18px;border-radius:50%;background:#a3a5a8;border:0;cursor:pointer;padding:0}
    .hero-dot.active{background:var(--gold)}

    /* ===== ردیف ویدیو — .row-video {margin-top:-94px} overlap روی اسلایدر ===== */
    /* بدون فاصلهٔ پایین: بخش «درباره» بلافاصله زیر کارت‌های ویدیو شروع می‌شود (مثل سایت مرجع) */
    .video-section{padding-bottom:0}
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
    /* طبق CSS واقعی سایت اصلی: کل کارت یه جعبه‌ی ۲۳۲px با متن overlay-شده روش (نه جدا زیرش) */
    .video-card{display:block;cursor:pointer;position:relative;height:232px;overflow:hidden}
    .video-card__img{
        position:absolute;inset:0;
        background:linear-gradient(135deg,#2c2c2c 0%,#3a3222 60%,#8a6d1f 170%);
        background-size:cover!important;background-position:center!important;
    }
    .video-icon{
        position:absolute;inset:0;margin:auto;width:47px;height:46px;
        display:flex;align-items:center;justify-content:center;
        background:rgba(0,0,0,.5);border:2px solid #fff;border-radius:50%;
        color:#fff;font-size:16px;z-index:2;transition:.2s;
    }
    .video-card:hover .video-icon{background:var(--gold);border-color:var(--gold);color:#000}
    .text-video{
        position:absolute;inset:0% 0% 0px;width:100%;color:#fff;
        background:linear-gradient(rgba(0,0,0,0) 3%,rgba(0,0,0,.36) 48%,rgba(0,0,0,.65) 85%,rgba(0,0,0,.65) 98%);
        display:flex!important;flex-direction:column-reverse;
        padding:20px;text-align:center;font-weight:600;font-size:14px;z-index:1;
    }

    /* ===== درباره/اپلیکیشن — سفید؛ عکس سرریزکننده (bleed) مثل سایت اصلی، نه باکس محدود ===== */
    /* دسکتاپ: عکس تمام‌ارتفاعِ بخش، چسبیده به بالا/چپ/پایین؛ موبایل: استاتیک زیر متن، چسبیده به پایین */
    .about-section{padding:60px 0 0;position:relative;background:#fff;overflow:hidden;min-height:420px}
    @@media (max-width:767px){.about-section{padding-top:32px;padding-bottom:0}}
    .about-text-col{max-width:475px;position:relative;z-index:1;margin-left:560px}
    @@media (max-width:767px){.about-text-col{margin-left:0;max-width:100%;margin-top:0}}
    .abou-company{color:#393e40;font-weight:800;font-size:2.2rem;margin-bottom:10px;line-height:1.3}
    @@media (max-width:767px){.abou-company{font-size:1.8rem;text-align:center}}
    .sub-title{color:#393e40;font-weight:500;font-size:14px}
    .about-text{color:#3b3b3b;line-height:2.2;font-size:13px;text-align:justify;margin:12px 0 8px}
    .about-cta{margin-top:16px}
    @@media (max-width:640px){.about-cta{margin-top:16px}}
    /* عکس — دسکتاپ: مثل سایت مرجع، تصویرِ تمام‌قد با نسبتِ طبیعی (width:auto) که به بالا/چپ/
       پایینِ بخش چسبیده و از لبهٔ چپِ صفحه بیرون می‌زند. ستون متن عمداً به 560px هل داده شده
       تا از دستکش/دستِ فایتر (که لبهٔ راستِ عکس است) فاصله بگیرد و رویش نیفتد. max-width یک
       «سقفِ ایمنی» است که لبهٔ راستِ عکس را همیشه قبل از ستون متن نگه می‌دارد؛ برای عکس‌های
       معمولی عرضِ طبیعی کمتر از این سقف است، پس بدون برش کاملِ بدن (و دستکش) دیده می‌شود. */
    .about-bleed-img{
        width:469px;max-width:100%;height:auto;margin-top:20px;display:block;
    }
    @@media (min-width:768px){
        .about-bleed-img{
            position:absolute;left:0;top:0;bottom:0;margin-top:0;height:100%;width:auto;
            max-width:calc(max((100vw - 1140px) / 2, 0px) + 500px);
            object-fit:cover;object-position:left top;
        }
    }
    .img-about-box{
        width:469px;max-width:100%;aspect-ratio:469/434;margin-top:20px;
        background:linear-gradient(135deg,#f0ede4 0%,#e2d3a8 70%,var(--gold) 160%);
        display:flex;align-items:flex-end;padding:20px;
    }
    @@media (min-width:768px){
        .img-about-box{position:absolute;left:0;bottom:0;margin-top:0}
    }
    .img-about-box span{font-weight:800;font-size:34px;color:rgba(0,0,0,.18)}

    /* ===== دوره‌ها — پس‌زمینهٔ عکسِ رینگ تیره‌شده، عیناً مطابق ehsandibazar.com؛ گرادیانِ تیره
       روی عکس برای حفظِ کنتراستِ متن سفید (.title-counter/.sun-counter) اضافه شده ===== */
    .counter{
        background:linear-gradient(rgba(15,15,15,.72),rgba(15,15,15,.82)),url('{{ asset('images/homepage/bg-courses.jpg') }}') 0 0/cover no-repeat;
        min-height:508px;color:#fff;padding:50px 0 60px;
    }
    @@media (max-width:767px){.counter{background-position:center!important}}
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
    /* دسکتاپ: سه کارت کل عرض را پر می‌کنند (مثل سایت اصلی)، نه کارت‌های کوچک چپ‌چین */
    @@media (min-width:768px){
        .learn-grid{overflow-x:visible;justify-content:center}
        .l-box{flex:0 0 calc((100% - 40px) / 3)}
        .courses-carousel .car-arrow{display:none}
    }
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

    /* ===== مقالات — پس‌زمینهٔ بافت دیوار/بتنِ روشن، عیناً مطابق ehsandibazar.com؛ چون کارت‌های
       خبر خودشان پس‌زمینهٔ سفید مجزا دارند (.news-card{background:#fff})، نیازی به گرادیان
       تیره‌کننده نیست. جلوهٔ پارالاکس (عکس هنگام اسکرول کندتر از محتوا حرکت می‌کند) — طبق درخواست
       صریح کاربر فقط همین بخش، نه ۳ بخش دیگر. background-attachment:fixed بومی جایگزین شد چون
       باعث ری‌پینت/لرزشِ محسوس مرورگر (به‌ویژه کروم/ویندوز) هنگام توقفِ اسکرول می‌شد — لایهٔ
       .section-news-bg جدا با transform (فقط GPU compositing، بدون ری‌پینت) کاملاً نرم است؛
       جاوااسکریپت پایین صفحه offset را روی هر فریمِ اسکرول (rAF-throttled) تنظیم می‌کند و اگر
       prefers-reduced-motion فعال باشد، اصلاً اجرا نمی‌شود (پس‌زمینه ثابت می‌ماند) ===== */
    .section-news{position:relative;overflow:hidden;padding:56px 0 64px}
    .section-news-bg{
        position:absolute;inset:-10% 0;z-index:0;
        background:url('{{ asset('images/homepage/bg-articles.jpg') }}') center/cover no-repeat;
        transform:translate3d(0,0,0);will-change:transform;
    }
    .section-news>.wrap{position:relative;z-index:1}
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

    /* ===== نتایج اعضا — .result-section {background:#fff} + واترمارک لوگوی سپر (ED)، عیناً
       مطابق ehsandibazar.com — عکس PNG شفاف است و خودش کم‌رنگ/کم‌کنتراست صادر شده، بدون تکرار،
       گوشهٔ پایین‌سمت‌راست، تا متن‌های تیره روی سفید خوانا بمانند ===== */
    .result-section{background:#fff url('{{ asset('images/homepage/watermark-shield.png') }}') no-repeat right center / 426px 520px;padding:70px 0 50px}
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

    /* ===== ویترین اینستاگرام (Instagram Showcase) — جایگزین دو نوار قدیمی؛ کارت پرمیوم
       (گوشه‌ی گرد، سایه، بوردر ظریف طلایی) کنار متن در دسکتاپ، زیر متن در موبایل. قاب
       امبد به نسبت تقریبی ۹:۱۶ (عمودی، مثل ریلز/پست‌های اینستاگرام) رزرو می‌شود — همان
       ویژگی امبد رسمی/لیزی‌لود/فال‌بک قبلی، فقط با تناسب تصویر عمودی‌تر. دو ردیف مستقل
       (برای دو پست/ریل/پیج جدا، مثل سایت مرجع ehsandibazar.com) با پس‌زمینه‌ی متناوب —
       ردیف اول همیشه نمایش داده می‌شود (fallback در صورت غیرفعال بودن)، ردیف دوم کاملاً
       اختیاری و پیش‌فرض مخفی است تا رفتار قبلی برای مدیرهایی که فقط ردیف اول را تنظیم
       کرده‌اند بدون تغییر بماند ===== */
    .insta-showcase{background:#ebebeb url('{{ asset('images/homepage/bg-instagram-row1.jpg') }}') 0 0/cover no-repeat;border-top:1px solid #c2c2c2;padding:56px 0}
    .insta-showcase--row2{background:#fff url('{{ asset('images/homepage/bg-instagram-row2.jpg') }}') 0 0/cover no-repeat;border-top:0}
    .insta-showcase-grid{display:grid;grid-template-columns:1fr 1fr;gap:44px;align-items:center}
    /* تبلت هم مثل دسکتاپ دو ستونی بماند (متن یک طرف، کادر طرف دیگر) — فقط روی موبایل تک‌ستونی
       می‌شود؛ نقطهٔ شکست از ۹۰۰ به ۶۴۰ کاهش یافت تا تبلت‌ها (۷۶۸/۸۲۰/…px) کنارِهم بمانند */
    @@media (max-width:900px){.insta-showcase-grid{gap:28px}}
    @@media (max-width:640px){.insta-showcase-grid{grid-template-columns:1fr;gap:32px}}
    /* ردیف دوم آینه‌ی ردیف اول است: کادر اینستاگرام سمت چپ، لوگو/متن سمت راست (زیگزاگ مثل سایت
       مرجع). فقط در حالت دوستونی جای ستون‌ها عوض می‌شود؛ روی موبایلِ تک‌ستونی، متن همیشه اول می‌آید */
    @@media (min-width:641px){
        .insta-showcase--row2 .insta-showcase-card{order:1}
        .insta-showcase--row2 .insta-showcase-text{order:2}
    }
    /* محتوای ستون متن (لوگو/آیکون، تیتر، زیرتیتر، دکمه) همیشه زیرِ هم و وسط‌چین است —
       چه در حالت دوستونیِ دسکتاپ/تبلت و چه در موبایلِ تک‌ستونی */
    .insta-showcase-text{text-align:center}
    .insta-showcase-text .insta-showcase-logo{width:100px;height:auto;margin:0 auto 20px}
    .insta-showcase-text h2{font-size:24px;font-weight:700;color:var(--title);margin-bottom:10px;line-height:1.35}
    .insta-showcase-text p{font-size:14px;color:var(--text);line-height:1.9;margin:0 auto 20px;max-width:420px}
    .insta-showcase-btn{
        display:inline-flex;align-items:center;gap:8px;background:#252525;color:var(--gold);
        padding:11px 28px;border-radius:30px;font-weight:600;font-size:14px;transition:.25s linear;
    }
    .insta-showcase-btn:hover{background-color:var(--gold);color:#252525}

    .insta-showcase-card{display:flex;justify-content:center}
    .insta-embed-wrap{
        position:relative;width:100%;max-width:360px;min-height:640px;
        background:#f9f7f2;border:1px solid #e8e3d5;border-radius:16px;
        box-shadow:0 18px 40px -18px rgba(37,32,15,.35);
        overflow:hidden;display:flex;align-items:center;justify-content:center;
        transition:transform .3s ease,box-shadow .3s ease,border-color .3s ease;
    }
    .insta-embed-wrap:hover{transform:translateY(-4px);box-shadow:0 24px 48px -16px rgba(37,32,15,.4);border-color:var(--gold)}
    .insta-embed-wrap iframe{border-radius:16px!important}
    .insta-embed-wrap .instagram-media{margin:0 auto!important;min-width:326px!important}
    .insta-embed-placeholder{display:flex;align-items:center;justify-content:center;width:100%;min-height:640px}
    .insta-embed-spinner{
        width:32px;height:32px;border-radius:50%;
        border:3px solid #e8e3d5;border-top-color:var(--gold);
        animation:insta-spin 1s linear infinite;
    }
    @@keyframes insta-spin{to{transform:rotate(360deg)}}
    @@media (prefers-reduced-motion: reduce){.insta-embed-spinner{animation-duration:2.5s}}
    .insta-embed-fallback-img{position:absolute;inset:0;width:100%;height:100%;min-height:640px;object-fit:cover}
    .insta-embed-fallback-overlay{
        position:relative;z-index:1;display:flex;flex-direction:column;align-items:center;gap:14px;
        justify-content:center;width:100%;min-height:640px;padding:32px;text-align:center;
        background:linear-gradient(180deg,rgba(10,8,9,.15) 0%,rgba(10,8,9,.72) 100%);
    }
    .insta-embed-fallback-overlay .insta-showcase-logo{width:46px;height:auto}
    .insta-embed-fallback-overlay p{color:#f1f1f1;font-size:13px;margin:0}

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
    {{-- مقدار فقط-فاصله عمداً «پر» حساب می‌شود — راه مدیر سایت برای مخفی‌کردن متن پیش‌فرض بدون کد --}}
    @php($v = fn($k, $d = '') => (($s[$k] ?? null) !== null && ($s[$k] ?? '') !== '') ? $s[$k] : $d)
    {{-- لینک ویدیو را به فرم embed تبدیل می‌کند: watch?v= / youtu.be / shorts و vimeo قابلِ
         iframe نیستند؛ مدیر معمولاً همان لینکِ عادی را کپی می‌کند، پس اینجا نرمال‌سازی می‌شود --}}
    @php($embed = function ($u) {
        $u = trim((string) $u);
        if ($u === '') return '';
        if (preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~i', $u, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1];
        }
        if (preg_match('~vimeo\.com/(?:video/)?(\d+)~i', $u, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1];
        }
        return $u;
    })

    {{-- ============ اسلایدر هیرو ============ --}}
    {{-- فقط اسلاید اول h1 دارد (تنها H1 قابل‌مشاهده صفحه) — بقیه h2 هستند تا چند H1 در DOM نداشته باشیم --}}
    <div class="hero-slider">
        <div class="hero-slide active @if($v('hero1_image')) has-bg @endif" @if($v('hero1_image')) style="background:url('{{ asset('storage/' . $v('hero1_image')) }}') center/cover no-repeat" @endif>
            <div class="wrap">
                <div class="hero-slide-text">
                    <h1 class="hero-title">{{ $v('hero1_title', 'Self-Defense & Martial Arts Training') }}</h1>
                    <div class="sub">{{ $v('hero1_sub', 'For complete beginners — no athletic background, no age limit, for both women and men. In Istanbul or online.') }}</div>
                </div>
            </div>
        </div>
        <div class="hero-slide @if($v('hero2_image')) has-bg @endif" @if($v('hero2_image')) style="background:url('{{ asset('storage/' . $v('hero2_image')) }}') center/cover no-repeat" @endif>
            <div class="wrap">
                <div class="hero-slide-text">
                    <h2 class="hero-title">{{ $v('hero2_title', 'Brazilian Jiu-Jitsu: the art of leverage') }}</h2>
                    <div class="sub">{{ $v('hero2_sub', 'Built so a smaller person can control a stronger attacker — skill and position instead of raw strength.') }}</div>
                </div>
            </div>
        </div>
        <div class="hero-slide @if($v('hero3_image')) has-bg @endif" @if($v('hero3_image')) style="background:url('{{ asset('storage/' . $v('hero3_image')) }}') center/cover no-repeat" @endif>
            <div class="wrap">
                <div class="hero-slide-text">
                    <h2 class="hero-title">{{ $v('hero3_title', 'Martial Intelligence') }}</h2>
                    <div class="sub">{{ $v('hero3_sub', 'Decision-making under pressure — the skill that matters most in a real confrontation.') }}</div>
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
            @php($videoDefaults = ['Why train martial arts & self-defense', 'How the training works', 'What is self-defense & martial sport'])
            <div class="row-video reveal-group">
                @foreach([1, 2, 3] as $i)
                @php($vEmbed = $embed($v("video{$i}_embed")))
                @php($vFile = $v("video{$i}_file"))
                @php($vThumb = $v("video{$i}_thumb"))
                <div class="video-card js-video reveal" data-embed="{{ $vEmbed }}" data-file="{{ $vFile ? asset('storage/' . $vFile) : '' }}">
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
            <div class="about-text-col reveal">
                <h2 class="abou-company">{{ $v('app_title', 'The Ehsan Dibazar Self-Defense Academy app') }}</h2>
                <div class="sub-title">{{ $v('app_subtitle', 'Step-by-step video training, anywhere') }}</div>
                <div class="about-text">{{ $v('app_text', 'The training app contains structured video courses that teach the process of self-defense step by step, so you can learn at your own pace. Our focus is on giving you the most effective training programs in martial arts and self-defense — with real quality, in the right order, so you actually reach your goal.') }}</div>
                <div class="about-cta">
                    <a href="{{ url('/about') }}" class="show-more">{{ $v('app_button_label', 'Download the app') }}</a>
                </div>
            </div>
        </div>
        @if($v('app_image'))
            <img src="{{ asset('storage/' . $v('app_image')) }}" alt="{{ $v('app_title', 'App') }}" class="about-bleed-img reveal">
        @else
            <div class="img-about-box reveal"><span>App</span></div>
        @endif
    </section>

    {{-- ============ دوره‌های آموزشی و محصولات ============ --}}
    <section class="counter">
        <div class="wrap">
            <div class="reveal">
                <h2 class="title-counter">{{ $v('courses_title', 'Courses & Products') }}</h2>
                <div class="sun-counter">{{ $v('courses_subtitle', 'Choose the format that fits you — in-person coaching in Istanbul, remote training through the app, or Brazilian Jiu-Jitsu classes.') }}</div>
            </div>
            @php($courseDefaults = [['In-Person', 'In-Person Coaching'], ['Remote', 'Remote Training (App)'], ['BJJ', 'Brazilian Jiu-Jitsu']])
            <div class="courses-carousel" data-carousel>
                <button class="car-arrow car-prev" aria-label="Previous">‹</button>
                <div class="learn-grid carousel-track reveal-group">
                @foreach([1, 2, 3] as $i)
                @php($courseLink = trim($v("course{$i}_link")))
                @php($courseHref = $courseLink !== '' ? (str_starts_with($courseLink, 'http') ? $courseLink : url($courseLink)) : url('/blog'))
                <a href="{{ $courseHref }}" class="l-box reveal">
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
        <div class="section-news-bg" aria-hidden="true"></div>
        <div class="wrap">
            <div class="reveal">
                <h3 class="title-section">Training Articles</h3>
                <div class="sub-title-section">
                    <a href="{{ url('/blog') }}">View the full archive ⟶</a>
                </div>
            </div>
            <div class="articles-carousel" data-carousel>
                <button class="car-arrow car-prev" aria-label="Previous">‹</button>
                <div class="news-grid carousel-track reveal-group">
                @forelse($latestArticles ?? collect() as $article)
                <a class="news-card reveal" href="{{ url('/blog/' . $article->slug) }}">
                    <div class="img-news" @if($article->image_path) style="background-image:url('{{ $article->optimized_image_url ?? asset('storage/' . $article->image_path) }}');background-size:cover;background-position:center" @endif>
                        @unless($article->image_path)<b>{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</b>@endunless
                    </div>
                    <div class="title-news">{{ $article->title }}</div>
                    <div class="news-short-text">{{ $article->excerpt ?: Str::limit(strip_tags($article->body), 100) }}</div>
                    <div class="news-more-row"><span class="more-news">Read more</span></div>
                </a>
                @empty
                <p style="flex:1 0 100%;text-align:center;color:#888;font-size:13px;padding:20px 0">New articles are coming soon.</p>
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
                <div class="reveal">
                    <h2 class="abou-company">{{ $v('members_title', 'Member Results') }}</h2>
                    <div class="sub-title">{{ $v('members_subtitle', 'Martial arts and self-defense training that builds real capability — and gives people stronger, more confident lives.') }}</div>
                    <div class="about-cta">
                        <a href="{{ url('/about') }}" class="show-more">{{ $v('members_button_label', 'View all member results') }}</a>
                    </div>
                </div>
                <div>
                    @php($membersList = !empty($members) ? $members : [['name' => 'Sajjad'], ['name' => 'Davoud'], ['name' => 'Omid'], ['name' => 'Mohammad'], ['name' => 'Amir'], ['name' => 'Sara']])
                    <ul class="user-list reveal-group">
                        @foreach($membersList as $m)
                        @php($mName = trim($m['name'] ?? '') !== '' ? $m['name'] : 'Member')
                        <li class="reveal">
                            <div class="img-user" @if(!empty($m['photo'])) style="background-image:url('{{ asset('storage/' . $m['photo']) }}');background-size:cover;background-position:center" @endif>
                                @if(empty($m['photo'])){{ mb_substr($mName, 0, 1) }}@endif
                            </div>{{ $mName }}
                        </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ ویترین اینستاگرام (Instagram Showcase) — جایگزین دو نوار قدیمی؛ فعال‌سازی،
         لینک embed و متن‌ها از پنل مدیریت (Homepage Settings → Instagram Showcase) می‌آید.
         دو ردیف مستقل (برای دو پست/ریل/پیج جدا) روی همان دیتای s رندر می‌شوند — ردیف اول
         همیشه نمایش داده می‌شود (رفتار قبلی، بدون تغییر)، ردیف دوم به‌محض داشتن محتوای واقعی
         (Embed URL یا Fallback Image یا روشن‌بودن Enable) ظاهر می‌شود — نه فقط با روشن‌بودن
         Enable به‌تنهایی؛ نمایش امبد زنده هم فقط به وجود Embed URL بستگی دارد، نه توگل Enable —
         تا رفتار هر دو ردیف برای «آپلود عکس» و «چسباندن لینک» کاملاً یکسان باشد ============ --}}
    @php($instaRows = [
        [
            'section_class' => '',
            'always_visible' => true,
            'enabled' => $v('insta_showcase_enabled'),
            'embed_url' => $v('insta_embed_url'),
            'title' => $v('insta_showcase_title', 'Follow the Journey'),
            'subtitle' => $v('insta_showcase_subtitle', 'Real training moments, straight from Instagram.'),
            'button_text' => $v('insta_showcase_button_text', 'Follow us on Instagram'),
            'button_url' => $v('insta_showcase_button_url') ?: $v('insta_url', 'https://instagram.com'),
            'fallback_image' => $v('insta_showcase_fallback_image') ? asset('storage/' . $v('insta_showcase_fallback_image')) : '',
        ],
        [
            'section_class' => ' insta-showcase--row2',
            'always_visible' => false,
            'enabled' => $v('insta_showcase2_enabled'),
            'embed_url' => $v('insta_showcase2_embed_url'),
            'title' => $v('insta_showcase2_title', 'More From Instagram'),
            'subtitle' => $v('insta_showcase2_subtitle', 'More highlights from our page.'),
            'button_text' => $v('insta_showcase2_button_text', 'Follow us on Instagram'),
            'button_url' => $v('insta_showcase2_button_url') ?: $v('insta_url', 'https://instagram.com'),
            'fallback_image' => $v('insta_showcase2_fallback_image') ? asset('storage/' . $v('insta_showcase2_fallback_image')) : '',
        ],
    ])
    @foreach ($instaRows as $row)
        @continue(!$row['always_visible'] && !$row['enabled'] && !$row['embed_url'] && !$row['fallback_image'])
        <section class="insta-showcase{{ $row['section_class'] }}">
            <div class="wrap">
                <div class="insta-showcase-grid reveal-group">
                    <div class="insta-showcase-text reveal">
                        <img src="{{ asset('storage/homepage/logo-inst.png') }}" alt="Instagram" class="insta-showcase-logo">
                        <h2>{{ $row['title'] }}</h2>
                        <p>{{ $row['subtitle'] }}</p>
                        <a href="{{ $row['button_url'] }}" class="insta-showcase-btn" rel="noopener" target="_blank">
                            {{ $row['button_text'] }}
                        </a>
                    </div>

                    <div class="insta-showcase-card reveal">
                        @if($row['embed_url'])
                            {{-- امبد رسمی اینستاگرام فقط وقتی این بخش وارد نمای دستگاه می‌شود لود می‌شود
                                 (نگاه کنید به page-js پایین صفحه) — تا آن لحظه فقط یک placeholder با
                                 ارتفاع رزروشده اینجاست تا CLS ایجاد نشود --}}
                            <div class="insta-embed-wrap js-insta-embed"
                                 data-insta-url="{{ $row['embed_url'] }}"
                                 data-follow-url="{{ $row['button_url'] }}"
                                 data-fallback-img="{{ $row['fallback_image'] }}"
                                 data-logo="{{ asset('storage/homepage/logo-inst.png') }}">
                                <div class="insta-embed-placeholder">
                                    <span class="insta-embed-spinner" aria-hidden="true"></span>
                                </div>
                            </div>
                        @else
                            <div class="insta-embed-wrap">
                                @if($row['fallback_image'])
                                    <img src="{{ $row['fallback_image'] }}" alt="Instagram" class="insta-embed-fallback-img">
                                @endif
                                <div class="insta-embed-fallback-overlay">
                                    <img src="{{ asset('storage/homepage/logo-inst.png') }}" alt="Instagram" class="insta-showcase-logo">
                                    <a href="{{ $v('insta_url', 'https://instagram.com') }}" class="insta-showcase-btn" rel="noopener" target="_blank">Watch on Instagram</a>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </section>
    @endforeach

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

    // ===== پارالاکس بخش مقالات — لایهٔ .section-news-bg را با transform جابه‌جا می‌کند (فقط
    // GPU compositing، بدون ری‌پینت مرورگر) به‌جای background-attachment:fixed بومی که باعث
    // لرزش/جابه‌جایی محسوس هنگام توقف اسکرول می‌شد. offset با rAF محدود می‌شود (حداکثر ۶۰px،
    // هم‌راستا با inset:-10% لایهٔ پس‌زمینه تا هرگز لبه‌ای دیده نشود) و فقط وقتی بخش داخل دید
    // است محاسبه می‌شود. اگر prefers-reduced-motion فعال باشد، اصلاً اجرا نمی‌شود و پس‌زمینه
    // بدون هیچ حرکتی (استاتیک) نمایش داده می‌شود.
    (function () {
        var section = document.querySelector('.section-news');
        var bg = section ? section.querySelector('.section-news-bg') : null;
        if (!section || !bg) return;
        if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        var ticking = false;
        function update() {
            var rect = section.getBoundingClientRect();
            if (rect.bottom >= 0 && rect.top <= window.innerHeight) {
                var offset = Math.max(-60, Math.min(60, rect.top * 0.15));
                bg.style.transform = 'translate3d(0,' + offset.toFixed(1) + 'px,0)';
            }
            ticking = false;
        }
        window.addEventListener('scroll', function () {
            if (!ticking) { window.requestAnimationFrame(update); ticking = true; }
        }, { passive: true });
        update();
    })();

    // ===== ویترین اینستاگرام — امبد رسمی (instagram.com/embed.js) فقط وقتی کارت وارد نمای
    // دستگاه می‌شود لود می‌شود (IntersectionObserver، همون الگوی reveal سراسر سایت) — تا آن
    // لحظه هیچ اسکریپت/درخواست شبکه‌ای اضافه‌ای ارسال نمی‌شود. اگر اسکریپت لود نشد یا در ۸
    // ثانیه پاسخ نداد (مسدود/کند)، به‌جای جعبه‌ی خالی، عکس fallback + آیکون + دکمه‌ی «Watch on
    // Instagram» نشان داده می‌شود =====
    (function () {
        var wraps = document.querySelectorAll('.js-insta-embed');
        if (!wraps.length) return;

        var scriptState = 'idle';

        function renderFallback(wrap) {
            var fallbackImg = wrap.getAttribute('data-fallback-img');
            var followUrl = wrap.getAttribute('data-follow-url') || 'https://instagram.com';
            var logo = wrap.getAttribute('data-logo');
            wrap.innerHTML =
                (fallbackImg ? '<img src="' + fallbackImg + '" alt="Instagram" class="insta-embed-fallback-img">' : '') +
                '<div class="insta-embed-fallback-overlay">' +
                (logo ? '<img src="' + logo + '" alt="Instagram" class="insta-showcase-logo">' : '') +
                '<a href="' + followUrl + '" class="insta-showcase-btn" rel="noopener" target="_blank">Watch on Instagram</a>' +
                '</div>';
        }

        function loadInstagramScript(cb) {
            if (scriptState === 'loaded') { cb(true); return; }
            if (scriptState === 'failed') { cb(false); return; }
            if (scriptState === 'loading') {
                document.addEventListener('insta-embed-script-ready', function handler(e) {
                    document.removeEventListener('insta-embed-script-ready', handler);
                    cb(e.detail.ok);
                });
                return;
            }
            scriptState = 'loading';
            var s = document.createElement('script');
            s.src = 'https://www.instagram.com/embed.js';
            s.async = true;
            var settled = false;
            function settle(ok) {
                if (settled) return;
                settled = true;
                scriptState = ok ? 'loaded' : 'failed';
                cb(ok);
                document.dispatchEvent(new CustomEvent('insta-embed-script-ready', { detail: { ok: ok } }));
            }
            s.onload = function () { settle(true); };
            s.onerror = function () { settle(false); };
            document.body.appendChild(s);
            setTimeout(function () { settle(false); }, 8000);
        }

        function renderEmbed(wrap) {
            var url = wrap.getAttribute('data-insta-url');
            if (!url) { renderFallback(wrap); return; }

            var bq = document.createElement('blockquote');
            bq.className = 'instagram-media';
            bq.setAttribute('data-instgrm-permalink', url);
            bq.setAttribute('data-instgrm-version', '14');
            var link = document.createElement('a');
            link.href = url;
            link.target = '_blank';
            link.rel = 'noopener';
            bq.appendChild(link);
            wrap.innerHTML = '';
            wrap.appendChild(bq);

            loadInstagramScript(function (ok) {
                if (!ok) { renderFallback(wrap); return; }
                if (window.instgrm && window.instgrm.Embeds) { window.instgrm.Embeds.process(); }
            });
        }

        if (typeof IntersectionObserver === 'undefined') {
            wraps.forEach(renderEmbed);
        } else {
            var io = new IntersectionObserver(function (entries, obs) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        renderEmbed(entry.target);
                        obs.unobserve(entry.target);
                    }
                });
            }, { rootMargin: '200px 0px', threshold: 0.01 });
            wraps.forEach(function (w) { io.observe(w); });
        }
    })();
</script>
@endsection
