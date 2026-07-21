@extends('layouts.master')

{{-- صفحه‌های ۲ به بعد عنوان/canonical مخصوصِ خودشان را می‌گیرند — عنوانِ تکراری و
     canonicalِ اشتباه (اشاره‌ی همه‌ی صفحه‌ها به صفحه‌ی ۱) دو خطای کلاسیکِ سئوی صفحه‌بندی‌اند --}}
@section('title', 'Blog — Self-Defense & Martial Arts Articles | Ehsan Dibazar'.($articles->currentPage() > 1 ? ' — Page '.$articles->currentPage() : ''))
@section('meta_description', 'Practical articles on self-defense, Brazilian Jiu-Jitsu, and martial arts training by Ehsan Dibazar — for complete beginners, women and men.')
@section('canonical', $articles->currentPage() > 1 ? $articles->url($articles->currentPage()) : url('/blog'))
@section('og_title', 'Blog — Self-Defense & Martial Arts Articles | Ehsan Dibazar')
@section('og_description', 'Practical articles on self-defense, Brazilian Jiu-Jitsu, and martial arts training by Ehsan Dibazar — for complete beginners, women and men.')

@section('json-ld')
<script type="application/ld+json">
{
  "@@context": "https://schema.org",
  "@@type": "CollectionPage",
  "@@id": "https://trainwithehsan.com/blog#webpage",
  "name": "Blog",
  "url": "https://trainwithehsan.com/blog",
  "description": "Practical articles on self-defense, Brazilian Jiu-Jitsu, and martial arts training by Ehsan Dibazar — for complete beginners, women and men.",
  "isPartOf": {"@@id": "https://trainwithehsan.com/#organization"},
  "mainEntity": {
    "@@type": "ItemList",
    "itemListElement": [
      @foreach($articles as $article)
      {
        "@@type": "ListItem",
        "position": {{ $loop->iteration }},
        "url": @json(url('/blog/' . $article->slug)),
        "name": @json($article->title)
      }@unless($loop->last),@endunless
      @endforeach
    ]
  }
}
</script>
@endsection

