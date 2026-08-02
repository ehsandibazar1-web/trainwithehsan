<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    protected $fillable = ['key', 'value', 'group'];

    // خواندن مقدار (رشته ساده)
    public static function get(string $key, $default = null)
    {
        $row = self::where('key', $key)->first();

        return $row ? $row->value : $default;
    }

    // خواندن مقدار به‌صورت آرایه (اگر JSON ذخیره شده)
    public static function getJson(string $key, array $default = [])
    {
        $row = self::where('key', $key)->first();
        if (! $row || ! $row->value) {
            return $default;
        }
        $decoded = json_decode($row->value, true);

        return is_array($decoded) ? $decoded : $default;
    }

    // خواندن یک‌جای همه‌ی کلیدهای زیر یک پیشوند مشترک (مثلاً footer.tr) در یک کوئری —
    // نتیجه یک Collection کلید/مقدار است که در Blade/Controller به‌صورت آرایه استفاده می‌شود.
    // عمداً کش نمی‌شود: کش‌کردنِ آبجکتِ Collection روی درایورِ database (serialize) با فعال‌بودنِ
    // OPcache/config-cache باعث خطای __PHP_Incomplete_Class در unserialize می‌شد.
    public static function byPrefix(string $prefix)
    {
        return self::where('key', 'like', $prefix.'.%')->pluck('value', 'key');
    }

    // نوشتن/به‌روزرسانی مقدار
    public static function set(string $key, $value, ?string $group = null): void
    {
        self::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
    }

    // لیستِ URLهای شبکه‌ی اجتماعی — منبعِ واحد برای sameAsِ Organization/Person JSON-LD.
    // از Footer Settings → Social media links می‌خواند؛ تا وقتی خالی است همان سه‌لینکِ فعلی
    // (پیش از این‌که Organization/Person JSON-LD به این منبعِ ادمین-ویرایش‌پذیر وصل شود) را
    // به‌عنوانِ fallback برمی‌گرداند — همان قراردادِ «fallback در Blade تا وقتی ادمین ویرایش کند».
    public static function socialLinks(): array
    {
        $links = collect(self::getJson('footer.en.socials'))->pluck('url')->filter()->values();

        if ($links->isEmpty()) {
            $links = collect([
                'https://www.instagram.com/ehsandibazarcoaching/',
                'https://telegram.me/ehsandibazar',
                'https://youtube.com/channel/UCDT9EOHriR9sHvq0PBdmlog',
            ]);
        }

        return $links->all();
    }
}
