<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Media;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Services\Media\MediaProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class BackfillMediaLibrary extends Command
{
    protected $signature = 'media:backfill';

    protected $description = 'Register pre-existing article/page featured images AND homepage/about/footer CMS images (including video files) in the Media Library, one-off — run once after upgrading to the DAM.';

    public function handle(MediaProcessor $processor): int
    {
        $paths = Article::query()->whereNotNull('image_path')->pluck('image_path')
            ->merge(Page::query()->whereNotNull('image_path')->pluck('image_path'))
            ->merge($this->siteSettingPaths())
            ->unique()
            ->filter();

        $created = 0;
        $skipped = 0;

        foreach ($paths as $path) {
            if (Media::where('disk_path', $path)->exists()) {
                continue;
            }

            if (! Storage::disk('public')->exists($path)) {
                $this->warn("Skipped (file missing on disk): {$path}");
                $skipped++;

                continue;
            }

            $processor->adopt($path, 'public');
            $created++;
        }

        $this->info("Backfilled {$created} media record(s), skipped {$skipped}.");

        return self::SUCCESS;
    }

    // مسیرهای فایلِ نشسته در تنظیماتِ CMS (home./about./footer.) — چه مقدارِ ساده (تصویر شاخصِ
    // هیرو، لوگو، …) چه داخلِ JSON blobها (members.photo/video_file، certificates.image،
    // gallery.image). فقط رشته‌هایی که واقعاً یک فایلِ موجود روی دیسکِ public هستند برمی‌گردند —
    // Storage::exists() دروازه است، پس متن/عنوان/URL هرگز به‌اشتباه adopt نمی‌شود.
    private function siteSettingPaths(): Collection
    {
        $found = collect();

        $values = SiteSetting::query()
            ->where('key', 'like', 'home.%')
            ->orWhere('key', 'like', 'about.%')
            ->orWhere('key', 'like', 'footer.%')
            ->pluck('value');

        foreach ($values as $value) {
            $this->extractPaths($value, $found);
        }

        return $found;
    }

    private function extractPaths(?string $value, Collection $into): void
    {
        if ($value === null || $value === '') {
            return;
        }

        // مقادیرِ ریپیتر به‌صورت JSON ذخیره می‌شوند — برگ‌های رشته‌ای را بازگشتی می‌گردیم
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            array_walk_recursive($decoded, fn ($leaf) => $this->considerPath($leaf, $into));

            return;
        }

        $this->considerPath($value, $into);
    }

    private function considerPath($value, Collection $into): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        // نه URL، نه مسیرِ مطلق — فقط مسیرِ نسبیِ دیسک
        if (str_contains($value, '://') || str_starts_with($value, '/')) {
            return;
        }

        // دروازه‌ی اصلی: فقط اگر واقعاً یک فایلِ موجود روی دیسک باشد
        if (Storage::disk('public')->exists($value)) {
            $into->push($value);
        }
    }
}
