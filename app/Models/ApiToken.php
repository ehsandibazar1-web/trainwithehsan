<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApiToken extends Model
{
    protected $fillable = ['name', 'token_hash', 'prefix', 'last_used_at', 'expires_at'];

    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public static function generatePlainToken(): string
    {
        return 'aiimp_'.Str::random(48);
    }

    // فقط هش نگه داشته می‌شود — پیدا کردن توکن از روی متنِ ارسال‌شده در هدر. expires_at نال
    // یعنی «هرگز منقضی نمی‌شود» (رفتار پیش‌فرض قبلی، برای توکن‌های موجود دست‌نخورده می‌ماند)
    public static function findByPlainToken(?string $plain): ?self
    {
        if (! $plain) {
            return null;
        }

        return static::where('token_hash', hash('sha256', $plain))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->first();
    }
}
