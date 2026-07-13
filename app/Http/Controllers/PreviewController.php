<?php

namespace App\Http\Controllers;

use App\Models\Article;

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

        return view($view, compact('article', 'related', 'latest', 'translation'));
    }
}
