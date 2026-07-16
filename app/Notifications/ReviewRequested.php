<?php

namespace App\Notifications;

use App\Models\ContentPlan;
use App\Models\NotificationPreference;
use App\Models\User;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

/**
 * یک ContentPlan به مرحله‌ی Human Review یا SEO Review رسیده — نگاه کنید به
 * App\Models\ContentPlan::moveToStage().
 */
class ReviewRequested extends Notification
{
    public const EVENT_KEY = 'review_requested';

    private const AVAILABLE_CHANNELS = ['database'];

    public function __construct(
        public ContentPlan $contentPlan,
        public string $stageLabel,
    ) {}

    /** @return string[] */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User
            ? NotificationPreference::filterChannels($notifiable, self::EVENT_KEY, self::AVAILABLE_CHANNELS)
            : self::AVAILABLE_CHANNELS;
    }

    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Review requested')
            ->body("\"{$this->contentPlan->title}\" is ready for {$this->stageLabel}.")
            ->icon('heroicon-o-magnifying-glass')
            ->getDatabaseMessage();
    }
}
