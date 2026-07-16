<?php

namespace App\Filament\Resources\NewsletterSubscribers\Tables;

use App\Mail\NewsletterCampaignMail;
use App\Mail\NewsletterVerificationMail;
use App\Models\NewsletterSubscriber;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class NewsletterSubscribersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('locale')
                    ->label('Lang')
                    ->badge(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'subscribed' ? 'success' : 'gray'),

                IconColumn::make('verified')
                    ->label('Verified')
                    ->boolean()
                    ->state(fn (NewsletterSubscriber $record): bool => $record->isVerified()),

                TextColumn::make('source')
                    ->label('Source')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Subscribed at')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),

                TextColumn::make('verified_at')
                    ->label('Verified at')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('unsubscribed_at')
                    ->label('Unsubscribed at')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('locale')
                    ->label('Language')
                    ->options([
                        'en' => 'English',
                        'tr' => 'Türkçe',
                    ]),

                TernaryFilter::make('verified')
                    ->label('Email verified')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('verified_at'),
                        false: fn ($query) => $query->whereNull('verified_at'),
                    ),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'subscribed' => 'Active',
                        'unsubscribed' => 'Unsubscribed',
                    ]),
            ])
            ->recordActions([
                self::resendVerificationAction(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::exportCsvBulkAction(),
                    self::resendVerificationBulkAction(),
                    self::sendNewsletterBulkAction(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    // ============ ارسال دوبارهٔ ایمیل تأیید (تکی) ============
    private static function resendVerificationAction(): Action
    {
        return Action::make('resendVerification')
            ->label('Resend verification')
            ->icon('heroicon-o-envelope')
            ->color('gray')
            ->visible(fn (NewsletterSubscriber $record): bool => ! $record->isVerified())
            ->requiresConfirmation()
            ->action(function (NewsletterSubscriber $record): void {
                $record->update(['verification_sent_at' => now()]);
                Mail::to($record->email)->send(new NewsletterVerificationMail($record));

                Notification::make()
                    ->success()
                    ->title('Verification email sent to '.$record->email)
                    ->send();
            });
    }

    // ============ خروجی CSV از رکوردهای انتخاب‌شده ============
    private static function exportCsvBulkAction(): BulkAction
    {
        return BulkAction::make('exportCsv')
            ->label('Export CSV')
            ->icon('heroicon-o-arrow-down-tray')
            ->action(function (Collection $records) {
                $filename = 'newsletter-subscribers-'.now()->format('Y-m-d-His').'.csv';

                return response()->streamDownload(function () use ($records): void {
                    $out = fopen('php://output', 'w');
                    fputcsv($out, ['Email', 'Status', 'Verified', 'Language', 'Source', 'IP address', 'Subscribed at', 'Verified at', 'Unsubscribed at']);
                    foreach ($records as $r) {
                        fputcsv($out, [
                            $r->email,
                            $r->status,
                            $r->isVerified() ? 'yes' : 'no',
                            $r->locale,
                            $r->source,
                            $r->ip_address,
                            optional($r->created_at)->format('Y-m-d H:i'),
                            optional($r->verified_at)->format('Y-m-d H:i'),
                            optional($r->unsubscribed_at)->format('Y-m-d H:i'),
                        ]);
                    }
                    fclose($out);
                }, $filename, ['Content-Type' => 'text/csv']);
            })
            ->deselectRecordsAfterCompletion();
    }

    // ============ ارسال دوبارهٔ ایمیل تأیید (گروهی — فقط تأییدنشده‌ها) ============
    private static function resendVerificationBulkAction(): BulkAction
    {
        return BulkAction::make('bulkResendVerification')
            ->label('Resend verification (unverified only)')
            ->icon('heroicon-o-envelope')
            ->requiresConfirmation()
            ->action(function (Collection $records): void {
                $count = 0;
                foreach ($records as $record) {
                    if ($record->isVerified() || $record->status !== 'subscribed') {
                        continue;
                    }
                    $record->update(['verification_sent_at' => now()]);
                    Mail::to($record->email)->send(new NewsletterVerificationMail($record));
                    $count++;
                }

                Notification::make()
                    ->success()
                    ->title("Verification email sent to {$count} subscriber(s)")
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    // ============ ارسال خبرنامه (گروهی) — صف‌شونده؛ پایهٔ کمپین‌های آینده ============
    private static function sendNewsletterBulkAction(): BulkAction
    {
        return BulkAction::make('sendNewsletter')
            ->label('Send newsletter')
            ->icon('heroicon-o-paper-airplane')
            ->schema([
                TextInput::make('subject')
                    ->label('Email subject')
                    ->required(),
                RichEditor::make('body')
                    ->label('Email content')
                    ->required()
                    ->helperText('The unsubscribe link is added automatically at the bottom of every email.'),
            ])
            ->requiresConfirmation()
            ->modalDescription('The newsletter will be queued and sent only to selected subscribers who are active and have confirmed their email.')
            ->action(function (Collection $records, array $data): void {
                $count = 0;
                foreach ($records as $record) {
                    // فقط فعال و تأییدشده — لغو اشتراکی‌ها و تأییدنشده‌ها هرگز خبرنامه نمی‌گیرند
                    if (! $record->isVerified() || $record->status !== 'subscribed') {
                        continue;
                    }
                    Mail::to($record->email)->queue(new NewsletterCampaignMail($record, $data['subject'], $data['body']));
                    $count++;
                }

                Notification::make()
                    ->success()
                    ->title("Newsletter queued for {$count} subscriber(s)")
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }
}
