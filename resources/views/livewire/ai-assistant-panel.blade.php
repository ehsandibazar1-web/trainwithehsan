<div>
    <style>
        .ai-ca-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;flex-wrap:wrap;gap:.5rem}
        .ai-ca-header .title{font-size:.9rem;color:#6b7280}
        .ai-ca-header .title strong{color:#111827}

        .ai-ca-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1rem}
        .ai-ca-card{border:1px solid rgb(229 231 235);border-radius:.75rem;background:#fff;padding:1rem}
        .ai-ca-card h3{font-size:.85rem;font-weight:600;color:#111827;margin:0 0 .4rem}

        .ai-ca-current{font-size:.8rem;color:#4b5563;background:#f9fafb;border:1px solid rgb(243 244 246);border-radius:.5rem;padding:.5rem .6rem;margin-bottom:.6rem;max-height:6rem;overflow-y:auto;white-space:pre-line}
        .ai-ca-current.empty{color:#9ca3af;font-style:italic}

        .ai-ca-modes{display:flex;flex-wrap:wrap;gap:.35rem;margin-bottom:.6rem}
        .ai-ca-mode-btn{font-size:.72rem;padding:.3rem .55rem;border-radius:9999px;border:1px solid rgb(209 213 219);background:#fff;color:#374151;cursor:pointer}
        .ai-ca-mode-btn:hover{background:#f3f4f6}

        .ai-ca-status{font-size:.75rem;color:#6b7280;margin-bottom:.5rem}
        .ai-ca-status.processing{color:#b45309}
        .ai-ca-status.failed{color:#b91c1c}

        .ai-ca-preview{background:#eff6ff;border:1px solid #bfdbfe;border-radius:.5rem;padding:.6rem .7rem;font-size:.8rem;color:#1e3a8a;margin-bottom:.5rem;white-space:pre-line}
        .ai-ca-preview ul{margin:.25rem 0 0 1.1rem;padding:0}
        .ai-ca-preview .qa{margin-bottom:.4rem}
        .ai-ca-preview .qa strong{display:block}

        .ai-ca-actions{display:flex;gap:.4rem;margin-bottom:.6rem}

        .ai-ca-history{font-size:.72rem;color:#9ca3af}
        .ai-ca-history-item{display:flex;justify-content:space-between;align-items:center;padding:.2rem 0;border-top:1px solid rgb(243 244 246)}

        .ai-ca-tabs{display:flex;gap:.4rem;margin-bottom:1rem;border-bottom:1px solid rgb(229 231 235)}
        .ai-ca-tab-btn{padding:.5rem .9rem;font-size:.82rem;color:#6b7280;background:none;border:none;border-bottom:2px solid transparent;cursor:pointer}
        .ai-ca-tab-btn.active{color:#111827;font-weight:600;border-bottom-color:#d9bb75}

        .ai-ca-findings{border:1px solid rgb(229 231 235);border-radius:.75rem;background:#fff;overflow:hidden;margin-bottom:1rem}
        .ai-ca-finding{display:flex;gap:.6rem;align-items:flex-start;padding:.6rem .8rem;border-bottom:1px solid rgb(243 244 246);font-size:.82rem}
        .ai-ca-finding:last-child{border-bottom:none}
        .ai-ca-finding .badge{flex-shrink:0;padding:.05rem .5rem;border-radius:9999px;font-size:.68rem;font-weight:600;text-transform:uppercase}
        .ai-ca-finding .badge.warning{background:#fee2e2;color:#b91c1c}
        .ai-ca-finding .badge.notice{background:#fef3c7;color:#92400e}
        .ai-ca-findings-empty{padding:1.5rem;text-align:center;color:#9ca3af;font-size:.85rem}

        .ai-ca-diff{font-size:.8rem;line-height:1.5;background:#fff;border:1px solid rgb(229 231 235);border-radius:.5rem;padding:.5rem .6rem;margin-bottom:.5rem;max-height:8rem;overflow-y:auto}
        .ai-ca-diff del{background:#fee2e2;color:#991b1b;text-decoration:line-through}
        .ai-ca-diff ins{background:#dcfce7;color:#166534;text-decoration:none}

        .ai-ca-score{border:1px solid rgb(229 231 235);border-radius:.75rem;background:#fff;padding:1rem;margin-bottom:1rem}
        .ai-ca-score-head{display:flex;align-items:center;gap:.75rem;margin-bottom:.75rem}
        .ai-ca-score-overall{font-size:1.6rem;font-weight:700;color:#111827;flex-shrink:0}
        .ai-ca-score-overall .max{font-size:.9rem;font-weight:400;color:#9ca3af}
        .ai-ca-score-label{font-size:.78rem;color:#6b7280}
        .ai-ca-score-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:.5rem}
        .ai-ca-score-cat{border:1px solid rgb(243 244 246);border-radius:.5rem;padding:.5rem .6rem}
        .ai-ca-score-cat .name{font-size:.72rem;color:#6b7280;margin-bottom:.15rem}
        .ai-ca-score-cat .num{font-size:1.1rem;font-weight:600}
        .ai-ca-score-cat .num.good{color:#166534}
        .ai-ca-score-cat .num.mid{color:#92400e}
        .ai-ca-score-cat .num.bad{color:#b91c1c}
        .ai-ca-score-cat .issues{margin:.3rem 0 0 1rem;padding:0;font-size:.68rem;color:#9ca3af}
    </style>

    <div @if ($this->isPolling) wire:poll.3s="$refresh" @endif>
        <div class="ai-ca-header">
            <div class="title">AI Assistant for <strong>{{ $record->title }}</strong> ({{ strtoupper($record->locale) }})</div>
            @if ($standalone)
                <x-filament::button size="sm" color="gray" tag="a" :href="$this->editUrl()" icon="heroicon-o-arrow-left">
                    Back to editing
                </x-filament::button>
            @endif
        </div>

        @if ($this->isPolling)
            <div class="ai-ca-status processing" style="margin-bottom:1rem">Generating… this page refreshes automatically.</div>
        @endif

        @php($scoreCard = $this->scoreCard)
        <div class="ai-ca-score">
            <div class="ai-ca-score-head">
                <div class="ai-ca-score-overall">{{ $scoreCard['overall'] }}<span class="max">/100</span></div>
                <div class="ai-ca-score-label">AI Health Report — overall score is the average of the six categories below. Hover a category for what's missing.</div>
            </div>
            <div class="ai-ca-score-grid">
                @foreach ($scoreCard['categories'] as $category)
                    @php($tier = $category['score'] >= 80 ? 'good' : ($category['score'] >= 50 ? 'mid' : 'bad'))
                    <div class="ai-ca-score-cat">
                        <div class="name">{{ $category['label'] }}</div>
                        <div class="num {{ $tier }}">{{ $category['score'] }}</div>
                        @if ($category['issues'])
                            <ul class="issues">
                                @foreach ($category['issues'] as $issue)
                                    <li>{{ $issue }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        <div class="ai-ca-tabs">
            <button type="button" class="ai-ca-tab-btn {{ $activeTab === 'generate' ? 'active' : '' }}" wire:click="setTab('generate')">Generate</button>
            <button type="button" class="ai-ca-tab-btn {{ $activeTab === 'review' ? 'active' : '' }}" wire:click="setTab('review')">Content Review</button>
        </div>

        @if ($activeTab === 'review')
            <div class="ai-ca-findings">
                @forelse ($this->reviewFindings as $finding)
                    <div class="ai-ca-finding">
                        <span class="badge {{ $finding['severity'] }}">{{ $finding['severity'] }}</span>
                        <span>{{ $finding['message'] }}</span>
                    </div>
                @empty
                    <div class="ai-ca-findings-empty">No issues found — this content looks solid.</div>
                @endforelse
            </div>

            <div class="ai-ca-card" style="max-width:600px">
                <h3>AI Summary</h3>
                <x-filament::button size="xs" color="gray" wire:click="generateReviewSummary" wire:loading.attr="disabled">
                    Generate summary
                </x-filament::button>

                @if ($this->reviewSummary)
                    @if (in_array($this->reviewSummary->status, ['queued', 'processing']))
                        <div class="ai-ca-status processing" style="margin-top:.5rem">{{ ucfirst($this->reviewSummary->status) }}…</div>
                    @elseif ($this->reviewSummary->status === 'failed')
                        <div class="ai-ca-status failed" style="margin-top:.5rem">Failed: {{ $this->reviewSummary->error }}</div>
                    @elseif ($this->reviewSummary->status === 'completed')
                        <div class="ai-ca-preview" style="margin-top:.5rem">{{ $this->reviewSummary->result }}</div>
                    @endif
                @endif
            </div>
        @else
        <div class="ai-ca-grid">
            @foreach ($this->fields as $field)
                <div class="ai-ca-card">
                    <h3>{{ $field['label'] }}</h3>

                    <div class="ai-ca-current {{ blank($field['current_value']) ? 'empty' : '' }}">
                        @if (blank($field['current_value']))
                            Not set yet
                        @elseif (is_array($field['current_value']))
                            {{ collect($field['current_value'])->map(fn ($v) => is_array($v) ? ($v['question'] ?? '') : $v)->implode(' · ') }}
                        @else
                            {{ \Illuminate\Support\Str::limit($field['current_value'], 200) }}
                        @endif
                    </div>

                    <div class="ai-ca-modes">
                        @foreach ($field['modes'] as $mode)
                            <button type="button" class="ai-ca-mode-btn" wire:click="generateField('{{ $field['key'] }}', '{{ $mode }}')" wire:loading.attr="disabled">
                                {{ ucfirst($mode) }}
                            </button>
                        @endforeach
                    </div>

                    @if ($field['latest'])
                        @php($latest = $field['latest'])

                        @if (in_array($latest->status, ['queued', 'processing']))
                            <div class="ai-ca-status processing">{{ ucfirst($latest->status) }}…</div>
                        @elseif ($latest->status === 'failed')
                            <div class="ai-ca-status failed">Failed: {{ $latest->error }}</div>
                        @elseif ($latest->status === 'completed')
                            @php($diff = $latest->applied_at ? null : $this->diffFor($field['current_value'], $latest->result))

                            @if ($diff)
                                <div class="ai-ca-diff">
                                    @foreach ($diff as $op)
                                        @if ($op['type'] === 'del')
                                            <del>{{ $op['text'] }}</del>
                                        @elseif ($op['type'] === 'add')
                                            <ins>{{ $op['text'] }}</ins>
                                        @else
                                            {{ $op['text'] }}
                                        @endif
                                    @endforeach
                                </div>
                            @else
                                <div class="ai-ca-preview">
                                    @if (is_array($latest->result) && isset($latest->result[0]['question']))
                                        @foreach ($latest->result as $qa)
                                            <div class="qa"><strong>{{ $qa['question'] }}</strong>{{ $qa['answer'] }}</div>
                                        @endforeach
                                    @elseif (is_array($latest->result))
                                        <ul>
                                            @foreach ($latest->result as $item)
                                                <li>{{ $item }}</li>
                                            @endforeach
                                        </ul>
                                    @else
                                        {{ $latest->result }}
                                    @endif
                                </div>
                            @endif

                            @if ($latest->applied_at)
                                <div class="ai-ca-status">Applied {{ $latest->applied_at->diffForHumans() }}</div>
                            @else
                                <div class="ai-ca-actions">
                                    <x-filament::button size="xs" wire:click="applyGeneration({{ $latest->id }})" wire:confirm="Replace the current value with this suggestion?">
                                        Apply
                                    </x-filament::button>
                                </div>
                            @endif
                        @endif
                    @endif

                    @if ($field['history']->count() > 1)
                        <div class="ai-ca-history">
                            @foreach ($field['history']->skip(1)->take(4) as $past)
                                <div class="ai-ca-history-item">
                                    <span>{{ ucfirst($past->mode) }} · {{ $past->created_at->diffForHumans() }}</span>
                                    @if ($past->canRestore())
                                        <button type="button" wire:click="restoreGeneration({{ $past->id }})" wire:confirm="Restore the value from before this generation ran?" style="color:#2563eb;background:none;border:none;cursor:pointer;font-size:.72rem">
                                            Restore
                                        </button>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="ai-ca-grid" style="margin-top:1rem">
            @if (isset($this->suggestionFields['internal_links']))
                @php($internalLinksField = $this->suggestionFields['internal_links'])
                <div class="ai-ca-card">
                    <h3>Internal Link Suggestions</h3>
                    <x-filament::button size="xs" color="gray" wire:click="generateField('internal_links', 'generate')" wire:loading.attr="disabled">
                        Generate
                    </x-filament::button>

                    @if ($internalLinksField['latest'])
                        @php($latest = $internalLinksField['latest'])
                        @if (in_array($latest->status, ['queued', 'processing']))
                            <div class="ai-ca-status processing">{{ ucfirst($latest->status) }}…</div>
                        @elseif ($latest->status === 'failed')
                            <div class="ai-ca-status failed">Failed: {{ $latest->error }}</div>
                        @elseif ($latest->status === 'completed')
                            <div class="ai-ca-preview">
                                @foreach ($latest->result as $item)
                                    <div class="qa"><strong>→ {{ $this->resolveTargetLabel($item['type'], $item['id']) }}</strong>"{{ $item['anchor_text'] }}" — {{ $item['reason'] }}</div>
                                @endforeach
                            </div>
                            @if ($latest->applied_at)
                                <div class="ai-ca-status">Added as pending suggestions {{ $latest->applied_at->diffForHumans() }}</div>
                            @else
                                <x-filament::button size="xs" wire:click="applyInternalLinkSuggestions({{ $latest->id }})" wire:confirm="Add these as pending suggestions in the Internal Linking Center?">
                                    Add as pending suggestions
                                </x-filament::button>
                            @endif
                        @endif
                    @endif
                </div>
            @endif

            @if (isset($this->suggestionFields['external_links']))
                <div class="ai-ca-card">
                    <h3>External Link Suggestions</h3>
                    <x-filament::button size="xs" color="gray" wire:click="generateField('external_links', 'generate')" wire:loading.attr="disabled">
                        Generate
                    </x-filament::button>

                    @php($latest = $this->suggestionFields['external_links']['latest'])
                    @if ($latest)
                        @if (in_array($latest->status, ['queued', 'processing']))
                            <div class="ai-ca-status processing">{{ ucfirst($latest->status) }}…</div>
                        @elseif ($latest->status === 'failed')
                            <div class="ai-ca-status failed">Failed: {{ $latest->error }}</div>
                        @elseif ($latest->status === 'completed')
                            <div class="ai-ca-preview">
                                @foreach ($this->verifiedExternalLinks as $item)
                                    <div class="qa">
                                        <strong>{{ $item['url'] }} @if ($item['broken']) <span style="color:#b91c1c">(unreachable)</span> @endif</strong>
                                        "{{ $item['anchor_text'] }}" — {{ $item['reason'] }}
                                    </div>
                                @endforeach
                            </div>
                            <div class="ai-ca-status">Suggestion only — copy a link into the body manually if you want to use it.</div>
                        @endif
                    @endif
                </div>
            @endif

            @if (isset($this->suggestionFields['schema']))
                <div class="ai-ca-card">
                    <h3>Schema Suggestions</h3>
                    <x-filament::button size="xs" color="gray" wire:click="generateField('schema', 'generate')" wire:loading.attr="disabled">
                        Generate
                    </x-filament::button>

                    @php($latest = $this->suggestionFields['schema']['latest'])
                    @if ($latest)
                        @if (in_array($latest->status, ['queued', 'processing']))
                            <div class="ai-ca-status processing">{{ ucfirst($latest->status) }}…</div>
                        @elseif ($latest->status === 'failed')
                            <div class="ai-ca-status failed">Failed: {{ $latest->error }}</div>
                        @elseif ($latest->status === 'completed')
                            <div class="ai-ca-preview"><pre style="white-space:pre-wrap;font-size:.75rem">{{ $latest->result }}</pre></div>
                            <div class="ai-ca-status">Suggestion only — hand this to a developer to wire into the template.</div>
                        @endif
                    @endif
                </div>
            @endif

            @if (isset($this->suggestionFields['caption']))
                <div class="ai-ca-card">
                    <h3>Image Caption</h3>
                    <x-filament::button size="xs" color="gray" wire:click="generateField('caption', 'generate')" wire:loading.attr="disabled">
                        Generate
                    </x-filament::button>

                    @php($latest = $this->suggestionFields['caption']['latest'])
                    @if ($latest)
                        @if (in_array($latest->status, ['queued', 'processing']))
                            <div class="ai-ca-status processing">{{ ucfirst($latest->status) }}…</div>
                        @elseif ($latest->status === 'failed')
                            <div class="ai-ca-status failed">Failed: {{ $latest->error }}</div>
                        @elseif ($latest->status === 'completed')
                            <div class="ai-ca-preview">{{ $latest->result }}</div>
                            <div class="ai-ca-status">Suggestion only — copy under the image manually if you want to use it.</div>
                        @endif
                    @endif
                </div>
            @endif
        </div>
        @endif
    </div>
</div>
