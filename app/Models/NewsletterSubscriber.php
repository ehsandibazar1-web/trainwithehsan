<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class NewsletterSubscriber extends Model
{
    protected $fillable = [
        'email', 'status', 'verification_token', 'verification_sent_at',
        'unsubscribe_token', 'locale', 'source', 'ip_address', 'user_agent',
        'verified_at', 'unsubscribed_at',
    ];

    protected $casts = [
        'verification_sent_at' => 'datetime',
        'verified_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];

    // توکن‌ها هنگام ساخت خودکار تولید می‌شوند تا هیچ رکوردی بدون توکن نماند
    protected static function booted(): void
    {
        static::creating(function (self $subscriber) {
            $subscriber->verification_token = $subscriber->verification_token ?: Str::random(64);
            $subscriber->unsubscribe_token = $subscriber->unsubscribe_token ?: Str::random(64);
        });
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    // لینک تأیید فقط ۲۴ ساعت بعد از آخرین ارسال معتبر است
    public function verificationExpired(): bool
    {
        return $this->verification_sent_at === null
            || $this->verification_sent_at->lt(now()->subHours(24));
    }

    public function markVerified(): void
    {
        $this->update([
            'status' => 'subscribed',
            'verified_at' => now(),
            'unsubscribed_at' => null,
        ]);
    }

    public function markUnsubscribed(): void
    {
        $this->update([
            'status' => 'unsubscribed',
            'unsubscribed_at' => now(),
        ]);
    }

    // فعال = مشترک و تأییدشده — فقط این‌ها خبرنامه می‌گیرند
    public function scopeActive($query)
    {
        return $query->where('status', 'subscribed')->whereNotNull('verified_at');
    }

    public function scopeLocale($query, string $locale)
    {
        return $query->where('locale', $locale);
    }
}
