<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Article extends Model
{
    protected $fillable = [
        'locale', 'translation_of', 'title', 'slug', 'category', 'excerpt', 'body',
        'image_path', 'author_name', 'reading_time', 'views',
        'status', 'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    // نسخه‌ی هم‌زبان دیگه (لینک بین انگلیسی و ترکی)
    public function translation()
    {
        return $this->belongsTo(Article::class, 'translation_of');
    }

    public function translations()
    {
        return $this->hasMany(Article::class, 'translation_of');
    }

    // مسیر عمومی مقاله بر اساس زبان
    public function path(): string
    {
        $prefix = $this->locale === 'tr' ? '/tr/blog/' : '/blog/';
        return $prefix . $this->slug;
    }

    // ساخت خودکار اسلاگ از عنوان (در صورت خالی بودن)
    public static function makeSlug(string $title): string
    {
        return Str::slug($title);
    }

    public function scopePublished($query)
    {
        // منتشرشده = وضعیت published، یا زمان‌بندی‌شده‌ای که زمانش رسیده
        // (تور ایمنی: حتی اگر کرون از کار بیفتد، مقاله سر وقت نمایش داده می‌شود)
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
