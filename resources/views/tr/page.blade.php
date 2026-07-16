@extends('layouts.master-tr')

@section('title', $page->title . ' — Ehsan Dibazar')
@section('meta_description', Str::limit(trim(strip_tags($page->body)), 150))
@section('canonical', url('/tr/' . $page->slug))
@section('og_title', $page->title . ' — Ehsan Dibazar')
@section('og_description', Str::limit(trim(strip_tags($page->body)), 150))
@section('og_image', $page->image_path ? asset('storage/' . $page->image_path) : '')

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
            <div class="page-hero-image" style="background-image:url('{{ asset('storage/' . $page->image_path) }}')"></div>
            @endif

            <div class="page-title"><h1>{{ $page->title }}</h1></div>

            @if($translation)
            <div class="page-meta">
                <span class="lang-switch">
                    <a href="{{ url($translation->path()) }}">
                        {{ $translation->locale === 'tr' ? 'Türkçe →' : 'English →' }}
                    </a>
                </span>
            </div>
            @endif

            <div class="page-body reveal">
                {!! $page->body !!}
            </div>
        </div>
    </div>
</div>
@endsection
