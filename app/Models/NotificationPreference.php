<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ترجیح یک کاربر برای یک رویداد+کانال مشخص (مثلاً «workflow_stage_changed» روی «database»).
 * نبودِ ردیف یعنی فعال (opt-out) — نه اینکه کاربر باید صریحاً هر کانالی را روشن کند.
 * App\Notifications\* از filterChannels() برای ساختن via() خودشان استفاده می‌کنند — همین چیزی
 * است که سیستم را «کانال‌آگنوستیک» می‌کند: افزودن کانال mail بعداً فقط یعنی آن را به
 * $availableChannels کلاس Notification اضافه کنی، نه تغییر این منطق.
 */
class NotificationPreference extends Model
{
    protected $fillable = ['user_id', 'event_key', 'channel', 'enabled'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function isEnabled(User $user, string $eventKey, string $channel): bool
    {
        $preference = static::query()
            ->where('user_id', $user->id)
            ->where('event_key', $eventKey)
            ->where('channel', $channel)
            ->first();

        return $preference?->enabled ?? true;
    }

    /**
     * @param  string[]  $availableChannels
     * @return string[]
     */
    public static function filterChannels(User $user, string $eventKey, array $availableChannels): array
    {
        return array_values(array_filter(
            $availableChannels,
            fn (string $channel): bool => static::isEnabled($user, $eventKey, $channel)
        ));
    }
}