@section('page-css')
<style>
    .site-blog{background-color:#f6f6f6;padding:50px 0}
    .site-blog__box{box-shadow:2px 5px 14px -5px #dbdbdb;background-color:#fff;padding:26px;display:grid;grid-template-columns:1fr 2fr;gap:30px}
    @@media (max-width:860px){.site-blog__box{grid-template-columns:1fr}}

    .site-blog__sidebar__item{margin-bottom:24px}
    .site-blog__sidebar__item__header fieldset{border:0;border-bottom:4px solid var(--gold);padding:0 0 8px}
    .site-blog__sidebar__item__header legend{font-size:17px;font-weight:600;letter-spacing:.01em;color:#000;padding:0}
    .cat-list{list-style:none;margin-top:10px}
    .cat-list li{display:flex;justify-content:space-between;border-bottom:1px dashed #d8d8d8;padding:10px 0}
    .cat-list li a{color:#888;font-size:14px}
    .cat-list li a:hover{color:var(--gold-dark,#c09d4c)}
    .cat-list li .badge-count{background:#1d1d1d;color:#fff;font-size:11px;border-radius:10px;padding:2px 8px}
    .popular-item{display:flex;gap:12px;border-bottom:1px dashed #d8d8d8;padding:10px 0}
    .popular-item .thumb{
        width:70px;height:60px;flex-shrink:0;border-radius:4px;overflow:hidden;
        background:linear-gradient(135deg,#d8d3c4,#d9bb75);
        background-size:cover;background-position:center;
    }
    .popular-item h6{font-size:14px;font-weight:800;color:#696969}
    .popular-item h6 a:hover{color:var(--gold-dark,#c09d4c)}

    .posts-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:14px}
    @@media (max-width:600px){.posts-grid{grid-template-columns:1fr}}
    .post-item{overflow:hidden}
    .post-item__image{position:relative;overflow:hidden;display:block}
    .post-item__image .thumb{
        height:200px;display:block;
        background:linear-gradient(135deg,#d8d3c4 0%,#cdb87f 80%,var(--gold) 160%);
        background-size:cover;background-position:center;
        transition:transform .4s ease-in-out;
        display:flex;align-items:center;justify-content:center;
    }
    .post-item:hover .thumb{transform:scale(1.08)}
    .thumb b{font-weight:800;font-size:22px;color:rgba(0,0,0,.2)}
    .post-item__cat{
        position:absolute;top:0;right:0;background-color:var(--gold);
        color:#1d1d1d;font-size:13px;padding:4px 10px;font-weight:600;
    }
    .post-item__desc{padding:12px 4px}
    .post-item__desc__title{font-size:16px;font-weight:600;color:#3a3a3a;margin-bottom:6px}
    .post-item__desc__title a:hover{color:var(--gold-dark,#c09d4c)}
    .post-item__desc__list{font-size:11px;color:#666;margin-bottom:8px}
    .post-item__desc__list span:not(:last-child)::after{content:"·";margin:0 5px}
    .post-item__desc__detail{font-size:13.5px;color:#666;text-align:justify}
    .blog-note{grid-column:1/-1;color:#888;font-size:13px;text-align:center;padding:20px 0 4px}
    .blog-pagination{display:flex;justify-content:center;gap:8px;margin-top:28px;flex-wrap:wrap}
    .pg-btn{
        display:flex;align-items:center;justify-content:center;min-width:40px;height:40px;
        padding:0 12px;border:1px solid #ddd;border-radius:4px;background:#fff;
        color:#555;font-size:14px;font-weight:600;transition:all .2s;
    }
    a.pg-btn:hover{border-color:var(--gold);color:#1d1d1d;background:var(--gold)}
    .pg-current{background:#1d1d1d;border-color:#1d1d1d;color:var(--gold)}
    .pg-disabled{opacity:.4;cursor:default}
</style>
@endsection

@section('content')

    <div class="page-title-bar" style="background:#1d1d1d;padding:26px 0;color:#fff">
        <div class="wrap">
            <h1 style="color:#fff;font-size:26px;font-weight:800">Blog</h1>
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
                    @if($categories->isNotEmpty())
                    <div class="site-blog__sidebar__item reveal">
                        <div class="site-blog__sidebar__item__header">
                            <fieldset><legend>Categories</legend></fieldset>
                        </div>
                        <ul class="cat-list">
                            @foreach($categories as $category => $count)
                            <li>
                                <a href="{{ url('/blog') }}">{{ $category }}</a>
                                <span class="badge-count">{{ $count }}</span>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                    @endif

                    @if($popular->isNotEmpty())
                    <div class="site-blog__sidebar__item reveal">
                        <div class="site-blog__sidebar__item__header">
                            <fieldset><legend>Most Popular</legend></fieldset>
                        </div>
                        <div style="margin-top:10px">
                            @foreach($popular as $pop)
                            <div class="popular-item">
                                <div class="thumb" @if($pop->image_path) style="background-image:url('{{ \App\Models\Media::optimizedUrl($pop->image_path, 480) }}')" @endif></div>
                                <h6><a href="{{ url('/blog/' . $pop->slug) }}">{{ $pop->title }}</a></h6>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </aside>

                {{-- ============ لیست پست‌ها ============ --}}
                <div>
                    <div class="site-blog__sidebar__item__header">
                        <fieldset><legend>Recent Articles</legend></fieldset>
                    </div>
                    <div class="posts-grid reveal-group">
                        @forelse($articles as $article)
                        <article class="post-item reveal">
                            <a href="{{ url('/blog/' . $article->slug) }}" class="post-item__image">
                                <div class="thumb" @if($article->image_path) style="background-image:url('{{ \App\Models\Media::optimizedUrl($article->image_path, 800) }}')" @endif>
                                    @unless($article->image_path)<b>{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</b>@endunless
                                </div>
                                @if($article->category)
                                <span class="post-item__cat">{{ $article->category }}</span>
                                @endif
                            </a>
                            <div class="post-item__desc">
                                <h4 class="post-item__desc__title">
                                    <a href="{{ url('/blog/' . $article->slug) }}">{{ $article->title }}</a>
                                </h4>
                                <div class="post-item__desc__list">
                                    <span>{{ $article->author_name }}</span><span>{{ optional($article->published_at)->format('F Y') }}</span>
                                </div>
                                <p class="post-item__desc__detail">{{ $article->excerpt }}</p>
                            </div>
                        </article>
                        @empty
                        <p class="blog-note">No articles published yet — check back soon.</p>
                        @endforelse
                    </div>

                    {{-- صفحه‌بندی — دست‌ساز مطابقِ CSSِ خودِ سایت (ویوی پیش‌فرضِ Laravel به Tailwindِ
                         کامپایل‌شده وابسته است که قالب‌های عمومی ندارند)؛ دکمه‌ها ۴۰px برای تاچ‌تارگت --}}
                    @if($articles->hasPages())
                    <nav class="blog-pagination" aria-label="Blog pages">
                        @if($articles->onFirstPage())
                            <span class="pg-btn pg-disabled" aria-hidden="true">&lsaquo;</span>
                        @else
                            <a class="pg-btn" href="{{ $articles->previousPageUrl() }}" rel="prev" aria-label="Previous page">&lsaquo;</a>
                        @endif

                        @foreach($articles->getUrlRange(1, $articles->lastPage()) as $page => $url)
                            @if($page === $articles->currentPage())
                                <span class="pg-btn pg-current" aria-current="page">{{ $page }}</span>
                            @else
                                <a class="pg-btn" href="{{ $url }}" aria-label="Page {{ $page }}">{{ $page }}</a>
                            @endif
                        @endforeach

                        @if($articles->hasMorePages())
                            <a class="pg-btn" href="{{ $articles->nextPageUrl() }}" rel="next" aria-label="Next page">&rsaquo;</a>
                        @else
                            <span class="pg-btn pg-disabled" aria-hidden="true">&rsaquo;</span>
                        @endif
                    </nav>
                    @endif
                </div>

            </div>
        </div>
    </div>

@endsection
