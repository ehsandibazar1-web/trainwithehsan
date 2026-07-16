<x-filament-panels::page>
    <style>
        .seo-center{display:grid;grid-template-columns:260px 1fr;gap:1.5rem;align-items:start}
        @media(max-width:900px){.seo-center{grid-template-columns:1fr}}

        .seo-summary{border:1px solid rgb(229 231 235);border-radius:.75rem;background:#fff;padding:1rem;margin-bottom:1rem}
        .seo-summary .count{font-size:1.6rem;font-weight:700;color:#374151}
        .seo-summary .label{font-size:.8rem;color:#6b7280}
        .seo-summary-actions{display:flex;gap:.5rem;margin-top:.75rem;flex-wrap:wrap}

        .seo-categories{border:1px solid rgb(229 231 235);border-radius:.75rem;background:#fff;padding:.5rem;display:flex;flex-direction:column;gap:.15rem}
        .seo-cat-btn{
            display:flex;align-items:center;justify-content:space-between;gap:.5rem;padding:.55rem .65rem;border-radius:.5rem;
            font-size:.82rem;color:#374151;text-decoration:none;cursor:pointer;background:none;border:none;text-align:left;width:100%;
        }
        .seo-cat-btn:hover{background:#f3f4f6}
        .seo-cat-btn.active{background:#fef3c7;font-weight:600}
        .seo-cat-badge{
            min-width:1.5rem;text-align:center;border-radius:9999px;padding:.05rem .4rem;font-size:.72rem;font-weight:700;
            background:#f3f4f6;color:#6b7280;
        }
        .seo-cat-badge.has-issues{background:#fee2e2;color:#b91c1c}
        .seo-cat-badge.clean{background:#dcfce7;color:#166534}

        .seo-toolbar{display:flex;flex-wrap:wrap;gap:.6rem;align-items:center;margin-bottom:.85rem}
        .seo-toolbar input[type=search]{border:1px solid rgb(209 213 219);border-radius:.5rem;padding:.4rem .6rem;font-size:.85rem;min-width:220px}
        .seo-toolbar select{border:1px solid rgb(209 213 219);border-radius:.5rem;padding:.4rem .6rem;font-size:.85rem}

        .seo-category-note{background:#eff6ff;border:1px solid #bfdbfe;border-radius:.5rem;padding:.6rem .8rem;font-size:.8rem;color:#1e40af;margin-bottom:.85rem}
        .seo-category-note.warn{background:#fffbeb;border-color:#fde68a;color:#92400e}

        table.seo-findings{width:100%;border-collapse:collapse;font-size:.83rem;background:#fff;border:1px solid rgb(229 231 235);border-radius:.75rem;overflow:hidden}
        table.seo-findings th{text-align:left;background:#f9fafb;padding:.6rem .75rem;font-size:.72rem;text-transform:uppercase;letter-spacing:.03em;color:#6b7280;border-bottom:1px solid rgb(229 231 235)}
        table.seo-findings td{padding:.6rem .75rem;border-bottom:1px solid rgb(243 244 246);vertical-align:top}
        table.seo-findings tr:last-child td{border-bottom:none}
        table.seo-findings .badge{display:inline-block;padding:.1rem .5rem;border-radius:9999px;font-size:.7rem;background:#f3f4f6;color:#4b5563}
        table.seo-findings .detail{color:#4b5563;white-space:pre-line}
        .seo-empty{padding:2.5rem 0;text-align:center;color:#9ca3af;font-size:.85rem}
    </style>

    <div class="seo-center">
        <div>
            <div class="seo-summary">
                <div class="count">{{ $this->totalIssues }}</div>
                <div class="label">total issue{{ $this->totalIssues === 1 ? '' : 's' }} found</div>
                <div class="seo-summary-actions">
                    <x-filament::button size="sm" color="gray" wire:click="runAudit" icon="heroicon-o-arrow-path">
                        Run audit
                    </x-filament::button>
                    <x-filament::button size="sm" color="gray" wire:click="exportFullReportCsv" icon="heroicon-o-arrow-down-tray">
                        Export full report
                    </x-filament::button>
                </div>
            </div>

            <div class="seo-categories">
                @foreach(\App\Filament\Pages\SeoCenter::CATEGORIES as $key => $label)
                    @php($count = $this->categoryCounts[$key] ?? 0)
                    <button type="button" class="seo-cat-btn {{ $activeCategory === $key ? 'active' : '' }}" wire:click="setCategory('{{ $key }}')">
                        <span>{{ $label }}</span>
                        <span class="seo-cat-badge {{ $count > 0 ? 'has-issues' : 'clean' }}">{{ $count }}</span>
                    </button>
                @endforeach
            </div>
        </div>

        <div>
            @if($activeCategory === 'missing_canonicals')
                <div class="seo-category-note">
                    Every page in this app gets an automatic canonical URL from the layout
                    (<code>@yield('canonical', url()->current())</code> in <code>master.blade.php</code>) — so this is expected to stay at 0. It's kept here so a future change that removes that fallback gets caught immediately.
                </div>
            @endif

            @if($activeCategory === 'missing_schema')
                <div class="seo-category-note">
                    Articles, the homepage, and the About page already emit JSON-LD. Standalone pages and the blog index template don't — those are template-level gaps, not something fixable by editing a single record.
                </div>
            @endif

            @if($activeCategory === 'broken_external_links')
                <div class="seo-category-note {{ $hasScannedExternalLinks ? '' : 'warn' }}">
                    @if($hasScannedExternalLinks)
                        Last scan checked every external link found in article/page bodies, the menu, and the footer.
                    @else
                        External links aren't checked automatically (it makes real HTTP requests to other websites) — click "Scan external links" to run it.
                    @endif
                    <div style="margin-top:.5rem">
                        <x-filament::button size="sm" wire:click="scanExternalLinks" icon="heroicon-o-globe-alt">
                            Scan external links
                        </x-filament::button>
                    </div>
                </div>
            @endif

            <div class="seo-toolbar">
                <input type="search" wire:model.live.debounce.400ms="search" placeholder="Search this list…">

                <select wire:model.live="localeFilter">
                    <option value="all">All languages</option>
                    <option value="en">English</option>
                    <option value="tr">Türkçe</option>
                </select>

                @if(count($this->availableTypes) > 1)
                    <select wire:model.live="typeFilter">
                        <option value="all">All types</option>
                        @foreach($this->availableTypes as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                @endif

                <x-filament::button size="sm" color="gray" wire:click="exportCategoryCsv" icon="heroicon-o-arrow-down-tray">
                    Export this view (CSV)
                </x-filament::button>
            </div>

            <table class="seo-findings">
                <thead>
                    <tr>
                        <th style="width:110px">Type</th>
                        <th style="width:60px">Lang</th>
                        <th>Item</th>
                        <th>Issue</th>
                        <th style="width:90px">Fix it</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->filteredFindings as $finding)
                        <tr wire:key="finding-{{ $activeCategory }}-{{ $loop->index }}">
                            <td><span class="badge">{{ $finding['type'] }}</span></td>
                            <td>{{ $finding['locale'] ? strtoupper($finding['locale']) : '—' }}</td>
                            <td>{{ $finding['title'] }}</td>
                            <td class="detail">{{ $finding['detail'] }}</td>
                            <td>
                                @if($finding['edit_url'])
                                    <x-filament::button size="xs" color="gray" tag="a" :href="$finding['edit_url']" target="_blank">
                                        Edit
                                    </x-filament::button>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="seo-empty">
                                    @if($activeCategory === 'broken_external_links' && ! $hasScannedExternalLinks)
                                        Not scanned yet — click "Scan external links" above.
                                    @else
                                        No issues found in this category.
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
