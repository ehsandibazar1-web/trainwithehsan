<?php

namespace App\Notifications;

use App\Models\ContentPlan;
use App\Models\NotificationPreference;
use App\Models\User;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

/**
 * یک ContentPlan از یک مرحله به مرحله‌ی دیگر منتقل شده — نگاه کنید به
 * App\Models\ContentPlan::moveToStage(). ->via() کانال‌محور نیست، فقط لیست کانال‌های فعال این
 * کاربر برای این رویداد را برمی‌گرداند — افزودن mail/slack بعداً فقط یعنی این کلاس یک
 * toMail()/toSlack() تازه بگیرد و AVAILABLE_CHANNELS را گسترش دهد، هیچ‌جای دیگری تغییر نمی‌کند.
 */
class WorkflowStageChanged extends Notification
{
    public const EVENT_KEY = 'workflow_stage_changed';

    private const AVAILABLE_CHANNELS = ['database'];

    public function __construct(
        public ContentPlan $contentPlan,
        public string $fromStageLabel,
        public string $toStageLabel,
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
            ->title('Workflow stage changed')
            ->body("\"{$this->contentPlan->title}\" moved from {$this->fromStageLabel} to {$this->toStageLabel}.")
            ->icon('heroicon-o-arrow-path')
            ->getDatabaseMessage();
    }
}
