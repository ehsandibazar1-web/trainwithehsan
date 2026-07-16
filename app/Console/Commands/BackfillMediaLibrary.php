<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Media;
use App\Models\Page;
use App\Services\Media\MediaProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BackfillMediaLibrary extends Command
{
    protected $signature = 'media:backfill';

    protected $description = 'Register pre-existing article/page featured images in the Media Library (with WebP/thumbnail/responsive variants), one-off — run once after upgrading to the DAM.';

    public function handle(MediaProcessor $processor): int
    {
        $paths = Article::query()->whereNotNull('image_path')->pluck('image_path')
            ->merge(Page::query()->whereNotNull('image_path')->pluck('image_path'))
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
}
