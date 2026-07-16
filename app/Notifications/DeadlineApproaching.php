<?php

namespace App\Notifications;

use App\Models\ContentPlan;
use App\Models\NotificationPreference;
use App\Models\User;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

/**
 * مهلت (due_at) یک ContentPlan نزدیک شده — نگاه کنید به
 * App\Console\Commands\NotifyApproachingDeadlines (هر ساعت، طبق routes/console.php).
 */
class DeadlineApproaching extends Notification
{
    public const EVENT_KEY = 'deadline_approaching';

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
            ->title('Deadline approaching')
            ->body("\"{$this->contentPlan->title}\" is due {$this->contentPlan->due_at->diffForHumans()}.")
            ->icon('heroicon-o-clock')
            ->color('warning')
            ->getDatabaseMessage();
    }
}
