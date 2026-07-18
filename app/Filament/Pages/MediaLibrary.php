<?php

namespace App\Filament\Pages;

use App\Models\Media;
use App\Models\MediaFolder;
use App\Services\Media\MediaProcessor;
use BackedEnum;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use Throwable;

class MediaLibrary extends Page implements HasForms
{
    use InteractsWithForms;
    use WithFileUploads;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $navigationLabel = 'Media Library';

    protected static ?string $title = 'Media Library';

    protected string $view = 'filament.pages.media-library';

    // پوشه‌ای که هم‌اکنون در حال مرور آن هستیم — null یعنی ریشه
    public ?int $currentFolderId = null;

    public string $search = '';

    public string $typeFilter = 'all'; // all | image | video | other

    public bool $onlyUnused = false;

    public bool $onlyOrphaned = false;

    public bool $onlyMissingAlt = false;

    public bool $onlyLarge = false;

    public ?int $selectedMediaId = null;

    /** @var TemporaryUploadedFile[] */
    public array $uploads = [];

    public ?TemporaryUploadedFile $replaceFile = null;

    public bool $showNewFolderForm = false;

    public string $newFolderName = '';

    public ?int $renamingFolderId = null;

    public string $renamingFolderName = '';

    // بیشترین تعداد آیتمی که یک‌جا نشان داده می‌شود — در این مقیاس محتوا کافی است، نیازی به صفحه‌بندی واقعی نیست
    private const MAX_ITEMS = 300;

    // اعتبارسنجیِ اولیه‌ی سطح فرم (بر اساس محتوای واقعی فایل، نه پسوند/Content-Type ادعاشده
    // توسط کلاینت — قانون mimes: خودِ لاراول همین‌طور کار می‌کند) — لاراول به‌طور جداگانه هر
    // آپلودی که پسوندِ کلاینتش php/php3/php4/php5/php7/php8/phtml/phar باشد را هم رد می‌کند.
    // MediaProcessor::store() علاوه بر این، همین فهرست را دوباره (بر اساس MIME واقعی) اعمال
    // می‌کند تا حتی اگر این اعتبارسنجی به هر دلیلی دور زده شود، پسوندِ ذخیره‌شده هرگز از این
    // فهرست بیرون نرود
    private const ALLOWED_UPLOAD_EXTENSIONS = 'jpg,jpeg,png,webp,gif,bmp,pdf,doc,docx,xls,xlsx,txt,mp4,webm,mov,mp3,wav,zip';

    // سقفِ اندازه بر اساس نوعِ رسانه: تصویرها عمداً سخت‌گیرانه (۱۵MB — تصویرِ بهینه‌ی سایت هرگز
    // این‌قدر بزرگ نیست) در برابر ویدئو/صوت/سند که تا سقفِ کلیِ Livewire (۱۲۸MB، config/livewire.php)
    // مجازند. توجه: PHP خودِ سرور (upload_max_filesize/post_max_size) هم باید دستِ‌کم ۱۲۸MB باشد،
    // وگرنه فایلِ بزرگ اصلا به لاراول نمی‌رسد — این را از کد نمی‌شود تضمین کرد، فقط در راهنما هشدار داده می‌شود.
    private const MAX_IMAGE_UPLOAD_KB = 15360;   // 15MB

    private const MAX_MEDIA_UPLOAD_KB = 131072;  // 128MB

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

    public function updatedUploads(): void
    {
        // سقفِ کلی (۱۲۸MB) اینجا اعمال می‌شود؛ سقفِ سخت‌گیرانه‌ترِ تصویر per-file پایین‌تر چک می‌شود
        $this->validate(['uploads.*' => ['file', 'max:'.self::MAX_MEDIA_UPLOAD_KB, 'mimes:'.self::ALLOWED_UPLOAD_EXTENSIONS]]);

        $processor = app(MediaProcessor::class);
        $count = 0;

        foreach ($this->uploads as $upload) {
            if (! $upload) {
                continue;
            }

            // یک تصویرِ بزرگ‌تر از ۱۵MB کلِ آپلودِ چندتایی را نمی‌شکند — فقط همان فایل با پیام رد می‌شود
            if ($this->exceedsTypeSizeLimit($upload)) {
                Notification::make()
                    ->danger()
                    ->title('Too large: '.$upload->getClientOriginalName())
                    ->body('Images must be under 15 MB. Video and other files may be up to 128 MB.')
                    ->send();

                continue;
            }

            try {
                $processor->store($upload, 'media/library', 'public', $this->currentFolderId);
                $count++;
            } catch (Throwable $e) {
                Notification::make()
                    ->danger()
                    ->title('Upload failed: '.$upload->getClientOriginalName())
                    ->body($e->getMessage())
                    ->send();
            }
        }

        $this->uploads = [];

        if ($count > 0) {
            Notification::make()->success()->title($count.' file(s) uploaded')->send();
        }
    }

