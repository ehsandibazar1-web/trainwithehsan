<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SiteSetting extends Model
{
    protected $fillable = ['key', 'value', 'group'];

    // مقدار نگهبان برای «این کلید در جدول وجود ندارد» — تا rememberForever بتواند «کش‌شده ولی
    // خالی» را از «هنوز کش‌نشده» تفکیک کند (وگرنه هر get() روی یک کلید ناموجود دوباره کوئری می‌زند)
    private const CACHE_MISS_SENTINEL = "\0__site_setting_missing__\0";

    protected static function booted(): void
    {
        // با ذخیره/حذف هر ردیف، کش تک‌کلیدی و کش پیشوندی متناظرش پاک می‌شود — تغییرات پنل CMS
        // بلافاصله دیده می‌شوند، نه بعد از انقضای TTL (کش عمداً بدون TTL است، فقط event-invalidated)
        static::saved(fn (self $setting) => self::forgetCacheFor($setting->key));
        static::deleted(fn (self $setting) => self::forgetCacheFor($setting->key));
    }

    private static function forgetCacheFor(string $key): void
    {
        Cache::forget("settings.key.$key");

        if (str_contains($key, '.')) {
            Cache::forget('settings.prefix.'.substr($key, 0, strrpos($key, '.')));
        }
    }

    // خواندن مقدار (رشته ساده) — کش دائمی با invalidation خودکار در ذخیره/حذف (booted بالا)
    public static function get(string $key, $default = null)
    {
        $value = Cache::rememberForever("settings.key.$key", function () use ($key) {
            $row = self::where('key', $key)->first();

            return $row ? $row->value : self::CACHE_MISS_SENTINEL;
        });

        return $value === self::CACHE_MISS_SENTINEL ? $default : $value;
    }

    // خواندن مقدار به‌صورت آرایه (اگر JSON ذخیره شده) — از همان کش get() استفاده می‌کند
    public static function getJson(string $key, array $default = [])
    {
        $value = self::get($key);
        if (! $value) {
            return $default;
        }
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : $default;
    }

    // خواندن یک‌جای همه‌ی کلیدهای زیر یک پیشوند مشترک (مثلاً footer.tr) در یک کوئری، کش‌شده —
    // جایگزینِ where('key','like',"$prefix.%")->pluck('value','key') که قبلاً مستقیم در Blade/Controller
    // روی هر بارگذاری صفحه اجرا می‌شد
    public static function byPrefix(string $prefix)
    {
        return Cache::rememberForever(
            "settings.prefix.$prefix",
            fn () => self::where('key', 'like', $prefix.'.%')->pluck('value', 'key')
        );
    }

    // نوشتن/به‌روزرسانی مقدار
    public static function set(string $key, $value, ?string $group = null): void
    {
        self::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
    }
}
