<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\SiteSetting;

class PreviewController extends Controller
{
    // پیش‌نمایش با لینک امضاشده — مقاله را صرف‌نظر از وضعیتش (Draft/Scheduled/Published) نشان می‌دهد
    public function show(Article $article)
    {
        $view = $article->locale === 'tr' ? 'tr.blog-post' : 'blog-post';

        $related = Article::published()
            ->locale($article->locale)
            ->where('id', '!=', $article->id)
            ->when($article->category, fn ($q) => $q->where('category', $article->category))
            ->orderByDesc('published_at')
            ->take(2)
            ->get();

        $latest = Article::published()
            ->locale($article->locale)
            ->where('id', '!=', $article->id)
            ->orderByDesc('published_at')
            ->take(3)
            ->get();

        $translation = $article->translation ?? $article->translations()->first();

        // همان عکس نویسنده‌ی BlogController::renderShow — پیش‌نمایش باید عین صفحه‌ی واقعی رندر شود
        $authorPhoto = SiteSetting::get("about.{$article->locale}.hero_image")
            ?? SiteSetting::get('about.en.hero_image');

        return view($view, compact('article', 'related', 'latest', 'translation', 'authorPhoto'));
    }
}
