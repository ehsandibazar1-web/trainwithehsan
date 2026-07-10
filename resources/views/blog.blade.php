@extends('layouts.master')

@section('title', 'Blog — Self-Defense & Martial Arts Articles | Ehsan Dibazar')
@section('meta_description', 'Practical articles on self-defense, Brazilian Jiu-Jitsu, and martial arts training by Ehsan Dibazar — for complete beginners, women and men.')
@section('canonical', url('/blog'))

@section('page-css')
<style>
    /* ===== عیناً از internal/style.css واقعی — رنگ هاور از بنفش لگاسی به گلد برند تغییر کرد ===== */
    .site-blog{background-color:#f6f6f6;padding:50px 0}
    .site-blog__box{box-shadow:2px 5px 14px -5px #dbdbdb;background-color:#fff;padding:26px;display:grid;grid-template-columns:1fr 2fr;gap:30px}
    @@media (max-width:860px){.site-blog__box{grid-template-columns:1fr}}

    /* ===== سایدبار ===== */
    .site-blog__sidebar__item{margin-bottom:24px}
    .site-blog__sidebar__item__header fieldset{border-bottom:4px solid var(--gold);border:0;border-bottom:4px solid var(--gold);padding:0 0 8px}
    .site-blog__sidebar__item__header legend{font-size:17px;font-weight:800;color:#000;padding:0}
    .cat-list{list-style:none;margin-top:10px}
    .cat-list li{display:flex;justify-content:space-between;border-bottom:1px dashed #d8d8d8;padding:10px 0}
    .cat-list li a{color:#888;font-size:14px}
    .cat-list li a:hover{color:var(--gold-dark,#c09d4c)}
    .cat-list li .badge-count{background:#1d1d1d;color:#fff;font-size:11px;border-radius:10px;padding:2px 8px}
    .popular-item{display:flex;gap:12px;border-bottom:1px dashed #d8d8d8;padding:10px 0}
    .popular-item .thumb{
        width:70px;height:60px;flex-shrink:0;border-radius:4px;overflow:hidden;
        background:linear-gradient(135deg,#d8d3c4,#d9bb75);
    }
    .popular-item h6{font-size:14px;font-weight:800;color:#696969}
    .popular-item h6 a:hover{color:var(--gold-dark,#c09d4c)}

    /* ===== کارت‌های پست ===== */
    .posts-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:14px}
    @@media (max-width:600px){.posts-grid{grid-template-columns:1fr}}
    .post-item{overflow:hidden}
    .post-item__image{position:relative;overflow:hidden}
    .post-item__image .thumb{
        height:200px;display:block;
        background:linear-gradient(135deg,#d8d3c4 0%,#cdb87f 80%,var(--gold) 160%);
        transition:transform .4s ease-in-out;display:flex;align-items:center;justify-content:center;
    }
    .post-item:hover .thumb{transform:scale(1.08)}
    .thumb b{font-weight:800;font-size:22px;color:rgba(0,0,0,.2)}
    .post-item__cat{
        position:absolute;top:0;right:0;background-color:var(--gold);
        color:#1d1d1d;font-size:13px;padding:4px 10px;font-weight:600;
    }
    .post-item__desc{padding:12px 4px}
    .post-item__desc__title{font-size:16px;font-weight:700;color:#3a3a3a;margin-bottom:6px}
    .post-item__desc__title:hover{color:var(--gold-dark,#c09d4c)}
    .post-item__desc__list{font-size:11px;color:#666;margin-bottom:8px}
    .post-item__desc__list span:not(:last-child)::after{content:"·";margin:0 5px}
    .post-item__desc__detail{font-size:13.5px;color:#666;text-align:justify}
    .blog-note{grid-column:1/-1;color:#888;font-size:13px;text-align:center;padding:20px 0 4px}
</style>
@endsection

@section('content')

    {{-- ============ نوار عنوان صفحه ============ --}}
    <div class="page-title-bar" style="background:#1d1d1d;padding:26px 0;color:#fff">
        <div class="wrap">
            <h1 style="color:#fff;font-size:26px;font-weight:700">Blog</h1>
            <div style="color:#9a9a9a;font-size:13px;margin-top:6px">
                <a href="{{ url('/') }}" style="color:#9a9a9a">Home</a>
                <span style="margin:0 6px;color:#555">/</span>
                <span style="color:var(--gold)">Blog</span>
            </div>
        </div>
    </div>

    <div class="site-blog">
        <div class="wrap">
            <div class="site-blog__box">

                {{-- ============ سایدبار ============ --}}
                <aside>
                    <div class="site-blog__sidebar__item">
                        <div class="site-blog__sidebar__item__header">
                            <fieldset><legend>Categories</legend></fieldset>
                        </div>
                        <ul class="cat-list">
                            <li><a href="{{ url('/blog') }}">Self-Defense</a><span class="badge-count">2</span></li>
                            <li><a href="{{ url('/blog') }}">Brazilian Jiu-Jitsu</a><span class="badge-count">1</span></li>
                            <li><a href="{{ url('/blog') }}">Training Tips</a><span class="badge-count">1</span></li>
                        </ul>
                    </div>
                    <div class="site-blog__sidebar__item">
                        <div class="site-blog__sidebar__item__header">
                            <fieldset><legend>Most Popular</legend></fieldset>
                        </div>
                        <div style="margin-top:10px">
                            <div class="popular-item">
                                <div class="thumb"></div>
                                <h6><a href="{{ url('/blog/why-technique-alone-wont-save-you') }}">Why Technique Alone Won't Save You</a></h6>
                            </div>
                            <div class="popular-item">
                                <div class="thumb"></div>
                                <h6><a href="{{ url('/blog') }}">BJJ for Smaller People: the Honest Truth</a></h6>
                            </div>
                            <div class="popular-item">
                                <div class="thumb"></div>
                                <h6><a href="{{ url('/blog') }}">Self-Defense for Women: Where to Start</a></h6>
                            </div>
                        </div>
                    </div>
                </aside>

                {{-- ============ لیست پست‌ها ============ --}}
                <div>
                    <div class="site-blog__sidebar__item__header">
                        <fieldset><legend>Recent Articles</legend></fieldset>
                    </div>
                    <div class="posts-grid">
                        <article class="post-item">
                            <a href="{{ url('/blog/why-technique-alone-wont-save-you') }}" class="post-item__image">
                                <div class="thumb"><b>01</b></div>
                                <span class="post-item__cat">Self-Defense</span>
                            </a>
                            <div class="post-item__desc">
                                <h4 class="post-item__desc__title">
                                    <a href="{{ url('/blog/why-technique-alone-wont-save-you') }}">Why Technique Alone Won't Save You</a>
                                </h4>
                                <div class="post-item__desc__list">
                                    <span>Ehsan Dibazar</span><span>July 2026</span>
                                </div>
                                <p class="post-item__desc__detail">
                                    What actually happens to your body and mind in the first three
                                    seconds of a real confrontation — and why decision-making, not
                                    memorized moves, is the skill that determines the outcome.
                                </p>
                            </div>
                        </article>
                        <article class="post-item">
                            <a href="{{ url('/blog') }}" class="post-item__image">
                                <div class="thumb"><b>02</b></div>
                                <span class="post-item__cat">Brazilian Jiu-Jitsu</span>
                            </a>
                            <div class="post-item__desc">
                                <h4 class="post-item__desc__title">
                                    <a href="{{ url('/blog') }}">BJJ for Smaller People: the Honest Truth</a>
                                </h4>
                                <div class="post-item__desc__list">
                                    <span>Ehsan Dibazar</span><span>July 2026</span>
                                </div>
                                <p class="post-item__desc__detail">
                                    Can a 55 kg beginner really control a 90 kg attacker? A clear
                                    look at what leverage, position and technique make possible —
                                    and what they don't.
                                </p>
                            </div>
                        </article>
                        <article class="post-item">
                            <a href="{{ url('/blog') }}" class="post-item__image">
                                <div class="thumb"><b>03</b></div>
                                <span class="post-item__cat">Self-Defense</span>
                            </a>
                            <div class="post-item__desc">
                                <h4 class="post-item__desc__title">
                                    <a href="{{ url('/blog') }}">Self-Defense for Women: Where to Start</a>
                                </h4>
                                <div class="post-item__desc__list">
                                    <span>Ehsan Dibazar</span><span>July 2026</span>
                                </div>
                                <p class="post-item__desc__detail">
                                    The most common threat scenarios women face in real life, and
                                    the first three skills worth learning before anything else.
                                </p>
                            </div>
                        </article>
                        <p class="blog-note">More articles are added regularly — check back soon.</p>
                    </div>
                </div>

            </div>
        </div>
    </div>

@endsection
