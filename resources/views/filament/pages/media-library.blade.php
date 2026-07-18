<x-filament-panels::page>
    <style>
        .media-lib{display:grid;grid-template-columns:220px 1fr;gap:1.5rem;align-items:start}
        @media(max-width:900px){.media-lib{grid-template-columns:1fr}}

        .media-lib-card{border:1px solid rgb(229 231 235);border-radius:.75rem;background:#fff;padding:1rem}

        .media-lib-folders{display:flex;flex-direction:column;gap:.15rem}
        .media-lib-folder-link{
            display:flex;align-items:center;gap:.4rem;padding:.4rem .5rem;border-radius:.5rem;
            font-size:.85rem;color:#374151;text-decoration:none;cursor:pointer;background:none;border:none;text-align:left;width:100%;
        }
        .media-lib-folder-link:hover{background:#f3f4f6}
        .media-lib-folder-link.active{background:#fef3c7;font-weight:600}

        .media-lib-drop{
            border:2px dashed rgb(209 213 219);border-radius:.75rem;padding:1.5rem;text-align:center;
            cursor:pointer;color:#6b7280;font-size:.85rem;transition:background .15s,border-color .15s;
        }
        .media-lib-drop.dragover{background:#fffbeb;border-color:#d9bb75}
        .media-lib-drop strong{color:#374151}

        .media-lib-toolbar{display:flex;flex-wrap:wrap;gap:.6rem;align-items:center;margin:1rem 0}
        .media-lib-toolbar input[type=search]{
            border:1px solid rgb(209 213 219);border-radius:.5rem;padding:.4rem .6rem;font-size:.85rem;min-width:220px;
        }
        .media-lib-toolbar select{border:1px solid rgb(209 213 219);border-radius:.5rem;padding:.4rem .6rem;font-size:.85rem}
        .media-lib-toolbar label{display:inline-flex;align-items:center;gap:.3rem;font-size:.8rem;color:#4b5563}

        .media-lib-breadcrumb{font-size:.8rem;color:#6b7280;margin-bottom:.5rem}
        .media-lib-breadcrumb button{background:none;border:none;color:#6b7280;cursor:pointer;padding:0;font-size:.8rem}
        .media-lib-breadcrumb button:hover{text-decoration:underline;color:#374151}

        .media-lib-subfolders{display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:1rem}
        .media-lib-subfolder{
            display:flex;align-items:center;gap:.35rem;border:1px solid rgb(229 231 235);border-radius:.5rem;
            padding:.3rem .3rem .3rem .6rem;font-size:.8rem;background:#f9fafb;
        }
        .media-lib-subfolder button.name{background:none;border:none;cursor:pointer;font-size:.8rem;color:#374151}
        .media-lib-subfolder .icon-btn{background:none;border:none;cursor:pointer;color:#9ca3af;padding:.15rem}
        .media-lib-subfolder .icon-btn:hover{color:#374151}

        .media-lib-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:.85rem}
        .media-lib-item{
            border:1px solid rgb(229 231 235);border-radius:.6rem;overflow:hidden;background:#fff;cursor:pointer;
            text-align:left;padding:0;position:relative;aspect-ratio:1/1;
        }
        .media-lib-item img{width:100%;height:100%;object-fit:cover;display:block}
        .media-lib-item .file-icon{display:flex;align-items:center;justify-content:center;height:100%;font-size:2rem;color:#9ca3af}
        .media-lib-item .name{
            position:absolute;left:0;right:0;bottom:0;background:rgba(0,0,0,.55);color:#fff;font-size:.68rem;
            padding:.25rem .4rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
        }
        .media-lib-item .warn-badge{
            position:absolute;top:.3rem;right:.3rem;background:#f59e0b;color:#fff;border-radius:9999px;
            width:1.25rem;height:1.25rem;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;
        }
        .media-lib-item .err-badge{
            position:absolute;top:.3rem;left:.3rem;background:#dc2626;color:#fff;border-radius:9999px;
            width:1.25rem;height:1.25rem;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;
        }
        .media-lib-error{background:#fef2f2;border:1px solid #fecaca;border-radius:.5rem;padding:.5rem .7rem;font-size:.78rem;color:#991b1b;margin-bottom:.6rem}
        .media-lib-empty{color:#9ca3af;font-size:.85rem;padding:2rem 0;text-align:center}

        .media-lib-overlay{position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:40}
        .media-lib-panel{
            position:fixed;top:0;right:0;bottom:0;width:100%;max-width:420px;background:#fff;z-index:41;
            overflow-y:auto;padding:1.5rem;box-shadow:-4px 0 16px rgba(0,0,0,.08);
        }
        .media-lib-panel h3{font-size:1rem;font-weight:700;margin-bottom:.25rem;word-break:break-word}
        .media-lib-panel .preview{width:100%;border-radius:.6rem;margin-bottom:1rem;background:#f3f4f6;max-height:260px;object-fit:contain}
        .media-lib-panel .meta{font-size:.8rem;color:#6b7280;margin-bottom:1rem}
        .media-lib-panel .field{margin-bottom:1rem}
        .media-lib-panel .field label{display:block;font-size:.75rem;font-weight:600;color:#374151;margin-bottom:.3rem}
        .media-lib-panel .field input[type=text],
        .media-lib-panel .field select{
            width:100%;border:1px solid rgb(209 213 219);border-radius:.5rem;padding:.4rem .6rem;font-size:.85rem;
        }
        .media-lib-warning{background:#fffbeb;border:1px solid #fde68a;border-radius:.5rem;padding:.5rem .7rem;font-size:.78rem;color:#92400e;margin-bottom:.4rem}
        .media-lib-info{background:#eff6ff;border:1px solid #bfdbfe;border-radius:.5rem;padding:.55rem .75rem;font-size:.8rem;color:#1e40af;margin-bottom:.85rem}
        .media-lib-orphan{background:#fff7ed;border:1px solid #fed7aa;border-radius:.5rem;padding:.5rem .7rem;font-size:.78rem;color:#9a3412;margin-bottom:.6rem}
        .media-lib-usage-list{font-size:.8rem;color:#92400e;margin:.4rem 0 0;padding-left:1.1rem}
        .media-lib-panel .actions{display:flex;gap:.5rem;flex-wrap:wrap;margin-top:1.25rem}
        .media-lib-close{position:absolute;top:1rem;right:1rem;background:none;border:none;cursor:pointer;font-size:1.1rem;color:#6b7280}
    </style>

    <div class="media-lib">
        {{-- ============ سایدبار: پوشه‌ها ============ --}}
        <div class="media-lib-card">
            <div class="media-lib-folders">
                <button type="button" class="media-lib-folder-link {{ is_null($currentFolderId) ? 'active' : '' }}" wire:click="openFolder(null)">
                    📁 All files
                </button>
                @foreach($this->rootFolders as $folder)
                    <button type="button" class="media-lib-folder-link {{ $currentFolderId === $folder->id ? 'active' : '' }}" wire:click="openFolder({{ $folder->id }})">
                        📁 {{ $folder->name }}
                    </button>
                @endforeach
            </div>
        </div>

        {{-- ============ محتوای اصلی ============ --}}
        <div>
            {{-- آپلود با درگ‌اند‌دراپ + انتخاب چندتایی --}}
            <div class="media-lib-drop" id="mediaDropzone">
                <div><strong>Drag &amp; drop files here</strong>, or click to choose files (multiple allowed)</div>
                <div style="margin-top:.35rem;font-size:.72rem;color:#9ca3af">Images up to 15 MB · video and other files up to 128 MB. Very large uploads also need the server's PHP upload limit raised to match.</div>
                <div wire:loading wire:target="uploads" style="margin-top:.4rem">Uploading…</div>
                <input type="file" id="mediaFileInput" multiple style="display:none">
            </div>

            {{-- نوار جست‌وجو و فیلترها --}}
            <div class="media-lib-toolbar">
                <input type="search" wire:model.live.debounce.400ms="search" placeholder="Search by filename…">

                <select wire:model.live="typeFilter">
                    <option value="all">All types</option>
                    <option value="image">Images</option>
                    <option value="video">Videos</option>
                    <option value="other">Other files</option>
                </select>

                <label><input type="checkbox" wire:model.live="onlyUnused"> Unused only</label>
                <label title="Files the system attached as a featured or hero image that nothing references anymore — safe to delete."><input type="checkbox" wire:model.live="onlyOrphaned"> Orphaned</label>
                <label><input type="checkbox" wire:model.live="onlyMissingAlt"> Missing ALT</label>
                <label><input type="checkbox" wire:model.live="onlyLarge"> Large files (&gt;500KB)</label>

                <x-filament::button size="sm" color="gray" wire:click="$set('showNewFolderForm', true)">
                    + New folder
                </x-filament::button>

                @if($this->imagesMissingWebpCount > 0)
                    <x-filament::button size="sm" color="warning" icon="heroicon-o-arrow-path"
                        wire:click="regenerateAllMissingWebp"
                        wire:confirm="Regenerate WebP for {{ $this->imagesMissingWebpCount }} image(s) that don't have one yet? This may take a moment."
                        wire:loading.attr="disabled" wire:target="regenerateAllMissingWebp">
                        <span wire:loading.remove wire:target="regenerateAllMissingWebp">Regenerate {{ $this->imagesMissingWebpCount }} missing WebP</span>
                        <span wire:loading wire:target="regenerateAllMissingWebp">Regenerating…</span>
                    </x-filament::button>
                @endif
            </div>

            @if($showNewFolderForm)
                <div class="media-lib-toolbar" style="margin-top:-.5rem">
                    <input type="text" wire:model="newFolderName" wire:keydown.enter="createFolder" placeholder="Folder name" autofocus>
                    <x-filament::button size="sm" wire:click="createFolder">Create</x-filament::button>
                    <x-filament::button size="sm" color="gray" wire:click="$set('showNewFolderForm', false)">Cancel</x-filament::button>
                </div>
            @endif

            @if($search === '')
                {{-- breadcrumb --}}
                @if($this->breadcrumbTrail)
                    <div class="media-lib-breadcrumb">
                        <button type="button" wire:click="openFolder(null)">Media Library</button>
                        @foreach($this->breadcrumbTrail as $crumb)
                            / <button type="button" wire:click="openFolder({{ $crumb->id }})">{{ $crumb->name }}</button>
                        @endforeach
                    </div>
                @endif

                {{-- زیرپوشه‌های پوشه‌ی جاری --}}
                @if($this->subfolders->isNotEmpty())
                    <div class="media-lib-subfolders">
                        @foreach($this->subfolders as $folder)
                            <div class="media-lib-subfolder">
                                @if($renamingFolderId === $folder->id)
                                    <input type="text" wire:model="renamingFolderName" wire:keydown.enter="saveFolderName" wire:blur="saveFolderName" style="font-size:.8rem;border:1px solid #d1d5db;border-radius:.3rem;padding:.1rem .3rem;width:120px" autofocus>
                                @else
                                    <button type="button" class="name" wire:click="openFolder({{ $folder->id }})">📁 {{ $folder->name }}</button>
                                    <button type="button" class="icon-btn" title="Rename" wire:click="startRenamingFolder({{ $folder->id }})">✎</button>
                                    <button type="button" class="icon-btn" title="Delete (must be empty)" wire:click="deleteFolder({{ $folder->id }})" wire:confirm="Delete this folder? Only works if it's empty.">🗑</button>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            @endif

            @if($onlyOrphaned)
                <div class="media-lib-info">Showing <strong>orphaned</strong> files — the system attached these as a featured or hero image, but nothing references them anymore (the image was replaced, a hero was regenerated, or an import was rolled back). They're safe to delete. Nothing is deleted automatically.</div>
            @endif

            {{-- شبکه‌ی رسانه --}}
            <div class="media-lib-grid" wire:loading.class="opacity-50" wire:target="search,typeFilter,onlyUnused,onlyOrphaned,onlyMissingAlt,onlyLarge,openFolder">
                @forelse($this->mediaItems as $item)
                    <button type="button" class="media-lib-item" wire:click="selectMedia({{ $item->id }})" title="{{ $item->original_name }}">
                        @if($item->type === 'image')
                            <img src="{{ $item->thumbnail_url }}" loading="lazy" alt="{{ $item->alt_text }}">
                        @elseif($item->type === 'video')
                            <div class="file-icon">🎬</div>
                        @else
                            <div class="file-icon">📄</div>
                        @endif

                        @if($item->processingFailed())
                            <span class="err-badge" title="Could not be processed — no optimized version was generated. It may be corrupt or in an unsupported format.">✕</span>
                        @endif

                        @if(count($item->warnings()))
                            <span class="warn-badge" title="{{ implode(' / ', $item->warnings()) }}">!</span>
                        @endif

                        <span class="name">{{ $item->original_name }}</span>
                    </button>
                @empty
                    <div class="media-lib-empty">No files here yet — drag some in above.</div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ============ پنل جزئیات ============ --}}
    @if($this->selectedMedia)
        @php($usages = $this->selectedMedia->usages())
        <div class="media-lib-overlay" wire:click="closeDetails"></div>
        <div class="media-lib-panel" wire:key="media-panel-{{ $this->selectedMedia->id }}">
            <button type="button" class="media-lib-close" wire:click="closeDetails">✕</button>

            @if($this->selectedMedia->processingFailed())
                <div class="media-lib-error">✕ This image could not be processed — no optimized (WebP) version was generated. It may be corrupt or in an unsupported format. Try replacing it with a valid image.</div>
            @endif

            @if($this->selectedMedia->type === 'image')
                <img class="preview" src="{{ $this->selectedMedia->webp_url ?? $this->selectedMedia->url }}" alt="{{ $this->selectedMedia->alt_text }}">
            @elseif($this->selectedMedia->type === 'video')
                {{-- نمایشِ نیتیوِ فایل — نه تولیدِ poster (آن فاز بعدی است)، فقط پخش‌کننده‌ی مرورگر --}}
                <video class="preview" controls preload="metadata" src="{{ $this->selectedMedia->url }}"></video>
            @endif

            <h3>{{ $this->selectedMedia->original_name }}</h3>
            <div class="meta">
                @if($this->selectedMedia->width)
                    {{ $this->selectedMedia->width }}×{{ $this->selectedMedia->height }}px ·
                @endif
                {{ $this->selectedMedia->human_size }}
                @if($this->selectedMedia->folder)
                    · in {{ $this->selectedMedia->folder->fullPath() }}
                @endif
            </div>

            @foreach($this->selectedMedia->warnings() as $warning)
                <div class="media-lib-warning">⚠ {{ $warning }}</div>
            @endforeach

            {{-- یتیم: از $usages که همین بالا محاسبه شده استفاده می‌کند + بررسیِ محضِ مسیر (بدونِ
                 کوئریِ اضافه)، پس هزینه‌ی اسکنِ دومی ندارد --}}
            @if(count($usages) === 0 && $this->selectedMedia->isInSystemAttachedDirectory())
                <div class="media-lib-orphan">🔗 Orphaned — this file was attached as a featured or hero image but nothing references it anymore. It's safe to delete.</div>
            @endif

            @if($this->selectedMedia->type === 'image')
                <div class="field">
                    <label for="mediaAltInput">ALT text (accessibility &amp; image SEO)</label>
                    <input type="text" id="mediaAltInput" x-ref="altInput" value="{{ $this->selectedMedia->alt_text }}" placeholder="Describe what's in this image…">
                    <div style="margin-top:.4rem">
                        <x-filament::button size="sm" wire:click="saveAltText($refs.altInput.value)">Save ALT text</x-filament::button>
                    </div>
                </div>
            @endif

            {{-- caption/description متادیتای عمومیِ Media است (نه فقط تصویر) — برای هر نوع فایل --}}
            <div class="field">
                <label for="mediaCaptionInput">Caption</label>
                <input type="text" id="mediaCaptionInput" x-ref="captionInput" value="{{ $this->selectedMedia->caption }}" placeholder="A short caption for this file…">
                <div style="margin-top:.4rem">
                    <x-filament::button size="sm" color="gray" wire:click="saveCaption($refs.captionInput.value)">Save caption</x-filament::button>
                </div>
            </div>

            <div class="field">
                <label for="mediaDescriptionInput">Description</label>
                <input type="text" id="mediaDescriptionInput" x-ref="descriptionInput" value="{{ $this->selectedMedia->description }}" placeholder="A longer description…">
                <div style="margin-top:.4rem">
                    <x-filament::button size="sm" color="gray" wire:click="saveDescription($refs.descriptionInput.value)">Save description</x-filament::button>
                </div>
            </div>

            @if($this->selectedMedia->type === 'image')
                <div class="field">
                    <label>WebP optimization</label>
                    @if($this->selectedMedia->webp_path)
                        <div style="font-size:.8rem;color:#15803d">✓ WebP generated — <code>{{ $this->selectedMedia->webp_path }}</code></div>
                        <div style="font-size:.8rem;color:#374151;margin-top:.35rem;line-height:1.7">
                            Original: <strong>{{ $this->selectedMedia->human_size }}</strong><br>
                            WebP: <strong>{{ $this->selectedMedia->webp_human_size }}</strong>
                            @php($saved = $this->selectedMedia->webp_savings_percent)
                            @if(! is_null($saved))
                                <br>
                                @if($saved >= 0)
                                    Saved: <strong style="color:#15803d">{{ $saved }}%</strong>
                                @else
                                    <span style="color:#b45309">WebP is {{ abs($saved) }}% larger (already-compressed image)</span>
                                @endif
                            @endif
                        </div>
                    @else
                        <div style="font-size:.8rem;color:#b45309">No WebP version yet. Click below to generate it — if it fails, you'll see the exact reason.</div>
                    @endif
                    <div style="margin-top:.4rem">
                        <x-filament::button size="sm" color="gray" icon="heroicon-o-arrow-path" wire:click="regenerateDerivatives({{ $this->selectedMedia->id }})" wire:loading.attr="disabled" wire:target="regenerateDerivatives">
                            Regenerate WebP
                        </x-filament::button>
                    </div>
                </div>
            @endif

            <div class="field">
                <label for="mediaFolderSelect">Folder</label>
                <select id="mediaFolderSelect" wire:change="moveSelectedToFolder($event.target.value)">
                    <option value="">— Root (no folder) —</option>
                    @foreach($this->allFolders as $folder)
                        <option value="{{ $folder['id'] }}" @selected($this->selectedMedia->folder_id === $folder['id'])>{{ $folder['label'] }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label>Used in</label>
                @if(count($usages))
                    <ul class="media-lib-usage-list">
                        @foreach($usages as $usage)
                            <li>{{ $usage['type'] }} — {{ $usage['label'] }} ({{ $usage['field'] }})</li>
                        @endforeach
                    </ul>
                @else
                    <div style="font-size:.8rem;color:#9ca3af">Not used anywhere on the site yet.</div>
                @endif
            </div>

            <div class="actions">
                <x-filament::button size="sm" color="gray" tag="a" :href="$this->selectedMedia->url" target="_blank">
                    Download original
                </x-filament::button>

                <x-filament::button size="sm" color="gray" id="mediaReplaceBtn">
                    Replace file…
                </x-filament::button>
                <input type="file" id="mediaReplaceInput" style="display:none">

                @if(count($usages) === 0)
                    <x-filament::button size="sm" color="danger" wire:click="deleteMedia({{ $this->selectedMedia->id }})" wire:confirm="Delete this file permanently? This cannot be undone.">
                        Delete
                    </x-filament::button>
                @else
                    <x-filament::button size="sm" color="gray" disabled>
                        Can't delete — in use
                    </x-filament::button>
                @endif
            </div>
        </div>
    @endif

    <script>
        (function () {
            function wireDropzone() {
                const zone = document.getElementById('mediaDropzone');
                const input = document.getElementById('mediaFileInput');
                if (!zone || !input) return;

                zone.onclick = () => input.click();

                input.onchange = () => {
                    if (input.files.length) {
                        @this.uploadMultiple('uploads', input.files, () => { input.value = ''; }, () => { input.value = ''; });
                    }
                };

                zone.ondragover = (e) => { e.preventDefault(); zone.classList.add('dragover'); };
                zone.ondragleave = () => zone.classList.remove('dragover');
                zone.ondrop = (e) => {
                    e.preventDefault();
                    zone.classList.remove('dragover');
                    if (e.dataTransfer.files.length) {
                        @this.uploadMultiple('uploads', e.dataTransfer.files);
                    }
                };
            }

            function wireReplace() {
                const btn = document.getElementById('mediaReplaceBtn');
                const input = document.getElementById('mediaReplaceInput');
                if (!btn || !input) return;

                btn.onclick = () => input.click();
                input.onchange = () => {
                    if (input.files.length) {
                        @this.upload('replaceFile', input.files[0], () => { input.value = ''; }, () => { input.value = ''; });
                    }
                };
            }

            function wireUp() {
                wireDropzone();
                wireReplace();
            }

            wireUp();
            document.addEventListener('livewire:navigated', wireUp);

            // پنل جزئیات به‌صورت شرطی رندر می‌شود، یعنی هر بار یک نود کاملا تازه‌ست — نه یک به‌روزرسانی‌ی
            // نود موجود — پس اتکا به یک هوک خاص Livewire شکننده است؛ MutationObserver مستقل از جزئیات
            // نسخه‌ی داخلی Livewire کار می‌کند
            const observer = new MutationObserver(() => wireUp());
            observer.observe(document.body, { childList: true, subtree: true });
        })();
    </script>
</x-filament-panels::page>
