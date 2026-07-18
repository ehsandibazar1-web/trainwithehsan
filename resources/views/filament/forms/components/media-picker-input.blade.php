@php
    $statePath = $getStatePath();
    $media = $getSelectedMedia();
    $state = $getState();
    $onlyImages = $isOnlyImages();
    $uploadDir = $getUploadDirectory();
    $initialType = $getInitialType();
    $previewUrl = $media && $media->type === 'image'
        ? $media->thumbnail_url
        : ($onlyImages && filled($state) ? \Illuminate\Support\Facades\Storage::disk('public')->url($state) : null);
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    {{--
        فیلد فقط ویجت است — مقدار (disk_path) با $wire.set ست می‌شود، پس هیچ ذخیره‌ی سفارشی‌ای لازم
        نیست و شکلِ داده دست‌نخورده می‌ماند. مقادیرِ باز کردن/شنیدن در x-data می‌نشینند (الگوی استانداردِ
        @js داخلِ x-data) تا عبارتِ inline پیچیده و شکننده نشود.
    --}}
    <div
        x-data="{
            pickTarget: @js($statePath),
            onlyImages: @js($onlyImages),
            uploadDir: @js($uploadDir),
            initialType: @js($initialType),
            openPicker() {
                window.dispatchEvent(new CustomEvent('open-media-picker', {
                    detail: { target: this.pickTarget, onlyImages: this.onlyImages, uploadDirectory: this.uploadDir, initialType: this.initialType },
                }));
            },
        }"
        x-on:media-picker-selected.window="if ($event.detail.target === pickTarget) { $wire.set(pickTarget, $event.detail.disk_path) }"
    >
        <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap">
            @if($previewUrl)
                <img src="{{ $previewUrl }}" alt="{{ $media?->alt_text }}"
                    style="width:96px;height:96px;object-fit:cover;border-radius:.55rem;border:1px solid rgb(229 231 235)">
            @elseif(filled($state))
                <div style="display:flex;align-items:center;gap:.4rem;padding:.5rem .7rem;border:1px solid rgb(229 231 235);border-radius:.55rem;font-size:.82rem;color:#374151">
                    📎 <span style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $media?->original_name ?? basename($state) }}</span>
                </div>
            @else
                <div style="font-size:.82rem;color:#9ca3af">No file selected</div>
            @endif

            <div style="display:flex;gap:.5rem;flex-wrap:wrap">
                <x-filament::button type="button" size="sm" icon="heroicon-o-photo" x-on:click="openPicker()">
                    {{ filled($state) ? 'Change' : ($onlyImages ? 'Choose image' : 'Choose from Media Library') }}
                </x-filament::button>

                @if(filled($state))
                    <x-filament::button type="button" size="sm" color="gray" x-on:click="$wire.set(pickTarget, null)">
                        Remove
                    </x-filament::button>
                @endif
            </div>
        </div>
    </div>
</x-dynamic-component>
