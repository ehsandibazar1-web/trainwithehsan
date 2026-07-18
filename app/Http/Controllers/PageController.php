<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Services\Seo\VideoSchemaService;

class PageController extends Controller
{
    // صفحه مستقل — انگلیسی
    public function show(string $slug)
    {
        return $this->renderShow('en', $slug, 'page');
    }

    // صفحه مستقل — ترکی
    public function showTr(string $slug)
    {
        return $this->renderShow('tr', $slug, 'tr.page');
    }

    private function renderShow(string $locale, string $slug, string $view)
    {
        $page = Page::published()
            ->locale($locale)
            ->where('slug', $slug)
            ->firstOrFail();

        // نسخه‌ی زبان دیگر (اگر لینک شده باشد) — دوطرفه چک می‌کنیم
        $translation = $page->translation
            ?? $page->translations()->first();

        // Video SEO — VideoObject برای ویدیوهای درون‌متنیِ صفحه
        $videoSchemas = app(VideoSchemaService::class)->forPage($page);

        return view($view, compact('page', 'translation', 'videoSchemas'));
    }
}