    public function updatedReplaceFile(): void
    {
        if (! $this->selectedMediaId || ! $this->replaceFile) {
            return;
        }

        $this->validate(['replaceFile' => ['file', 'max:'.self::MAX_MEDIA_UPLOAD_KB, 'mimes:'.self::ALLOWED_UPLOAD_EXTENSIONS]]);

        if ($this->exceedsTypeSizeLimit($this->replaceFile)) {
            Notification::make()
                ->danger()
                ->title('Too large')
                ->body('Images must be under 15 MB. Video and other files may be up to 128 MB.')
                ->send();
            $this->replaceFile = null;

            return;
        }

        $media = Media::findOrFail($this->selectedMediaId);
        app(MediaProcessor::class)->replace($media, $this->replaceFile);
        $this->replaceFile = null;

        Notification::make()->success()->title('File replaced — existing links on the site keep working')->send();
    }

    // سقفِ اندازه بسته به نوعِ فایل — تصویرها ۱۵MB، بقیه (ویدئو/صوت/سند) ۱۲۸MB. از همان
    // طبقه‌بندیِ واحدِ MediaProcessor::resolveType() (فاز ۱) استفاده می‌کند تا منطق دوباره‌کاری نشود.
    private function exceedsTypeSizeLimit(TemporaryUploadedFile $upload): bool
    {
        $type = app(MediaProcessor::class)->resolveType($upload->getMimeType());

        return self::isOverTypeLimit($type, (int) $upload->getSize());
    }

    // تصمیمِ محضِ سیاستِ اندازه، جدا از آپلودِ واقعی تا مستقیم و بدونِ وابستگی به Livewire
    // تست‌پذیر باشد: تصویر → ۱۵MB، هر نوعِ دیگر → ۱۲۸MB.
    public static function isOverTypeLimit(string $type, int $sizeBytes): bool
    {
        $maxKb = $type === 'image' ? self::MAX_IMAGE_UPLOAD_KB : self::MAX_MEDIA_UPLOAD_KB;

        return $sizeBytes > $maxKb * 1024;
    }

    public function openFolder(?int $folderId): void
    {
        $this->currentFolderId = $folderId;
        $this->search = '';
        $this->selectedMediaId = null;
    }

    public function createFolder(): void
    {
        $name = trim($this->newFolderName);

        if ($name === '') {
            Notification::make()->danger()->title('Folder name is required')->send();

            return;
        }

        MediaFolder::create([
            'name' => $name,
            'parent_id' => $this->currentFolderId,
        ]);

        $this->newFolderName = '';
        $this->showNewFolderForm = false;

        Notification::make()->success()->title('Folder created')->send();
    }

    public function startRenamingFolder(int $folderId): void
    {
        $folder = MediaFolder::findOrFail($folderId);
        $this->renamingFolderId = $folderId;
        $this->renamingFolderName = $folder->name;
    }

    public function saveFolderName(): void
    {
        $name = trim($this->renamingFolderName);

        if ($name === '' || ! $this->renamingFolderId) {
            $this->renamingFolderId = null;

            return;
        }

        MediaFolder::whereKey($this->renamingFolderId)->update(['name' => $name]);
        $this->renamingFolderId = null;

        Notification::make()->success()->title('Folder renamed')->send();
    }

    public function deleteFolder(int $folderId): void
    {
        $folder = MediaFolder::findOrFail($folderId);

        if (! $folder->isEmpty()) {
            Notification::make()
                ->danger()
                ->title('Folder is not empty')
                ->body('Move or delete its files and subfolders first.')
                ->send();

            return;
        }

        $parentId = $folder->parent_id;
        $folder->delete();

        if ($this->currentFolderId === $folderId) {
            $this->currentFolderId = $parentId;
        }

        Notification::make()->success()->title('Folder deleted')->send();
    }

    public function selectMedia(int $mediaId): void
    {
        $this->selectedMediaId = $mediaId;
    }

    public function closeDetails(): void
    {
        $this->selectedMediaId = null;
    }

    public function saveAltText(string $altText): void
    {
        if (! $this->selectedMediaId) {
            return;
        }

        Media::whereKey($this->selectedMediaId)->update(['alt_text' => trim($altText) ?: null]);

        Notification::make()->success()->title('ALT text saved')->send();
    }

    // caption/description ستون‌های واقعیِ Media هستند (از خط‌لوله‌ی تصویرِ هوش مصنوعی) که تا حالا
    // فقط توسط AI پر می‌شدند — این‌ها اجازه‌ی ویرایشِ دستی می‌دهند
    public function saveCaption(string $caption): void
    {
        if (! $this->selectedMediaId) {
            return;
        }

        Media::whereKey($this->selectedMediaId)->update(['caption' => trim($caption) ?: null]);

        Notification::make()->success()->title('Caption saved')->send();
    }

    public function saveDescription(string $description): void
    {
        if (! $this->selectedMediaId) {
            return;
        }

        Media::whereKey($this->selectedMediaId)->update(['description' => trim($description) ?: null]);

        Notification::make()->success()->title('Description saved')->send();
    }

    public function moveSelectedToFolder(string $folderId): void
    {
        if (! $this->selectedMediaId) {
            return;
        }

        Media::whereKey($this->selectedMediaId)->update(['folder_id' => $folderId !== '' ? (int) $folderId : null]);

        Notification::make()->success()->title('Moved')->send();
    }

