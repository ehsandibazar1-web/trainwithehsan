<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;

class Page extends Model
{
    protected $fillable = [
        'locale', 'translation_of', 'title', 'slug', 'body', 'faqs',
        'seo_title', 'meta_description', 'meta_keywords', 'canonical_url', 'robots',
        'og_title', 'og_description',
        'image_path', 'image_alt', 'status', 'published_at',
        'hero_image_prompt', 'thumbnail_image_prompt', 'og_image_prompt', 'social_image_prompt',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'faqs' => 'array',
    ];

    // نسخه‌ی هم‌زبان دیگه (لینک بین انگلیسی و ترکی)
    public function translation()
    {
        return $this->belongsTo(Page::class, 'translation_of');
    }

    public function translations()
    {
        return $this->hasMany(Page::class, 'translation_of');
    }

    // کلیدواژه‌های هدفِ سئو — پایه‌ی امتیازدهی پیشنهادهای لینک داخلی در Internal Linking Center
    public function keywords(): MorphMany
    {
        return $this->morphMany(Keyword::class, 'keywordable');
    }

    // برچسب‌های سازمان‌دهی محتوا (Content Planner) — جدا از keywords() که فقط برای سئو است
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    // کارت برنامه‌ریزِ متناظر (اگر این صفحه از یک Idea مادیت پیدا کرده باشد)
    public function contentPlan(): MorphOne
    {
        return $this->morphOne(ContentPlan::class, 'contentable');
    }

    // نسخه‌ی WebP تصویر شاخص (اگر کتابخانه‌ی رسانه یکی برایش ساخته باشد) — قالب‌های عمومی این را
    // با fallback به image_path خام نمایش می‌دهند؛ عکس‌های ثبت‌نشده در DAM یا بدون مشتق WebP
    // (مثلا SVG) همچنان همان مسیر اصلی را برمی‌گردانند (null اینجا)
    public function getOptimizedImageUrlAttribute(): ?string
    {
        return Media::forRecord($this)?->webp_url;
    }

    // مسیر عمومی صفحه بر اساس زبان — صفحات مستقل در ریشه‌ی سایت هستند، نه زیر /blog
    public function path(): string
    {
        $prefix = $this->locale === 'tr' ? '/tr/' : '/';

        return $prefix.$this->slug;
    }

    public static function makeSlug(string $title): string
    {
        return Str::slug($title);
    }

    public function scopePublished($query)
    {
        // منتشرشده = وضعیت published، یا زمان‌بندی‌شده‌ای که زمانش رسیده
        // (همان تور ایمنی مقالات: حتی اگر کرون از کار بیفتد، صفحه سر وقت نمایش داده می‌شود)
        return $query->where(function ($q) {
            $q->where('status', 'published')
                ->orWhere(function ($q2) {
                    $q2->where('status', 'scheduled')
                        ->where('published_at', '<=', now());
                });
        });
    }

    public function scopeLocale($query, string $locale)
    {
        return $query->where('locale', $locale);
    }
}
