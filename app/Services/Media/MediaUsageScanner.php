<?php

namespace App\Services\Media;

use App\Models\Article;
use App\Models\Media;
use App\Models\Page;
use App\Models\SiteSetting;

class MediaUsageScanner
{
    /**
     * کجاهای سایت از این فایل استفاده می‌کنند.
     *
     * توجه: featured image و متن مقاله‌ها/صفحه‌ها و تنظیمات سایت مسیر فایل را به‌صورت رشته‌ی خام
     * ذخیره می‌کنند، نه کلید خارجی به جدول media — بنابراین تطبیق بر اساس خودِ disk_path انجام می‌شود.
     *
     * @return array<int, array{type: string, label: string, field: string}>
     */
    public function scan(Media $media): array
    {
        $path = $media->disk_path;
        $usages = [];

        Article::query()
            ->where('image_path', $path)
            ->orWhere('body', 'like', '%'.$path.'%')
            ->get(['id', 'title', 'locale', 'image_path'])
            ->each(function (Article $article) use (&$usages, $path) {
                $usages[] = [
                    'type' => 'Article',
                    'label' => $article->title.' ('.strtoupper($article->locale).')',
                    'field' => $article->image_path === $path ? 'Featured image' : 'Article body',
                ];
            });

        Page::query()
            ->where('image_path', $path)
            ->orWhere('body', 'like', '%'.$path.'%')
            ->get(['id', 'title', 'locale', 'image_path'])
            ->each(function (Page $page) use (&$usages, $path) {
                $usages[] = [
                    'type' => 'Page',
                    'label' => $page->title.' ('.strtoupper($page->locale).')',
                    'field' => $page->image_path === $path ? 'Featured image' : 'Page body',
                ];
            });

        SiteSetting::query()
            ->where('value', 'like', '%'.$path.'%')
            ->get(['key'])
            ->each(function (SiteSetting $setting) use (&$usages) {
                $usages[] = [
                    'type' => 'Site setting',
                    'label' => $setting->key,
                    'field' => 'Layout / page content',
                ];
            });

        return $usages;
    }
}
