<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\InteractsWithMediaLibrary;
use App\Models\Media;
use BackedEnum;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Features\SupportFileUploads\WithFileUploads;

class MediaLibrary extends Page implements HasForms
{
    use InteractsWithForms;

    // کلِ منطقِ کتابخانه‌ی رسانه (پوشه‌ها/آپلود/فیلتر/جزئیات/حذف/بازتولید) در این trait است تا
    // MediaPicker (پنجره‌ی انتخابِ یکپارچه) عیناً همان را به‌اشتراک بگذارد — نگاه کنید به
    // App\Filament\Concerns\InteractsWithMediaLibrary
    use InteractsWithMediaLibrary;
    use WithFileUploads;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $navigationLabel = 'Media Library';

    protected static ?string $title = 'Media Library';

    protected string $view = 'filament.pages.media-library';

    // لینک مستقیم از جاهای دیگر پنل (مثلا SEO Center) با ?media=ID — پوشه‌ی درست باز و آیتم انتخاب می‌شود
    public function mount(): void
    {
        $mediaId = request()->integer('media');
        if (! $mediaId) {
            return;
        }

        $media = Media::find($mediaId);
        if (! $media) {
            return;
        }

        $this->currentFolderId = $media->folder_id;
        $this->selectedMediaId = $media->id;
    }
}
