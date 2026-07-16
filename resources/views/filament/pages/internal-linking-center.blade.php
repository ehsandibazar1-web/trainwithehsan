<x-filament-panels::page>
    <style>
        .ilc-tabs{display:flex;gap:.4rem;margin-bottom:1.25rem;border-bottom:1px solid rgb(229 231 235)}
        .ilc-tab{
            padding:.6rem 1rem;font-size:.85rem;font-weight:600;color:#6b7280;background:none;border:none;
            cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-1px;
        }
        .ilc-tab:hover{color:#374151}
        .ilc-tab.active{color:#b45309;border-bottom-color:#d9bb75}

        .ilc-center{display:grid;grid-template-columns:260px 1fr;gap:1.5rem;align-items:start}
        @media(max-width:900px){.ilc-center{grid-template-columns:1fr}}

        .ilc-summary{border:1px solid rgb(229 231 235);border-radius:.75rem;background:#fff;padding:1rem;margin-bottom:1rem}
        .ilc-summary .count{font-size:1.6rem;font-weight:700;color:#374151}
        .ilc-summary .label{font-size:.8rem;color:#6b7280}
        .ilc-summary-actions{display:flex;gap:.5rem;margin-top:.75rem;flex-wrap:wrap}

        .ilc-categories{border:1px solid rgb(229 231 235);border-radius:.75rem;background:#fff;padding:.5rem;display:flex;flex-direction:column;gap:.15rem}
        .ilc-cat-btn{
            display:flex;align-items:center;justify-content:space-between;gap:.5rem;padding:.55rem .65rem;border-radius:.5rem;
            font-size:.8rem;color:#374151;text-decoration:none;cursor:pointer;background:none;border:none;text-align:left;width:100%;
        }
        .ilc-cat-btn:hover{background:#f3f4f6}
        .ilc-cat-btn.active{background:#fef3c7;font-weight:600}
        .ilc-cat-badge{min-width:1.5rem;text-align:center;border-radius:9999px;padding:.05rem .4rem;font-size:.72rem;font-weight:700;background:#f3f4f6;color:#6b7280}
        .ilc-cat-badge.has-issues{background:#fee2e2;color:#b91c1c}
        .ilc-cat-badge.clean{background:#dcfce7;color:#166534}

        .ilc-toolbar{display:flex;flex-wrap:wrap;gap:.6rem;align-items:center;margin-bottom:.85rem}
        .ilc-toolbar input[type=search]{border:1px solid rgb(209 213 219);border-radius:.5rem;padding:.4rem .6rem;font-size:.85rem;min-width:220px}
        .ilc-toolbar select{border:1px solid rgb(209 213 219);border-radius:.5rem;padding:.4rem .6rem;font-size:.85rem}

        .ilc-note{background:#eff6ff;border:1px solid #bfdbfe;border-radius:.5rem;padding:.6rem .8rem;font-size:.8rem;color:#1e40af;margin-bottom:.85rem}

        table.ilc-table{width:100%;border-collapse:collapse;font-size:.83rem;background:#fff;border:1px solid rgb(229 231 235);border-radius:.75rem;overflow:hidden}
        table.ilc-table th{text-align:left;background:#f9fafb;padding:.6rem .75rem;font-size:.72rem;text-transform:uppercase;letter-spacing:.03em;color:#6b7280;border-bottom:1px solid rgb(229 231 235)}
        table.ilc-table td{padding:.6rem .75rem;border-bottom:1px solid rgb(243 244 246);vertical-align:top}
        table.ilc-table tr:last-child td{border-bottom:none}
        table.ilc-table .badge{display:inline-block;padding:.1rem .5rem;border-radius:9999px;font-size:.7rem;background:#f3f4f6;color:#4b5563}
        table.ilc-table .detail{color:#4b5563;white-space:pre-line}
        .ilc-empty{padding:2.5rem 0;text-align:center;color:#9ca3af;font-size:.85rem}

        .ilc-confidence{display:inline-block;min-width:2.5rem;text-align:center;border-radius:.4rem;padding:.1rem .4rem;font-size:.72rem;font-weight:700}
        .ilc-confidence.high{background:#dcfce7;color:#166534}
        .ilc-confidence.medium{background:#fef3c7;color:#92400e}
        .ilc-confidence.low{background:#f3f4f6;color:#6b7280}

        .ilc-bulkbar{display:flex;align-items:center;gap:.6rem;margin-bottom:.6rem;font-size:.8rem;color:#6b7280}

        .ilc-graph-wrap{border:1px solid rgb(229 231 235);border-radius:.75rem;background:#fff;padding:1rem;overflow:auto}
        .ilc-graph-legend{display:flex;gap:1rem;font-size:.75rem;color:#6b7280;margin-bottom:.6rem}
        .ilc-graph-legend span{display:inline-flex;align-items:center;gap:.3rem}
        .ilc-graph-legend i{width:9px;height:9px;border-radius:50%;display:inline-block}
        .ilc-node-article circle{fill:#d9bb75}
        .ilc-node-page circle{fill:#60a5fa}
        .ilc-node-orphan circle{stroke:#dc2626;stroke-width:2}
        .ilc-node text{font-size:9px;fill:#374151}
        .ilc-edge{stroke:#d1d5db;stroke-width:1;marker-end:url(#ilc-arrow)}
    </style>

    <div class="ilc-tabs">
        <button type="button" class="ilc-tab {{ $activeTab === 'dashboard' ? 'active' : '' }}" wire:click="setTab('dashboard')">Dashboard</button>
        <button type="button" class="ilc-tab {{ $activeTab === 'suggestions' ? 'active' : '' }}" wire:click="setTab('suggestions')">Suggestions ({{ $this->pendingSuggestions->count() }})</button>
        <button type="button" class="ilc-tab {{ $activeTab === 'graph' ? 'active' : '' }}" wire:click="setTab('graph')">Link Graph</button>
    </div>

    @if($activeTab === 'dashboard')
        <div class="ilc-center">
            <div>
                <div class="ilc-summary">
                    <div class="count">{{ $this->totalIssues }}</div>
                    <div class="label">total issue{{ $this->totalIssues === 1 ? '' : 's' }} found</div>
                    <div class="ilc-summary-actions">
                        <x-filament::button size="sm" color="gray" wire:click="runAudit" icon="heroicon-o-arrow-path">
                            Run audit
                        </x-filament::button>
                        <x-filament::button size="sm" color="gray" wire:click="exportFullReportCsv" icon="heroicon-o-arrow-down-tray">
                            Export full report
                        </x-filament::button>
                    </div>
                </div>

                <div class="ilc-categories">
                    @foreach(\App\Filament\Pages\InternalLinkingCenter::CATEGORIES as $key => $label)
                        @php($count = $this->categoryCounts[$key] ?? 0)
                        <button type="button" class="ilc-cat-btn {{ $activeCategory === $key ? 'active' : '' }}" wire:click="setCategory('{{ $key }}')">
                            <span>{{ $label }}</span>
                            <span class="ilc-cat-badge {{ $count > 0 ? 'has-issues' : 'clean' }}">{{ $count }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            <div>
                @if($activeCategory === 'redirect_chains')
                    <div class="ilc-note">
                        This site has no URL redirect mechanism (no redirects table or middleware), so redirect chains cannot occur here. If a slug changes, old links become "Broken Internal Links" instead of a redirect chain — see that category.
                    </div>
                @endif

                @if($activeCategory === 'broken_external_links')
                    <div class="ilc-note">
                        External links aren't checked automatically (it makes real HTTP requests to other websites) — click "Scan external links" to run it.
                        <div style="margin-top:.5rem">
                            <x-filament::button size="sm" wire:click="scanExternalLinks" icon="heroicon-o-globe-alt">
                                Scan external links
                            </x-filament::button>
                        </div>
                    </div>
                @endif

                @if($activeCategory === 'weak_internal_linking')
                    <div class="ilc-note">Published content with at least one inbound link, but fewer than recommended. Not orphaned — just under-linked.</div>
                @endif

                <div class="ilc-toolbar">
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

                <table class="ilc-table">
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
                            <tr wire:key="ilc-finding-{{ $activeCategory }}-{{ $loop->index }}">
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
                                    <div class="ilc-empty">
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
    @endif

    @if($activeTab === 'suggestions')
        <div class="ilc-note">
            Rule-based suggestions (keyword overlap, category match, wording similarity) for content with few inbound links — not AI/machine-learning, so every suggestion states exactly why it was made. Approving a suggestion appends a real link (with the recommended anchor text) to the end of the source article/page — it never rewrites existing content.
        </div>

        <div class="ilc-toolbar">
            <select wire:model.live="suggestionLocaleFilter">
                <option value="all">All languages</option>
                <option value="en">English</option>
                <option value="tr">Türkçe</option>
            </select>

            <select wire:model.live="suggestionMinConfidence">
                <option value="0">Any confidence</option>
                <option value="50">50%+ confidence</option>
                <option value="70">70%+ confidence</option>
            </select>

            <x-filament::button size="sm" wire:click="generateSuggestions" icon="heroicon-o-sparkles">
                Generate suggestions
            </x-filament::button>

            <x-filament::button size="sm" color="gray" wire:click="exportSuggestionsCsv" icon="heroicon-o-arrow-down-tray">
                Export (CSV)
            </x-filament::button>
        </div>

        @if(count($selectedSuggestionIds))
            <div class="ilc-bulkbar">
                <span>{{ count($selectedSuggestionIds) }} selected</span>
                <x-filament::button size="xs" wire:click="approveSelected" wire:confirm="Add these links to their source articles/pages?">
                    Approve selected
                </x-filament::button>
                <x-filament::button size="xs" color="gray" wire:click="dismissSelected">
                    Dismiss selected
                </x-filament::button>
            </div>
        @endif

        <table class="ilc-table">
            <thead>
                <tr>
                    <th style="width:30px"></th>
                    <th style="width:60px">Lang</th>
                    <th>Source → Target</th>
                    <th style="width:70px">Confidence</th>
                    <th>Anchor &amp; reason</th>
                    <th style="width:170px">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($this->pendingSuggestions as $s)
                    <tr wire:key="ilc-suggestion-{{ $s['id'] }}">
                        <td><input type="checkbox" value="{{ $s['id'] }}" wire:model.live="selectedSuggestionIds"></td>
                        <td>{{ strtoupper($s['locale']) }}</td>
                        <td>{{ $s['source_label'] }} → {{ $s['target_label'] }}</td>
                        <td>
                            @php($level = $s['confidence'] >= 70 ? 'high' : ($s['confidence'] >= 50 ? 'medium' : 'low'))
                            <span class="ilc-confidence {{ $level }}">{{ $s['confidence'] }}%</span>
                        </td>
                        <td class="detail">"{{ $s['anchor'] }}" — {{ $s['reason'] }}</td>
                        <td>
                            <x-filament::button size="xs" wire:click="approveSuggestion({{ $s['id'] }})">
                                Approve
                            </x-filament::button>
                            <x-filament::button size="xs" color="gray" wire:click="dismissSuggestion({{ $s['id'] }})">
                                Dismiss
                            </x-filament::button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            <div class="ilc-empty">No pending suggestions — click "Generate suggestions" above.</div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @endif

    @if($activeTab === 'graph')
        @php($graphData = $this->graphData)
        <div class="ilc-toolbar">
            <select wire:model.live="graphLocaleFilter">
                <option value="all">All languages</option>
                <option value="en">English</option>
                <option value="tr">Türkçe</option>
            </select>

            <select wire:model.live="graphTypeFilter">
                <option value="all">All types</option>
                <option value="Article">Articles</option>
                <option value="Page">Pages</option>
            </select>

            <select wire:model.live="graphCategoryFilter">
                <option value="all">All categories</option>
                @foreach($this->graphCategories as $cat)
                    <option value="{{ $cat }}">{{ $cat }}</option>
                @endforeach
            </select>
        </div>

        <div class="ilc-graph-legend">
            <span><i style="background:#d9bb75"></i> Article</span>
            <span><i style="background:#60a5fa"></i> Page</span>
            <span><i style="background:#fff;border:2px solid #dc2626"></i> No inbound links (orphan)</span>
        </div>

        <div class="ilc-graph-wrap">
            @if($graphData['nodes']->isEmpty())
                <div class="ilc-empty">No content matches these filters.</div>
            @else
                <svg viewBox="0 0 600 600" width="100%" height="600" style="max-width:700px">
                    <defs>
                        <marker id="ilc-arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
                            <path d="M 0 0 L 10 5 L 0 10 z" fill="#d1d5db"></path>
                        </marker>
                    </defs>

                    @foreach($graphData['edges'] as $edge)
                        @php($from = $graphData['nodes'][$edge['from']] ?? null)
                        @php($to = $graphData['nodes'][$edge['to']] ?? null)
                        @if($from && $to)
                            <line class="ilc-edge" x1="{{ $from['x'] }}" y1="{{ $from['y'] }}" x2="{{ $to['x'] }}" y2="{{ $to['y'] }}"></line>
                        @endif
                    @endforeach

                    @foreach($graphData['nodes'] as $node)
                        <a href="{{ $node['edit_url'] }}" target="_blank">
                            <g class="ilc-node {{ $node['model'] === 'Article' ? 'ilc-node-article' : 'ilc-node-page' }} {{ $node['inbound'] === 0 ? 'ilc-node-orphan' : '' }}">
                                <title>{{ $node['title'] }} ({{ strtoupper($node['locale']) }}) — {{ $node['inbound'] }} in / {{ $node['outbound'] }} out</title>
                                <circle cx="{{ $node['x'] }}" cy="{{ $node['y'] }}" r="{{ min(14, 5 + $node['inbound']) }}"></circle>
                                <text x="{{ $node['x'] }}" y="{{ $node['y'] - 10 }}" text-anchor="middle">{{ \Illuminate\Support\Str::limit($node['title'], 18) }}</text>
                            </g>
                        </a>
                    @endforeach
                </svg>
            @endif
        </div>
    @endif
</x-filament-panels::page>