    // تشخیص + بازتولیدِ WebP/مشتقاتِ یک تصویر — دقیقاً می‌گوید چه اتفاقی می‌افتد (ساخته شد؟
    // روی دیسک هست؟ مسیرش ذخیره شد؟ یا خطای دقیق). هم ابزارِ عیب‌یابی است هم رفعِ تصاویرِ قدیمی.
    public function regenerateDerivatives(int $mediaId): void
    {
        $media = Media::findOrFail($mediaId);
        $report = app(MediaProcessor::class)->regenerate($media);

        if ($report['error']) {
            Notification::make()
                ->danger()
                ->title('WebP could not be generated')
                ->body($report['error'])
                ->persistent()
                ->send();

            return;
        }

        if ($report['webp_created'] && $report['webp_exists_on_disk']) {
            Notification::make()
                ->success()
                ->title('WebP generated successfully')
                ->body('Saved to: '.$report['webp_path'])
                ->send();

            return;
        }

        Notification::make()
            ->warning()
            ->title('Ran without error, but no WebP file was produced')
            ->body('The encode reported success but the file is not on disk — likely a disk-write/permission issue in '.dirname((string) $report['webp_path']).'.')
            ->persistent()
            ->send();
    }

    public function deleteMedia(int $mediaId): void
    {
        $media = Media::findOrFail($mediaId);
        $usages = $media->usages();

        if (count($usages) > 0) {
            Notification::make()
                ->danger()
                ->title('Cannot delete — this file is in use')
                ->body(collect($usages)->pluck('label')->implode(', '))
                ->send();

            return;
        }

        app(MediaProcessor::class)->delete($media);

        if ($this->selectedMediaId === $mediaId) {
            $this->selectedMediaId = null;
        }

        Notification::make()->success()->title('Deleted')->send();
    }

    public function getSelectedMediaProperty(): ?Media
    {
        return $this->selectedMediaId ? Media::find($this->selectedMediaId) : null;
    }

    public function getCurrentFolderProperty(): ?MediaFolder
    {
        return $this->currentFolderId ? MediaFolder::find($this->currentFolderId) : null;
    }

    /** @return Collection<int, MediaFolder> */
    public function getRootFoldersProperty(): Collection
    {
        return MediaFolder::whereNull('parent_id')->orderBy('name')->get();
    }

    // پوشه‌های کودک همان پوشه‌ای که هم‌اکنون در حال مرور آن هستیم
    public function getSubfoldersProperty(): Collection
    {
        return MediaFolder::where('parent_id', $this->currentFolderId)->orderBy('name')->get();
    }

    // فهرست تخت همه‌ی پوشه‌ها با مسیر کامل — برای منوی «انتقال به پوشه»
    public function getAllFoldersProperty(): Collection
    {
        return MediaFolder::orderBy('name')->get()->map(fn (MediaFolder $folder) => [
            'id' => $folder->id,
            'label' => $folder->fullPath(),
        ]);
    }

    // زنجیره‌ی breadcrumb از ریشه تا پوشه‌ی جاری
    public function getBreadcrumbTrailProperty(): array
    {
        $trail = [];
        $folder = $this->currentFolder;

        while ($folder) {
            array_unshift($trail, $folder);
            $folder = $folder->parent;
        }

        return $trail;
    }

    public function getMediaItemsProperty(): Collection
    {
        $query = Media::query()->latest();

        // جست‌وجو کل کتابخانه را می‌گردد؛ در غیر این صورت فقط پوشه‌ی جاری
        if ($this->search !== '') {
            $query->where('original_name', 'like', '%'.$this->search.'%');
        } else {
            $query->where('folder_id', $this->currentFolderId);
        }

        if ($this->typeFilter === 'image') {
            $query->where('type', 'image');
        } elseif ($this->typeFilter === 'video') {
            $query->where('type', 'video');
        } elseif ($this->typeFilter === 'other') {
            // «سایر فایل‌ها» = نه تصویر و نه ویدئو (سند/صوت/زیپ/…) — ویدئوها فیلترِ اختصاصیِ
            // خودشان را دارند، پس هیچ فایلی از هیچ فیلتری ناپدید نمی‌شود.
            $query->whereNotIn('type', ['image', 'video']);
        }

        if ($this->onlyMissingAlt) {
            $query->where(function ($q) {
                $q->whereNull('alt_text')->orWhere('alt_text', '');
            });
        }

        if ($this->onlyLarge) {
            $query->where('size', '>', 500 * 1024);
        }

        $items = $query->limit(self::MAX_ITEMS)->get();

        if ($this->onlyUnused) {
            $items = $items->filter(fn (Media $media) => ! $media->isInUse())->values();
        }

        // «یتیم» زیرمجموعه‌ی «استفاده‌نشده» است ولی باریک‌تر — همان الگوی فیلترِ پس‌از-کوئریِ
        // onlyUnused (اسکنِ per-item)، فقط وقتی فیلتر فعال است، نه در هر رندر
        if ($this->onlyOrphaned) {
            $items = $items->filter(fn (Media $media) => $media->isOrphan())->values();
        }

        return $items;
    }
}
