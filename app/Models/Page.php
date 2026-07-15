<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Page extends Model
{
    protected $fillable = [
        'locale', 'translation_of', 'title', 'slug', 'body',
        'image_path', 'status', 'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
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
