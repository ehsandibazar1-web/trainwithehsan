<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\SiteSetting;

class BlogController extends Controller
{
    // صفحه اصلی — انگلیسی (با آخرین مقالات + تنظیمات)
    public function home()
    {
        $latestArticles = Article::published()
            ->locale('en')
            ->orderByDesc('published_at')
            ->take(3)
            ->get();

        [$s, $members] = $this->homeSettings('en');

        return view('home', compact('latestArticles', 's', 'members'));
    }

    // صفحه اصلی — ترکی
    public function homeTr()
    {
        $latestArticles = Article::published()
            ->locale('tr')
            ->orderByDesc('published_at')
            ->take(3)
            ->get();

        [$s, $members] = $this->homeSettings('tr');

        return view('tr.home', compact('latestArticles', 's', 'members'));
    }

    // خواندن یک‌جای تنظیمات صفحه اصلی از دیتابیس (یک کوئری)
    private function homeSettings(string $locale): array
    {
        $prefix = "home.$locale.";

        $raw = SiteSetting::where('key', 'like', $prefix . '%')
            ->pluck('value', 'key');

        $s = [];
        foreach ($raw as $key => $value) {
            $s[substr($key, strlen($prefix))] = $value;
        }

        $members = json_decode($s['members'] ?? '[]', true) ?: [];
        unset($s['members']);

        return [$s, $members];
    }

    // لیست مقالات — انگلیسی
    public function index()
    {
        return $this->renderIndex('en', 'blog');
    }

    // لیست مقالات — ترکی
    public function indexTr()
    {
        return $this->renderIndex('tr', 'tr.blog');
    }

    // تک‌مقاله — انگلیسی
    public function show(string $slug)
    {
        return $this->renderShow('en', $slug, 'blog-post');
    }

    // تک‌مقاله — ترکی
    public function showTr(string $slug)
    {
        return $this->renderShow('tr', $slug, 'tr.blog-post');
    }

    private function renderIndex(string $locale, string $view)
    {
        $articles = Article::published()
            ->locale($locale)
            ->orderByDesc('published_at')
            ->get();

        $popular = Article::published()
            ->locale($locale)
            ->orderByDesc('views')
            ->take(3)
            ->get();

        // شمارش مقالات هر دسته برای سایدبار
        $categories = Article::published()
            ->locale($locale)
            ->whereNotNull('category')
            ->selectRaw('category, count(*) as total')
            ->groupBy('category')
            ->pluck('total', 'category');

        return view($view, compact('articles', 'popular', 'categories'));
    }

    private function renderShow(string $locale, string $slug, string $view)
    {
        $article = Article::published()
            ->locale($locale)
            ->where('slug', $slug)
            ->firstOrFail();

        // افزایش شمارنده بازدید
        $article->increment('views');

        // مقالات مرتبط: هم‌دسته، غیر از خودش
        $related = Article::published()
            ->locale($locale)
            ->where('id', '!=', $article->id)
            ->when($article->category, fn ($q) => $q->where('category', $article->category))
            ->orderByDesc('published_at')
            ->take(2)
            ->get();

        // آخرین مقالات برای سایدبار
        $latest = Article::published()
            ->locale($locale)
            ->where('id', '!=', $article->id)
            ->orderByDesc('published_at')
            ->take(3)
            ->get();

        // نسخه‌ی زبان دیگر (اگر لینک شده باشد) — دوطرفه چک می‌کنیم
        $translation = $article->translation
            ?? $article->translations()->first();

        return view($view, compact('article', 'related', 'latest', 'translation'));
    }
}
