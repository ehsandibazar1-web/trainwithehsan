<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Article extends Model
{
    use LogsActivity;

    protected $fillable = [
        'locale', 'translation_of', 'title', 'slug', 'category', 'excerpt', 'body',
        'faqs', 'image_path', 'author_name', 'reading_time', 'views',
        'status', 'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'faqs' => 'array',
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

    // کلیدواژه‌های هدفِ سئو — پایه‌ی امتیازدهی پیشنهادهای لینک داخلی در Internal Linking Center
    public function keywords(): MorphMany
    {
        return $this->morphMany(Keyword::class, 'keywordable');
    }

    // مسیر عمومی مقاله بر اساس زبان
    public function path(): string
    {
        $prefix = $this->locale === 'tr' ? '/tr/blog/' : '/blog/';

        return $prefix.$this->slug;
    }

    // ساخت خودکار اسلاگ از عنوان (در صورت خالی بودن)
    public static function makeSlug(string $title): string
    {
        return Str::slug($title);
    }

    // تنظیمات لاگ فعالیت — فقط تغییرات واقعی (dirty) و فیلدهای مهم ثبت می‌شود
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'locale', 'status', 'published_at', 'category'])
            ->logOnlyDirty()
            ->useLogName('article')
            ->setDescriptionForEvent(function (string $eventName) {
                if ($eventName === 'updated' && $this->wasChanged('status') && $this->status === 'published') {
                    return 'Article published';
                }

                return match ($eventName) {
                    'created' => 'Article created',
                    'updated' => 'Article updated',
                    'deleted' => 'Article deleted',
                    default => $eventName,
                };
            });
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
