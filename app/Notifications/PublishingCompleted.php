<?php

namespace App\Notifications;

use App\Models\ContentPlan;
use App\Models\NotificationPreference;
use App\Models\User;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

/**
 * یک ContentPlan به مرحله‌ی Published رسیده — یا با کلیک ادمین در Kanban، یا خودکار توسط
 * App\Console\Commands\PublishDueArticles وقتی زمان انتشارِ Article متناظرش برسد.
 */
class PublishingCompleted extends Notification
{
    public const EVENT_KEY = 'publishing_completed';

    private const AVAILABLE_CHANNELS = ['database'];

    public function __construct(public ContentPlan $contentPlan) {}

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
            ->title('Publishing completed')
            ->body("\"{$this->contentPlan->title}\" has been published.")
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->getDatabaseMessage();
    }
}
