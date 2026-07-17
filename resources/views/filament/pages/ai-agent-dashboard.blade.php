<x-filament-panels::page>
    @if($this->isPolling)
        <div wire:poll.5s></div>
    @endif

    <style>
        .agent-dash{display:grid;grid-template-columns:260px 1fr;gap:1.5rem;align-items:start}
        @media(max-width:900px){.agent-dash{grid-template-columns:1fr}}

        .agent-summary{border:1px solid rgb(229 231 235);border-radius:.75rem;background:#fff;padding:1rem;margin-bottom:1rem}
        .agent-summary-stats{display:flex;gap:1.25rem;flex-wrap:wrap}
        .agent-summary-stats .count{font-size:1.6rem;font-weight:700;color:#374151}
        .agent-summary-stats .label{font-size:.8rem;color:#6b7280}
        .agent-summary-actions{display:flex;gap:.5rem;margin-top:.75rem;flex-wrap:wrap;align-items:center}
        .agent-run-note{font-size:.78rem;color:#6b7280;margin-top:.5rem}
        .agent-run-note.running{color:#92400e}

        .agent-categories{border:1px solid rgb(229 231 235);border-radius:.75rem;background:#fff;padding:.5rem;display:flex;flex-direction:column;gap:.15rem}
        .agent-cat-btn{
            display:flex;align-items:center;justify-content:space-between;gap:.5rem;padding:.55rem .65rem;border-radius:.5rem;
            font-size:.82rem;color:#374151;text-decoration:none;cursor:pointer;background:none;border:none;text-align:left;width:100%;
        }
        .agent-cat-btn:hover{background:#f3f4f6}
        .agent-cat-btn.active{background:#fef3c7;font-weight:600}
        .agent-cat-badge{min-width:1.5rem;text-align:center;border-radius:9999px;padding:.05rem .4rem;font-size:.72rem;font-weight:700;background:#f3f4f6;color:#6b7280}
        .agent-cat-badge.has-issues{background:#fee2e2;color:#b91c1c}
        .agent-cat-badge.clean{background:#dcfce7;color:#166534}

        .agent-toolbar{display:flex;flex-wrap:wrap;gap:.6rem;align-items:center;margin-bottom:.85rem}
        .agent-toolbar input[type=search]{border:1px solid rgb(209 213 219);border-radius:.5rem;padding:.4rem .6rem;font-size:.85rem;min-width:220px}
        .agent-toolbar select{border:1px solid rgb(209 213 219);border-radius:.5rem;padding:.4rem .6rem;font-size:.85rem}

        .agent-cards{display:flex;flex-direction:column;gap:.75rem}
        .agent-card{border:1px solid rgb(229 231 235);border-radius:.75rem;background:#fff;padding:.9rem 1rem}
        .agent-card.severity-warning{border-color:#fde68a;background:#fffbeb}
        .agent-card-head{display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;flex-wrap:wrap}
        .agent-card-title{font-weight:600;color:#1f2937;font-size:.9rem}
        .agent-card-detail{color:#4b5563;font-size:.83rem;margin-top:.35rem;white-space:pre-line}
        .agent-card-meta{display:flex;gap:.4rem;flex-wrap:wrap;margin-top:.5rem}
        .agent-badge{display:inline-block;padding:.1rem .5rem;border-radius:9999px;font-size:.7rem;background:#f3f4f6;color:#4b5563}
        .agent-badge.status-applied{background:#dcfce7;color:#166534}
        .agent-badge.status-rejected{background:#fee2e2;color:#b91c1c}
        .agent-card-actions{display:flex;gap:.4rem;flex-wrap:wrap;margin-top:.65rem}
        .agent-fix-note{font-size:.76rem;color:#9ca3af;margin-top:.5rem}
        .agent-empty{padding:2.5rem 0;text-align:center;color:#9ca3af;font-size:.85rem}
    </style>

    <div class="agent-dash">
        <div>
            <div class="agent-summary">
                <div class="agent-summary-stats">
                    <div>
                        <div class="count">{{ $this->totalPending }}</div>
                        <div class="label">pending</div>
                    </div>
                    <div>
                        <div class="count">{{ $this->totalApplied }}</div>
                        <div class="label">applied</div>
                    </div>
                    <div>
                        <div class="count">{{ $this->totalRejected }}</div>
                        <div class="label">rejected</div>
                    </div>
                </div>
                <div class="agent-summary-actions">
                    <x-filament::button size="sm" wire:click="runAuditNow" icon="heroicon-o-arrow-path" :disabled="$this->isAuditRunning">
                        {{ $this->isAuditRunning ? 'Audit running…' : 'Run audit now' }}
                    </x-filament::button>
                </div>
                @if($this->latestRun)
                    <div class="agent-run-note {{ $this->isAuditRunning ? 'running' : '' }}">
                        Last audit: {{ $this->latestRun->status }}
                        @if($this->latestRun->finished_at)
                            ({{ $this->latestRun->finished_at->diffForHumans() }}, {{ $this->latestRun->new_count }} new, {{ $this->latestRun->resolved_count }} resolved)
                        @endif
                        — a full audit also runs automatically every week.
                    </div>
                @else
                    <div class="agent-run-note">No audit has run yet — click "Run audit now" to scan the site for the first time.</div>
                @endif
            </div>

            <div class="agent-categories">
                @foreach(\App\Filament\Pages\AiAgentDashboard::CATEGORIES as $key => $label)
                    @php($count = $this->categoryCounts[$key] ?? 0)
                    <button type="button" class="agent-cat-btn {{ $activeCategory === $key ? 'active' : '' }}" wire:click="setCategory('{{ $key }}')">
                        <span>{{ $label }}</span>
                        <span class="agent-cat-badge {{ $count > 0 ? 'has-issues' : 'clean' }}">{{ $count }}</span>
                    </button>
                @endforeach
            </div>
        </div>

        <div>
            <div class="agent-toolbar">
                <input type="search" wire:model.live.debounce.400ms="search" placeholder="Search this list…">

                <select wire:model.live="statusFilter">
                    <option value="pending">Pending</option>
                    <option value="applied">Applied</option>
                    <option value="rejected">Rejected</option>
                    <option value="all">All</option>
                </select>

                <select wire:model.live="localeFilter">
                    <option value="all">All languages</option>
                    <option value="en">English</option>
                    <option value="tr">Türkçe</option>
                </select>
            </div>

            <div class="agent-cards">
                @forelse($this->findings as $recommendation)
                    <div class="agent-card severity-{{ $recommendation->severity }}" wire:key="rec-{{ $recommendation->id }}">
                        <div class="agent-card-head">
                            <div class="agent-card-title">{{ $recommendation->title }}</div>
                            <span class="agent-badge status-{{ $recommendation->status }}">{{ ucfirst($recommendation->status) }}</span>
                        </div>

                        <div class="agent-card-detail">{{ $recommendation->detail }}</div>

                        <div class="agent-card-meta">
                            @if($recommendation->content_type)
                                <span class="agent-badge">{{ $recommendation->content_type }}</span>
                            @endif
                            @if($recommendation->locale)
                                <span class="agent-badge">{{ strtoupper($recommendation->locale) }}</span>
                            @endif
                        </div>

                        <div class="agent-card-actions">
                            @if($recommendation->edit_url)
                                <x-filament::button size="xs" color="gray" tag="a" :href="$recommendation->edit_url" target="_blank">
                                    Review
                                </x-filament::button>
                            @endif
                            @if($recommendation->related_edit_url)
                                <x-filament::button size="xs" color="gray" tag="a" :href="$recommendation->related_edit_url" target="_blank">
                                    Review related
                                </x-filament::button>
                            @endif

                            @if($recommendation->status === 'pending')
                                @if($recommendation->isFixable())
                                    @php($generation = $recommendation->generation)
                                    @if(! $generation)
                                        <x-filament::button size="xs" wire:click="queueFix({{ $recommendation->id }})" icon="heroicon-o-sparkles">
                                            Preview fix
                                        </x-filament::button>
                                    @elseif(in_array($generation->status, ['queued', 'processing']))
                                        <x-filament::button size="xs" color="gray" disabled icon="heroicon-o-clock">
                                            Generating…
                                        </x-filament::button>
                                    @elseif($generation->status === 'completed')
                                        @if($recommendation->fix_type !== 'translate' && is_string($generation->result))
                                            <div class="agent-fix-note">Suggested: "{{ \Illuminate\Support\Str::limit($generation->result, 160) }}"</div>
                                        @elseif($recommendation->fix_type === 'translate' && is_array($generation->result))
                                            <div class="agent-fix-note">Draft created: {{ $generation->result['title'] ?? '' }}</div>
                                        @endif
                                        <x-filament::button size="xs" color="success" wire:click="approveFix({{ $recommendation->id }})" icon="heroicon-o-check">
                                            Approve
                                        </x-filament::button>
                                    @else
                                        <div class="agent-fix-note">Generation failed: {{ $generation->error }}</div>
                                        <x-filament::button size="xs" wire:click="queueFix({{ $recommendation->id }})" icon="heroicon-o-arrow-path">
                                            Retry
                                        </x-filament::button>
                                    @endif
                                @else
                                    <div class="agent-fix-note">No automatic fix — this needs an editorial decision.</div>
                                @endif

                                <x-filament::button size="xs" color="danger" wire:click="rejectFix({{ $recommendation->id }})" icon="heroicon-o-x-mark">
                                    Reject
                                </x-filament::button>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="agent-empty">Nothing here — run an audit, or nothing matches the current filters.</div>
                @endforelse
            </div>
        </div>
    </div>
</x-filament-panels::page>
