@extends('layouts.master')

@section('title', $title . ' — Ehsan Dibazar')
@section('meta_description', $message)
@section('canonical', url('/'))

@section('page-css')
<style>
    .nl-result{background:#f6f6f6;padding:70px 15px}
    .nl-result__box{max-width:520px;margin:0 auto;background:#fff;box-shadow:2px 5px 14px -5px #dbdbdb;padding:36px;text-align:center}
    .nl-result__box h1{font-size:22px;font-weight:700;color:#222;margin-bottom:14px}
    .nl-result__box p{font-size:14px;color:#555;line-height:2;margin-bottom:22px}
    .nl-result__box a.btn{display:inline-block;background:var(--gold);color:#111;font-weight:600;font-size:14px;padding:10px 26px}
</style>
@endsection

@section('content')
<div class="nl-result">
    <div class="nl-result__box">
        <h1>{{ $title }}</h1>
        <p>{{ $message }}</p>
        <a class="btn" href="{{ url('/') }}">{{ __('newsletter.back_home') }}</a>
    </div>
</div>
@endsection
