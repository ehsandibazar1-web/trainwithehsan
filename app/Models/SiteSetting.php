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

    // نوشتن/به‌روزرسانی مقدار
    public static function set(string $key, $value, ?string $group = null): void
    {
        self::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
    }
}
