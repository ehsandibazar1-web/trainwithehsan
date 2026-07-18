<?php

namespace App\Filament\Concerns;

use App\Models\Media;
use App\Models\MediaFolder;
use App\Services\Media\MediaProcessor;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

/**
 * منطقِ مشترکِ کتابخانه‌ی رسانه — استخراج‌شده از App\Filament\Pages\MediaLibrary تا هم آن صفحه
 * و هم App\Livewire\MediaPicker (پنجره‌ی انتخابِ رسانه‌ی یکپارچه) دقیقاً یک پیاده‌سازی داشته
 * باشند، نه دو نسخه‌ی موازی («There should never be duplicate picker implementations»).
 *
 * هرچه اینجاست رفتارِ صفحه‌ی MediaLibrary را عیناً حفظ می‌کند؛ نقاطِ توسعه‌پذیر
 * (searchableColumns/uploadDirectory) با مقدارِ پیش‌فرضِ صفحه شروع می‌شوند تا سیم‌کشیِ فعلی
 * تغییری نکند، و فقط MediaPicker آن‌ها را برای جست‌وجوی گسترده‌تر / پوشه‌ی آپلودِ اختصاصی
 * بازنویسی می‌کند.
 */
trait InteractsWithMediaLibrary
{
    // پوشه‌ای که هم‌اکنون در حال مرور آن هستیم — null یعنی ریشه
    public ?int $currentFolderId = null;

    public string $search = '';

    public string $typeFilter = 'all'; // all | image | video | document | audio | archive | other

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
    protected const MAX_ITEMS = 300;

    // اعتبارسنجیِ اولیه‌ی سطح فرم (بر اساس محتوای واقعی فایل، نه پسوند/Content-Type ادعاشده
    // توسط کلاینت — قانون mimes: خودِ لاراول همین‌طور کار می‌کند) — لاراول به‌طور جداگانه هر
    // آپلودی که پسوندِ کلاینتش php/php3/php4/php5/php7/php8/phtml/phar باشد را هم رد می‌کند.
    // MediaProcessor::store() علاوه بر این، همین فهرست را دوباره (بر اساس MIME واقعی) اعمال
    // می‌کند تا حتی اگر این اعتبارسنجی به هر دلیلی دور زده شود، پسوندِ ذخیره‌شده هرگز از این
    // فهرست بیرون نرود
    protected const ALLOWED_UPLOAD_EXTENSIONS = 'jpg,jpeg,png,webp,gif,bmp,pdf,doc,docx,xls,xlsx,txt,mp4,webm,mov,mp3,wav,zip';

    // سقفِ اندازه بر اساس نوعِ رسانه: تصویرها عمداً سخت‌گیرانه (۱۵MB — تصویرِ بهینه‌ی سایت هرگز
    // این‌قدر بزرگ نیست) در برابر ویدئو/صوت/سند که تا سقفِ کلیِ Livewire (۱۲۸MB، config/livewire.php)
    // مجازند. توجه: PHP خودِ سرور (upload_max_filesize/post_max_size) هم باید دستِ‌کم ۱۲۸MB باشد،
    // وگرنه فایلِ بزرگ اصلا به لاراول نمی‌رسد — این را از کد نمی‌شود تضمین کرد، فقط در راهنما هشدار داده می‌شود.
    protected const MAX_IMAGE_UPLOAD_KB = 15360;   // 15MB

    protected const MAX_MEDIA_UPLOAD_KB = 131072;  // 128MB

    // پوشه‌ی پیش‌فرضِ آپلود — صفحه‌ی MediaLibrary همیشه در media/library آپلود می‌کند؛ MediaPicker
    // می‌تواند این را (بر اساس فیلدی که پنجره را باز کرده) موقتاً عوض کند تا مثلا تصویرِ شاخصِ
    // یک مقاله در پوشه‌ی articles/ بنشیند و ردگیریِ «یتیم» دست‌نخورده بماند.
    public string $uploadDirectory = 'media/library';

