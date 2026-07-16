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

    public string $typeFilter = 'all'; // all | image | other

    public bool $onlyUnused = false;

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

    public function updatedUploads(): void
    {
        $this->validate(['uploads.*' => ['file', 'max:15360']]);

        $processor = app(MediaProcessor::class);
        $count = 0;

        foreach ($this->uploads as $upload) {
            if (! $upload) {
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

        $this->validate(['replaceFile' => ['file', 'max:15360']]);

        $media = Media::findOrFail($this->selectedMediaId);
        app(MediaProcessor::class)->replace($media, $this->replaceFile);
        $this->replaceFile = null;

        Notification::make()->success()->title('File replaced — existing links on the site keep working')->send();
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

    public function moveSelectedToFolder(string $folderId): void
    {
        if (! $this->selectedMediaId) {
            return;
        }

        Media::whereKey($this->selectedMediaId)->update(['folder_id' => $folderId !== '' ? (int) $folderId : null]);

        Notification::make()->success()->title('Moved')->send();
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

        if ($this->typeFilter !== 'all') {
            $query->where('type', $this->typeFilter);
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

        return $items;
    }
}
