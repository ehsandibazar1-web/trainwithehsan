{{--
    پنجره‌ی انتخابِ رسانه‌ی یکپارچه — یک‌بار سراسری در چرومِ پنل mount می‌شود. تا وقتی isOpen نیست
    فقط این ریشه و شنونده‌ی رویداد وجود دارد (بدونِ کوئری/بارِ اضافه روی هر صفحه‌ی پنل).
    x-on:open-media-picker.window: فیلدها با یک window CustomEvent پنجره را باز می‌کنند.
--}}
<div
    x-data
    x-on:open-media-picker.window="$wire.openFor($event.detail.target, $event.detail.onlyImages ?? false, $event.detail.uploadDirectory ?? null)"
>
    <style>
        .mp-overlay{position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:60;display:flex;align-items:center;justify-content:center;padding:1rem}
        .mp-modal{background:#fff;border-radius:.9rem;width:100%;max-width:1140px;height:86vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 24px 60px rgba(0,0,0,.35)}
        :root.dark .mp-modal{background:#18181b;color:#e4e4e7}

        .mp-head{display:flex;align-items:center;gap:.75rem;padding:.85rem 1.1rem;border-bottom:1px solid rgb(229 231 235)}
        :root.dark .mp-head{border-color:#3f3f46}
        .mp-head h2{font-size:1rem;font-weight:700;margin:0;flex-shrink:0}
        .mp-search{flex:1;min-width:120px;border:1px solid rgb(209 213 219);border-radius:.55rem;padding:.45rem .7rem;font-size:.85rem;background:#fff}
        :root.dark .mp-search{background:#27272a;border-color:#3f3f46;color:#e4e4e7}
        .mp-viewtoggle{display:inline-flex;border:1px solid rgb(209 213 219);border-radius:.55rem;overflow:hidden}
        :root.dark .mp-viewtoggle{border-color:#3f3f46}
        .mp-viewtoggle button{background:none;border:none;padding:.4rem .6rem;cursor:pointer;font-size:.85rem;color:#6b7280}
        .mp-viewtoggle button.active{background:#fef3c7;color:#92400e}
        :root.dark .mp-viewtoggle button.active{background:#3f3f46;color:#fde68a}
        .mp-close{background:none;border:none;font-size:1.3rem;line-height:1;cursor:pointer;color:#6b7280;padding:.15rem .4rem}

        .mp-filters{display:flex;flex-wrap:wrap;gap:.4rem;padding:.6rem 1.1rem;border-bottom:1px solid rgb(229 231 235)}
        :root.dark .mp-filters{border-color:#3f3f46}
        .mp-chip{border:1px solid rgb(209 213 219);background:#fff;border-radius:9999px;padding:.28rem .7rem;font-size:.78rem;cursor:pointer;color:#374151}
        :root.dark .mp-chip{background:#27272a;border-color:#3f3f46;color:#d4d4d8}
        .mp-chip.active{background:#d9bb75;border-color:#d9bb75;color:#1f2937;font-weight:600}
        .mp-chip:disabled{opacity:.5;cursor:not-allowed}

        .mp-body{flex:1;display:grid;grid-template-columns:180px 1fr;min-height:0}
        .mp-body.with-panel{grid-template-columns:180px 1fr 340px}
        @media(max-width:820px){.mp-body,.mp-body.with-panel{grid-template-columns:1fr}}

        .mp-folders{border-right:1px solid rgb(229 231 235);padding:.75rem;overflow-y:auto}
        :root.dark .mp-folders{border-color:#3f3f46}
        .mp-folder{display:flex;align-items:center;gap:.4rem;width:100%;text-align:left;background:none;border:none;cursor:pointer;padding:.4rem .5rem;border-radius:.5rem;font-size:.82rem;color:#374151}
        :root.dark .mp-folder{color:#d4d4d8}
        .mp-folder:hover{background:#f3f4f6}
        :root.dark .mp-folder:hover{background:#27272a}
        .mp-folder.active{background:#fef3c7;font-weight:600}
        :root.dark .mp-folder.active{background:#3f3f46}

        .mp-main{overflow-y:auto;padding:.9rem 1.1rem}
        .mp-drop{border:2px dashed rgb(209 213 219);border-radius:.7rem;padding:1rem;text-align:center;cursor:pointer;color:#6b7280;font-size:.82rem;margin-bottom:.9rem;transition:background .15s,border-color .15s}
        .mp-drop.dragover{background:#fffbeb;border-color:#d9bb75}
        .mp-breadcrumb{font-size:.78rem;color:#6b7280;margin-bottom:.6rem}
        .mp-breadcrumb button{background:none;border:none;color:#6b7280;cursor:pointer;padding:0;font-size:.78rem}
        .mp-breadcrumb button:hover{text-decoration:underline}

        .mp-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:.75rem}
        .mp-item{border:2px solid transparent;border-radius:.6rem;overflow:hidden;background:#f3f4f6;cursor:pointer;text-align:left;padding:0;position:relative;aspect-ratio:1/1}
        :root.dark .mp-item{background:#27272a}
        .mp-item:hover{border-color:#e5c98a}
        .mp-item.selected{border-color:#d9bb75;box-shadow:0 0 0 2px #d9bb75}
        .mp-item img{width:100%;height:100%;object-fit:cover;display:block}
        .mp-item .ico{display:flex;align-items:center;justify-content:center;height:100%;font-size:2rem;color:#9ca3af}
        .mp-item .nm{position:absolute;left:0;right:0;bottom:0;background:rgba(0,0,0,.55);color:#fff;font-size:.66rem;padding:.2rem .35rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .mp-item .badge{position:absolute;top:.25rem;right:.25rem;background:#f59e0b;color:#fff;border-radius:9999px;width:1.1rem;height:1.1rem;display:flex;align-items:center;justify-content:center;font-size:.65rem;font-weight:700}

        .mp-list{display:flex;flex-direction:column;gap:.25rem}
        .mp-row{display:flex;align-items:center;gap:.7rem;padding:.45rem .5rem;border-radius:.5rem;cursor:pointer;border:1px solid transparent}
        .mp-row:hover{background:#f9fafb}
        :root.dark .mp-row:hover{background:#27272a}
        .mp-row.selected{border-color:#d9bb75;background:#fffbeb}
        :root.dark .mp-row.selected{background:#3f3f46}
        .mp-row .thumb{width:40px;height:40px;border-radius:.35rem;object-fit:cover;flex-shrink:0;background:#f3f4f6;display:flex;align-items:center;justify-content:center;font-size:1.1rem;color:#9ca3af}
        .mp-row .r-name{flex:1;font-size:.82rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .mp-row .r-meta{font-size:.72rem;color:#9ca3af;flex-shrink:0}

        .mp-empty{color:#9ca3af;font-size:.85rem;text-align:center;padding:2.5rem 0}

        .mp-panel{border-left:1px solid rgb(229 231 235);overflow-y:auto;padding:1rem}
        :root.dark .mp-panel{border-color:#3f3f46}
        .mp-panel .pv{width:100%;max-height:200px;object-fit:contain;border-radius:.5rem;background:#f3f4f6;margin-bottom:.7rem}
        .mp-panel h3{font-size:.9rem;font-weight:700;margin:0 0 .3rem;word-break:break-word}
        .mp-panel .kv{font-size:.76rem;color:#6b7280;line-height:1.7;margin-bottom:.7rem}
        .mp-fld{margin-bottom:.7rem}
        .mp-fld label{display:block;font-size:.72rem;font-weight:600;margin-bottom:.25rem;color:#374151}
        :root.dark .mp-fld label{color:#d4d4d8}
        .mp-fld input{width:100%;border:1px solid rgb(209 213 219);border-radius:.45rem;padding:.35rem .55rem;font-size:.8rem;background:#fff}
        :root.dark .mp-fld input{background:#27272a;border-color:#3f3f46;color:#e4e4e7}
        .mp-note{font-size:.74rem;border-radius:.45rem;padding:.4rem .6rem;margin-bottom:.5rem}
        .mp-note.ok{background:#f0fdf4;color:#15803d}
        .mp-note.warn{background:#fffbeb;color:#92400e}
        .mp-note.err{background:#fef2f2;color:#991b1b}
        .mp-usage{font-size:.76rem;color:#92400e;margin:.2rem 0 0;padding-left:1.1rem}

        .mp-foot{display:flex;align-items:center;justify-content:space-between;gap:.6rem;padding:.75rem 1.1rem;border-top:1px solid rgb(229 231 235)}
        :root.dark .mp-foot{border-color:#3f3f46}
        .mp-foot .hint{font-size:.76rem;color:#9ca3af}
    </style>

    @if($isOpen)
        <div class="mp-overlay" wire:key="mp-overlay" x-on:keydown.escape.window="$wire.close()">
            <div class="mp-modal" x-on:click.outside="$wire.close()">
                {{-- هدر: عنوان + جست‌وجو + تعویضِ نما + بستن --}}
                <div class="mp-head">
                    <h2>{{ $onlyImages ? 'Choose an image' : 'Media Library' }}</h2>
                    <input type="search" class="mp-search" wire:model.live.debounce.350ms="search"
                        placeholder="Search filename, ALT, caption, description, type…">
                    <div class="mp-viewtoggle">
                        <button type="button" class="{{ $viewMode === 'grid' ? 'active' : '' }}" wire:click="setViewMode('grid')" title="Grid view">▦</button>
                        <button type="button" class="{{ $viewMode === 'list' ? 'active' : '' }}" wire:click="setViewMode('list')" title="List view">☰</button>
                    </div>
                    <button type="button" class="mp-close" wire:click="close" title="Close">✕</button>
                </div>

                {{-- فیلترها: در حالتِ فقط-تصویر فقط «Images» معنا دارد، پس چیپ‌های نوع پنهان می‌شوند --}}
                <div class="mp-filters">
                    @unless($onlyImages)
                        @php($types = ['all' => 'All','image' => 'Images','video' => 'Videos','document' => 'Documents','audio' => 'Audio','archive' => 'Archives','other_only' => 'Other'])
                        @foreach($types as $val => $lbl)
                            <button type="button" class="mp-chip {{ $typeFilter === $val ? 'active' : '' }}" wire:click="setTypeFilter('{{ $val }}')">{{ $lbl }}</button>
                        @endforeach
                        <span style="width:1px;background:#e5e7eb;margin:0 .2rem"></span>
                    @endunless
                    <button type="button" class="mp-chip {{ $onlyUnused ? 'active' : '' }}" wire:click="$toggle('onlyUnused')">Unused</button>
                    <button type="button" class="mp-chip" disabled title="Coming soon">★ Favorites</button>
                </div>

                {{-- بدنه: پوشه‌ها | شبکه/فهرست | پنلِ جزئیات (فقط وقتی چیزی انتخاب شده) --}}
                <div class="mp-body {{ $this->selectedMedia ? 'with-panel' : '' }}">
                    {{-- ستونِ پوشه‌ها --}}
                    <div class="mp-folders">
                        <button type="button" class="mp-folder {{ is_null($currentFolderId) ? 'active' : '' }}" wire:click="openFolder(null)">📁 All files</button>
                        @foreach($this->rootFolders as $folder)
                            <button type="button" class="mp-folder {{ $currentFolderId === $folder->id ? 'active' : '' }}" wire:click="openFolder({{ $folder->id }})">📁 {{ $folder->name }}</button>
                        @endforeach
                    </div>

                    {{-- ناحیه‌ی اصلی --}}
                    <div class="mp-main">
                        {{-- آپلودِ درون‌پنجره‌ای (درگ‌اند‌دراپ + انتخاب) — از MediaProcessor عبور می‌کند --}}
                        <div class="mp-drop"
                            x-data
                            x-on:click="$refs.mpFile.click()"
                            x-on:dragover.prevent="$el.classList.add('dragover')"
                            x-on:dragleave="$el.classList.remove('dragover')"
                            x-on:drop.prevent="$el.classList.remove('dragover'); if ($event.dataTransfer.files.length) $wire.uploadMultiple('uploads', $event.dataTransfer.files)">
                            <strong>Drag &amp; drop</strong> or click to upload — added to the library with WebP, thumbnail and responsive sizes generated automatically.
                            <span wire:loading wire:target="uploads">· Uploading…</span>
                            <input type="file" multiple x-ref="mpFile" style="display:none"
                                x-on:change="if ($event.target.files.length) { $wire.uploadMultiple('uploads', $event.target.files); $event.target.value='' }">
                        </div>

                        {{-- breadcrumb (فقط وقتی جست‌وجو خالی است) --}}
                        @if($search === '' && $this->breadcrumbTrail)
                            <div class="mp-breadcrumb">
                                <button type="button" wire:click="openFolder(null)">Media Library</button>
                                @foreach($this->breadcrumbTrail as $crumb)
                                    / <button type="button" wire:click="openFolder({{ $crumb->id }})">{{ $crumb->name }}</button>
                                @endforeach
                            </div>
                        @endif

                        @if($viewMode === 'grid')
                            <div class="mp-grid" wire:loading.class="opacity-50" wire:target="search,typeFilter,onlyUnused,openFolder,setTypeFilter">
                                @forelse($this->mediaItems as $item)
                                    <button type="button" wire:key="mp-g-{{ $item->id }}"
                                        class="mp-item {{ $selectedMediaId === $item->id ? 'selected' : '' }}"
                                        wire:click="selectMedia({{ $item->id }})"
                                        x-on:dblclick="$wire.chooseAndReturn({{ $item->id }})"
                                        title="{{ $item->original_name }} — double-click to use">
                                        @if($item->type === 'image')
                                            <img src="{{ $item->thumbnail_url }}" loading="lazy" alt="{{ $item->alt_text }}">
                                        @else
                                            <div class="ico">{{ \App\Livewire\MediaPicker::icon($item) }}</div>
                                        @endif
                                        @if(count($item->warnings()))
                                            <span class="badge" title="{{ implode(' / ', $item->warnings()) }}">!</span>
                                        @endif
                                        <span class="nm">{{ $item->original_name }}</span>
                                    </button>
                                @empty
                                    <div class="mp-empty" style="grid-column:1/-1">No files here yet — drop some in above.</div>
                                @endforelse
                            </div>
                        @else
                            <div class="mp-list" wire:loading.class="opacity-50" wire:target="search,typeFilter,onlyUnused,openFolder,setTypeFilter">
                                @forelse($this->mediaItems as $item)
                                    <div wire:key="mp-l-{{ $item->id }}"
                                        class="mp-row {{ $selectedMediaId === $item->id ? 'selected' : '' }}"
                                        wire:click="selectMedia({{ $item->id }})"
                                        x-on:dblclick="$wire.chooseAndReturn({{ $item->id }})">
                                        @if($item->type === 'image')
                                            <img class="thumb" src="{{ $item->thumbnail_url }}" loading="lazy" alt="">
                                        @else
                                            <div class="thumb">{{ \App\Livewire\MediaPicker::icon($item) }}</div>
                                        @endif
                                        <span class="r-name">{{ $item->original_name }}</span>
                                        <span class="r-meta">{{ ucfirst($item->type) }} · {{ $item->human_size }}</span>
                                    </div>
                                @empty
                                    <div class="mp-empty">No files here yet — drop some in above.</div>
                                @endforelse
                            </div>
                        @endif
                    </div>

                    {{-- پنلِ جزئیات/پیش‌نمایش --}}
                    @if($this->selectedMedia)
                        @php($sel = $this->selectedMedia)
                        @php($usages = $sel->usages())
                        <div class="mp-panel" wire:key="mp-panel-{{ $sel->id }}">
                            @if($sel->processingFailed())
                                <div class="mp-note err">✕ Could not be processed — no optimized (WebP) version. It may be corrupt or unsupported.</div>
                            @endif

                            @if($sel->type === 'image')
                                <img class="pv" src="{{ $sel->webp_url ?? $sel->url }}" alt="{{ $sel->alt_text }}">
                            @elseif($sel->type === 'video')
                                <video class="pv" controls preload="metadata" src="{{ $sel->url }}"></video>
                            @else
                                <div class="pv" style="display:flex;align-items:center;justify-content:center;height:120px;font-size:2.5rem;color:#9ca3af">{{ \App\Livewire\MediaPicker::icon($sel) }}</div>
                            @endif

                            <h3>{{ $sel->original_name }}</h3>
                            <div class="kv">
                                {{ ucfirst($sel->type) }}@if($sel->mime_type) · {{ $sel->mime_type }}@endif<br>
                                @if($sel->width){{ $sel->width }}×{{ $sel->height }}px · @endif{{ $sel->human_size }}<br>
                                Uploaded {{ $sel->created_at?->format('M j, Y') }}
                                @if($sel->folder) · in {{ $sel->folder->fullPath() }}@endif
                            </div>

                            @foreach($sel->warnings() as $warning)
                                <div class="mp-note warn">⚠ {{ $warning }}</div>
                            @endforeach

                            @if($sel->type === 'image')
                                <div class="mp-fld">
                                    <label>ALT text (accessibility &amp; image SEO)</label>
                                    <input type="text" x-ref="mpAlt" value="{{ $sel->alt_text }}" placeholder="Describe this image…">
                                    <div style="margin-top:.35rem"><x-filament::button size="xs" color="gray" x-on:click="$wire.saveAltText($refs.mpAlt.value)">Save ALT</x-filament::button></div>
                                </div>
                            @endif

                            <div class="mp-fld">
                                <label>Caption</label>
                                <input type="text" x-ref="mpCap" value="{{ $sel->caption }}" placeholder="A short caption…">
                                <div style="margin-top:.35rem"><x-filament::button size="xs" color="gray" x-on:click="$wire.saveCaption($refs.mpCap.value)">Save caption</x-filament::button></div>
                            </div>

                            <div class="mp-fld">
                                <label>Description</label>
                                <input type="text" x-ref="mpDesc" value="{{ $sel->description }}" placeholder="A longer description…">
                                <div style="margin-top:.35rem"><x-filament::button size="xs" color="gray" x-on:click="$wire.saveDescription($refs.mpDesc.value)">Save description</x-filament::button></div>
                            </div>

                            @if($sel->type === 'image')
                                <div class="mp-fld">
                                    <label>WebP optimization</label>
                                    @if($sel->webp_path)
                                        <div class="mp-note ok" style="margin-bottom:.35rem">
                                            Original: <strong>{{ $sel->human_size }}</strong> · WebP: <strong>{{ $sel->webp_human_size }}</strong>
                                            @php($saved = $sel->webp_savings_percent)
                                            @if(! is_null($saved))
                                                ·
                                                @if($saved >= 0)
                                                    Saved <strong>{{ $saved }}%</strong>
                                                @else
                                                    {{ abs($saved) }}% larger
                                                @endif
                                            @endif
                                        </div>
                                        @if($sel->responsive_urls)
                                            <div class="kv" style="margin-bottom:.35rem">Responsive sizes: {{ implode('px, ', array_keys($sel->responsive_urls)) }}px</div>
                                        @endif
                                    @else
                                        <div class="mp-note warn" style="margin-bottom:.35rem">No WebP version yet.</div>
                                    @endif
                                    <x-filament::button size="xs" color="gray" icon="heroicon-o-arrow-path" wire:click="regenerateDerivatives({{ $sel->id }})" wire:loading.attr="disabled" wire:target="regenerateDerivatives">Regenerate WebP</x-filament::button>
                                </div>
                            @endif

                            <div class="mp-fld">
                                <label>Used in ({{ count($usages) }})</label>
                                @if(count($usages))
                                    <ul class="mp-usage">
                                        @foreach($usages as $usage)<li>{{ $usage['type'] }} — {{ $usage['label'] }} ({{ $usage['field'] }})</li>@endforeach
                                    </ul>
                                @else
                                    <div class="kv">Not used anywhere yet.</div>
                                @endif
                            </div>

                            <div style="display:flex;gap:.4rem;flex-wrap:wrap;margin-top:.5rem">
                                <x-filament::button size="xs" color="gray" tag="a" :href="$sel->url" target="_blank">Download</x-filament::button>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- فوتر: درجِ انتخاب‌شده / انصراف --}}
                <div class="mp-foot">
                    <span class="hint">Single-click selects · double-click uses it immediately</span>
                    <div style="display:flex;gap:.5rem">
                        <x-filament::button color="gray" wire:click="close">Cancel</x-filament::button>
                        @if($selectedMediaId)
                            <x-filament::button wire:click="chooseAndReturn({{ $selectedMediaId }})">Use this file</x-filament::button>
                        @else
                            <x-filament::button disabled>Use this file</x-filament::button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