    // ستون‌هایی که جست‌وجو رویشان کار می‌کند — صفحه فقط بر اساس نام فایل می‌گردد؛ MediaPicker این
    // را گسترش می‌دهد (ALT/کپشن/توضیح/نوع MIME) — «Instant search by Filename, ALT, Caption…»
    protected function searchableColumns(): array
    {
        return ['original_name'];
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
                $processor->store($upload, $this->uploadDirectory, 'public', $this->currentFolderId);
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
    protected function exceedsTypeSizeLimit(TemporaryUploadedFile $upload): bool
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

    // بازتولیدِ WebP برای همه‌ی تصاویری که هنوز نسخه‌ی WebP ندارند (webp_path == null) — تصاویری که
    // پیش از رفعِ خطِ لوله آپلود شده‌اند و هرگز دوباره تلاش نشده. همان MediaProcessor::regenerate() که
    // دکمه‌ی تک‌عکسِ regenerateDerivatives() صدا می‌زند، فقط روی همه‌ی آن‌ها یک‌جا. خلاصه‌ی شمارشی
    // (ساخته‌شد/رد/شکست) گزارش می‌شود؛ خطاهای تکیِ per-image کلِ عملیات را نمی‌شکنند.
    public function regenerateAllMissingWebp(): void
    {
        $processor = app(MediaProcessor::class);
        $regenerated = 0;
        $failed = 0;

        Media::where('type', 'image')->whereNull('webp_path')->get()->each(function (Media $media) use ($processor, &$regenerated, &$failed) {
            $report = $processor->regenerate($media);

            if (! $report['error'] && $report['webp_created'] && $report['webp_exists_on_disk']) {
                $regenerated++;
            } else {
                $failed++;
            }
        });

        if ($regenerated === 0 && $failed === 0) {
            Notification::make()->success()->title('Every image already has a WebP version')->send();

            return;
        }

        if ($failed === 0) {
            Notification::make()->success()->title($regenerated.' image(s) now have a WebP version')->send();

            return;
        }

        Notification::make()
            ->warning()
            ->title($regenerated.' regenerated, '.$failed.' could not be')
            ->body('Open a failed image and use its Regenerate WebP button to see the exact reason (missing original file, unsupported encoding, or a disk-write issue).')
            ->persistent()
            ->send();
    }

    // چند تصویر هنوز نسخه‌ی WebP ندارند — برای نشان دادن (یا پنهان کردنِ) دکمه‌ی بازتولیدِ گروهی
    public function getImagesMissingWebpCountProperty(): int
    {
        return Media::where('type', 'image')->whereNull('webp_path')->count();
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

        // جست‌وجو کل کتابخانه را می‌گردد (روی ستون‌های searchableColumns())؛ در غیر این صورت فقط پوشه‌ی جاری
        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $columns = $this->searchableColumns();
            $query->where(function (Builder $q) use ($columns, $term) {
                foreach ($columns as $column) {
                    $q->orWhere($column, 'like', $term);
                }
            });
        } else {
            $query->where('folder_id', $this->currentFolderId);
        }

        $this->applyTypeFilter($query);

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

    // فیلترِ نوع — نگاشتِ گزینه‌های نوارِ بالای پنجره روی ستون‌های واقعیِ Media. صفحه‌ی MediaLibrary
    // فقط all/image/video/other می‌فرستد؛ MediaPicker مجموعه‌ی کاملِ اسناد/صوت/آرشیو را هم می‌فرستد
    // — یک جای واحد که هر دو سطح از آن استفاده می‌کنند.
    protected function applyTypeFilter(Builder $query): void
    {
        match ($this->typeFilter) {
            'image' => $query->where('type', 'image'),
            'video' => $query->where('type', 'video'),
            'audio' => $query->where('type', 'audio'),
            'document' => $query->where('type', 'document'),
            // آرشیو (zip) در طبقه‌بندیِ MediaProcessor نوعِ 'other' است ولی MIME مشخصی دارد
            'archive' => $query->where('mime_type', 'application/zip'),
            // «سایر» (page/legacy) = هرچه تصویر و ویدئو نیست — چون منوی صفحه‌ی MediaLibrary فقط
            // all/image/video/other دارد، این باید فراگیر بماند وگرنه اسناد/صوت از آن صفحه ناپدید می‌شوند
            'other' => $query->whereNotIn('type', ['image', 'video']),
            // «Other» در نوارِ MediaPicker (که چیپ‌های اختصاصیِ Documents/Audio/Archives هم دارد) =
            // فقط فایل‌های واقعاً طبقه‌بندی‌نشده: نوعِ 'other' منهای آرشیو
            'other_only' => $query->where('type', 'other')->where('mime_type', '!=', 'application/zip'),
            default => null, // all
        };
    }
}
