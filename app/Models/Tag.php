<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;

/**
 * برچسب‌گذاری محتوا برای سازمان‌دهی/فیلتر (Content Planner و غیره) — جدا از App\Models\Keyword
 * که فقط برای تحلیل/بهینه‌سازی سئو است، نه دسته‌بندی محتوا. many-to-many چندریختی (pivot
 * `taggables`) تا بدون تغییر دیتابیس بشود آن را به هر مدل تازه‌ای (ContentPlan و غیره) هم وصل کرد.
 */
class Tag extends Model
{
    protected $fillable = ['name', 'slug', 'color'];

    protected static function booted(): void
    {
        static::creating(function (Tag $tag) {
            if (blank($tag->slug)) {
                $tag->slug = Str::slug($tag->name);
            }
        });
    }

    public function articles(): MorphToMany
    {
        return $this->morphedByMany(Article::class, 'taggable');
    }

    public function pages(): MorphToMany
    {
        return $this->morphedByMany(Page::class, 'taggable');
    }

    public function knowledgeEntries(): MorphToMany
    {
        return $this->morphedByMany(KnowledgeEntry::class, 'taggable');
    }
}
