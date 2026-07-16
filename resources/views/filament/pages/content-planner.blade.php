<x-filament-panels::page>
    <style>
        .cp-tabs{display:flex;gap:.5rem;margin-bottom:1rem;flex-wrap:wrap}
        .cp-tabs button{
            border:1px solid rgb(209 213 219);border-radius:.5rem;padding:.45rem .9rem;
            background:#fff;cursor:pointer;font-size:.85rem;font-weight:600;color:#374151;
        }
        .cp-tabs button.active{background:#111827;color:#fff;border-color:#111827}
        :root.dark .cp-tabs button{background:#1f2937;color:#e5e7eb;border-color:#374151}
        :root.dark .cp-tabs button.active{background:#d9bb75;color:#111827;border-color:#d9bb75}

        .cp-filters{
            display:flex;flex-wrap:wrap;gap:.6rem;align-items:center;margin-bottom:1.25rem;
            padding:.75rem;border:1px solid rgb(229 231 235);border-radius:.75rem;background:#f9fafb;
        }
        :root.dark .cp-filters{background:#111827;border-color:#374151}
        .cp-filters select,.cp-filters input[type="text"]{
            font-size:.8rem;border:1px solid rgb(209 213 219);border-radius:.4rem;padding:.35rem .5rem;background:#fff;
        }
        :root.dark .cp-filters select,:root.dark .cp-filters input[type="text"]{background:#1f2937;color:#e5e7eb;border-color:#4b5563}
        .cp-filters button.reset{
            font-size:.78rem;color:#6b7280;background:none;border:none;cursor:pointer;text-decoration:underline;
        }

        .cp-kanban{display:flex;gap:1rem;overflow-x:auto;padding-bottom:1rem}
        .cp-column{
            min-width:260px;max-width:280px;flex:0 0 auto;background:#f9fafb;border:1px solid rgb(229 231 235);
            border-radius:.75rem;padding:.6rem;display:flex;flex-direction:column;gap:.5rem;transition:background .15s;
        }
        :root.dark .cp-column{background:#111827;border-color:#374151}
        .cp-column.dragover{background:#eef2ff}
        :root.dark .cp-column.dragover{background:#1e2a4a}
        .cp-column-title{font-size:.85rem;font-weight:700;display:flex;align-items:center;justify-content:space-between;padding:0 .2rem}
        .cp-column-dot{width:9px;height:9px;border-radius:50%;display:inline-block;margin-right:.4rem}

        .cp-card{
            background:#fff;border:1px solid rgb(229 231 235);border-radius:.6rem;padding:.6rem;
            font-size:.78rem;cursor:grab;display:flex;flex-direction:column;gap:.35rem;
        }
        :root.dark .cp-card{background:#1f2937;border-color:#374151;color:#e5e7eb}
        .cp-card:active{cursor:grabbing}
        .cp-card-title{font-weight:600;font-size:.82rem}
        .cp-card-row{display:flex;flex-wrap:wrap;gap:.3rem;align-items:center}
        .cp-badge{
            display:inline-block;font-size:.68rem;padding:.1rem .4rem;border-radius:.35rem;
            background:#e5e7eb;color:#374151;
        }
        :root.dark .cp-badge{background:#374151;color:#e5e7eb}
        .cp-priority-low{background:#e5e7eb;color:#374151}
        .cp-priority-medium{background:#dbeafe;color:#1e40af}
        .cp-priority-high{background:#fef3c7;color:#92400e}
        .cp-priority-critical{background:#fee2e2;color:#991b1b}
        .cp-scores{display:flex;gap:.5rem;font-size:.7rem;color:#6b7280}
        :root.dark .cp-scores{color:#9ca3af}
        .cp-card-footer{display:flex;justify-content:space-between;font-size:.68rem;color:#9ca3af}
        .cp-card a{font-size:.72rem;color:#2563eb;text-decoration:none}
        :root.dark .cp-card a{color:#93c5fd}

        .cp-bulk-bar{
            position:sticky;bottom:0;display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;
            background:#111827;color:#fff;padding:.6rem .9rem;border-radius:.6rem;margin-top:.75rem;font-size:.82rem;
        }
        .cp-bulk-bar select{font-size:.8rem;border-radius:.35rem;padding:.25rem .4rem}
        .cp-bulk-bar button{
            font-size:.78rem;padding:.3rem .7rem;border-radius:.4rem;border:1px solid rgba(255,255,255,.3);
            background:rgba(255,255,255,.1);color:#fff;cursor:pointer;
        }
        .cp-bulk-bar button.danger{background:#7f1d1d;border-color:#7f1d1d}

        .cp-empty{color:#9ca3af;font-size:.8rem;text-align:center;padding:1rem 0}
        .cp-placeholder{padding:2rem;text-align:center;color:#9ca3af;font-size:.9rem}

        .cpcal-toolbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;flex-wrap:wrap;gap:.75rem}
        .cpcal-nav{display:flex;align-items:center;gap:.5rem}
        .cpcal-nav button{border:1px solid rgb(209 213 219);border-radius:.5rem;padding:.4rem .8rem;background:#fff;cursor:pointer;font-size:.85rem}
        :root.dark .cpcal-nav button{background:#1f2937;color:#e5e7eb;border-color:#4b5563}
        .cpcal-title{font-size:1.1rem;font-weight:700}
        .cpcal-legend{display:flex;gap:1rem;font-size:.8rem;color:#6b7280}
        .cpcal-legend span{display:inline-flex;align-items:center;gap:.35rem}
        .cpcal-dot{width:9px;height:9px;border-radius:50%;display:inline-block}
        .cpcal-dot.content{background:#2563eb}
        .cpcal-dot.planned{background:#9ca3af}
        .cpcal-dot.deadline{background:#dc2626}

        .cpcal-grid{display:grid;grid-template-columns:repeat(7,1fr);border:1px solid rgb(229 231 235);border-radius:.75rem;overflow:hidden}
        .cpcal-headcell{background:#f9fafb;padding:.5rem;text-align:center;font-size:.75rem;font-weight:600;color:#6b7280;border-bottom:1px solid rgb(229 231 235)}
        :root.dark .cpcal-headcell{background:#111827;color:#9ca3af;border-color:#374151}
        .cpcal-day{min-height:110px;border-right:1px solid rgb(229 231 235);border-bottom:1px solid rgb(229 231 235);padding:.35rem;display:flex;flex-direction:column;gap:.25rem;transition:background .15s}
        :root.dark .cpcal-day{border-color:#374151}
        .cpcal-day:nth-child(7n){border-right:none}
        .cpcal-day.out{background:#fafafa;color:#c0c4cc}
        :root.dark .cpcal-day.out{background:#0b1220}
        .cpcal-day.today{background:#fffbeb}
        :root.dark .cpcal-day.today{background:#3a2f10}
        .cpcal-day.dragover{background:#eef2ff}
        :root.dark .cpcal-day.dragover{background:#1e2a4a}
        .cpcal-daynum{font-size:.75rem;font-weight:600;color:#4b5563}
        :root.dark .cpcal-daynum{color:#9ca3af}
        .cpcal-day.out .cpcal-daynum{color:#c0c4cc}
        .cpcal-chip{display:block;font-size:.7rem;line-height:1.3;padding:.25rem .4rem;border-radius:.4rem;color:#fff;text-decoration:none;cursor:grab;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .cpcal-chip:active{cursor:grabbing}
        .cpcal-chip.content{background:#2563eb}
        .cpcal-chip.planned{background:#9ca3af}
        .cpcal-chip.deadline{background:#dc2626}

        .cp-table{width:100%;border-collapse:collapse;font-size:.8rem}
        .cp-table th{text-align:left;padding:.5rem .6rem;background:#f9fafb;border-bottom:1px solid rgb(229 231 235);font-weight:600;color:#374151;white-space:nowrap}
        .cp-table td{padding:.5rem .6rem;border-bottom:1px solid rgb(243 244 246);vertical-align:top}
        :root.dark .cp-table th{background:#111827;border-color:#374151;color:#e5e7eb}
        :root.dark .cp-table td{border-color:#1f2937;color:#e5e7eb}
        .cp-table a{color:#2563eb;text-decoration:none}
        :root.dark .cp-table a{color:#93c5fd}

        .cp-stats{display:flex;flex-wrap:wrap;gap:.75rem;margin-bottom:1.5rem}
        .cp-stat{
            flex:1 1 140px;border:1px solid rgb(229 231 235);border-radius:.75rem;padding:.9rem 1rem;
            display:flex;flex-direction:column;gap:.15rem;background:#fff;
        }
        :root.dark .cp-stat{background:#1f2937;border-color:#374151}
        .cp-stat-value{font-size:1.5rem;font-weight:700}
        .cp-stat-label{font-size:.75rem;color:#6b7280}
        :root.dark .cp-stat-label{color:#9ca3af}

        .cp-chart-card{border:1px solid rgb(229 231 235);border-radius:.75rem;padding:1rem}
        :root.dark .cp-chart-card{border-color:#374151}
        .cp-chart-card h3{font-size:.9rem;font-weight:700;margin-bottom:1rem}
        .cp-bars{display:flex;align-items:flex-end;gap:1rem;height:140px}
        .cp-bar-col{display:flex;flex-direction:column;align-items:center;justify-content:flex-end;gap:.25rem;flex:1}
        .cp-bar{width:100%;max-width:40px;background:#2563eb;border-radius:.3rem .3rem 0 0}
        .cp-bar-count{font-size:.75rem;font-weight:600}
        .cp-bar-label{font-size:.7rem;color:#6b7280}
        :root.dark .cp-bar-label{color:#9ca3af}
    </style>

    <div class="cp-tabs">
        <button type="button" wire:click="setView('kanban')" class="{{ $activeView === 'kanban' ? 'active' : '' }}">Kanban</button>
        <button type="button" wire:click="setView('calendar')" class="{{ $activeView === 'calendar' ? 'active' : '' }}">Calendar</button>
        <button type="button" wire:click="setView('table')" class="{{ $activeView === 'table' ? 'active' : '' }}">Table</button>
        <button type="button" wire:click="setView('dashboard')" class="{{ $activeView === 'dashboard' ? 'active' : '' }}">Dashboard</button>
    </div>

    @if($activeView !== 'dashboard')
        <div class="cp-filters">
            <input type="text" wire:model.live.debounce.400ms="search" placeholder="Search title…">

            <select wire:model.live="filterStage">
                <option value="all">All stages</option>
                @foreach($this->stages as $stage)
                    <option value="{{ $stage->id }}">{{ $stage->label }}</option>
                @endforeach
            </select>

            <select wire:model.live="filterLocale">
                <option value="all">All languages</option>
                <option value="en">English</option>
                <option value="tr">Türkçe</option>
            </select>

            <select wire:model.live="filterAuthor">
                <option value="all">All authors</option>
                @foreach($this->filterAuthors as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>

            <select wire:model.live="filterCategory">
                <option value="all">All categories</option>
                @foreach($this->filterCategories as $category)
                    <option value="{{ $category }}">{{ $category }}</option>
                @endforeach
            </select>

            <select wire:model.live="filterTag">
                <option value="all">All tags</option>
                @foreach($this->filterTags as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>

            <select wire:model.live="filterPriority">
                <option value="all">All priorities</option>
                <option value="low">Low</option>
                <option value="medium">Medium</option>
                <option value="high">High</option>
                <option value="critical">Critical</option>
            </select>

            <select wire:model.live="filterPublicationStatus">
                <option value="all">All publication statuses</option>
                @foreach(\App\Filament\Pages\ContentPlanner::PUBLICATION_STATUSES as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>

            <button type="button" class="reset" wire:click="resetFilters">Reset filters</button>
        </div>
    @endif

    @if($activeView === 'kanban')
        <div class="cp-kanban" id="contentPlannerKanban" wire:loading.class="opacity-50">
            @forelse($this->stages as $stage)
                <div class="cp-column" data-stage-id="{{ $stage->id }}">
                    <div class="cp-column-title">
                        <span><i class="cp-column-dot" style="background:{{ $stage->color ?? '#9ca3af' }}"></i>{{ $stage->label }}</span>
                        <span>{{ ($this->plansByStage[$stage->id] ?? collect())->count() }}</span>
                    </div>

                    @forelse(($this->plansByStage[$stage->id] ?? collect()) as $plan)
                        <div class="cp-card" draggable="true" data-plan-id="{{ $plan->id }}">
                            <div class="cp-card-row">
                                <input type="checkbox" wire:model.live="selectedPlanIds" value="{{ $plan->id }}">
                                <span class="cp-card-title">{{ $plan->title }}</span>
                            </div>

                            <div class="cp-card-row">
                                <span class="cp-badge">{{ strtoupper($plan->locale) }}</span>
                                @if($plan->category)<span class="cp-badge">{{ $plan->category }}</span>@endif
                                @foreach($plan->tags as $tag)
                                    <span class="cp-badge" @if($tag->color) style="background:{{ $tag->color }};color:#fff" @endif>{{ $tag->name }}</span>
                                @endforeach
                            </div>

                            <div class="cp-card-row">
                                <span class="cp-badge">{{ $plan->author?->name ?? '—' }}</span>
                                <span class="cp-badge cp-priority-{{ $plan->priority }}">{{ ucfirst($plan->priority) }}</span>
                            </div>

                            @php($score = $this->scoreCardFor($plan))
                            @if($score)
                                <div class="cp-scores">
                                    <span>SEO {{ $score['categories']['seo']['score'] }}</span>
                                    <span>AI {{ $score['overall'] }}</span>
                                    <span>Read {{ $score['categories']['readability']['score'] }}</span>
                                </div>
                            @endif

                            <div class="cp-card-footer">
                                <span>{{ optional($plan->effectivePublishDate())->format('Y-m-d') ?? 'No date' }}</span>
                                <span>{{ $plan->updated_at->diffForHumans() }}</span>
                            </div>

                            <a href="{{ $this->editUrlFor($plan) }}" wire:navigate>Edit →</a>
                        </div>
                    @empty
                        <div class="cp-empty">No cards</div>
                    @endforelse
                </div>
            @empty
                <p class="cp-placeholder">No workflow stages configured.</p>
            @endforelse
        </div>

        @if(count($selectedPlanIds))
            <div class="cp-bulk-bar">
                <span>{{ count($selectedPlanIds) }} selected</span>

                <select wire:model="bulkStageId">
                    <option value="">Move to…</option>
                    @foreach($this->stages as $stage)
                        <option value="{{ $stage->id }}">{{ $stage->label }}</option>
                    @endforeach
                </select>
                <button type="button" wire:click="bulkMoveStage">Move</button>

                <select wire:model="bulkPriority">
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                    <option value="critical">Critical</option>
                </select>
                <button type="button" wire:click="bulkSetPriority">Set priority</button>

                <button type="button" class="danger" wire:click="bulkDelete" wire:confirm="Delete {{ count($selectedPlanIds) }} card(s)? This does not delete any linked Article/Page.">Delete</button>
            </div>
        @endif
    @elseif($activeView === 'calendar')
        <div class="cpcal-toolbar">
            <div class="cpcal-nav">
                <button type="button" wire:click="previousMonth">← Prev</button>
                <button type="button" wire:click="goToday">Today</button>
                <button type="button" wire:click="nextMonth">Next →</button>
                <span class="cpcal-title">{{ $this->monthLabel }}</span>
            </div>
            <div class="cpcal-legend">
                <span><i class="cpcal-dot content"></i> Article/Page</span>
                <span><i class="cpcal-dot planned"></i> Planned idea</span>
                <span><i class="cpcal-dot deadline"></i> Draft deadline</span>
            </div>
        </div>

        <div class="cpcal-grid" id="contentPlannerCalendar" wire:loading.class="opacity-50">
            @foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $d)
                <div class="cpcal-headcell">{{ $d }}</div>
            @endforeach

            @foreach($this->calendarWeeks as $week)
                @foreach($week as $day)
                    <div class="cpcal-day @if(! $day['inMonth']) out @endif @if($day['isToday']) today @endif" data-date="{{ $day['date']->format('Y-m-d') }}">
                        <div class="cpcal-daynum">{{ $day['date']->format('j') }}</div>

                        @foreach($day['articles'] as $article)
                            <a href="{{ $this->contentEditUrl('Article', $article->id) }}" wire:navigate class="cpcal-chip content" draggable="true" data-kind="content" data-type="Article" data-id="{{ $article->id }}" title="{{ $article->title }}">{{ \Illuminate\Support\Str::limit($article->title, 20) }}</a>
                        @endforeach

                        @foreach($day['pages'] as $page)
                            <a href="{{ $this->contentEditUrl('Page', $page->id) }}" wire:navigate class="cpcal-chip content" draggable="true" data-kind="content" data-type="Page" data-id="{{ $page->id }}" title="{{ $page->title }}">{{ \Illuminate\Support\Str::limit($page->title, 20) }}</a>
                        @endforeach

                        @foreach($day['planned'] as $plan)
                            <a href="{{ $this->editUrlFor($plan) }}" wire:navigate class="cpcal-chip planned" draggable="true" data-kind="planned" data-id="{{ $plan->id }}" title="{{ $plan->title }}">{{ \Illuminate\Support\Str::limit($plan->title, 20) }}</a>
                        @endforeach

                        @foreach($day['deadlines'] as $plan)
                            <a href="{{ $this->editUrlFor($plan) }}" wire:navigate class="cpcal-chip deadline" draggable="true" data-kind="deadline" data-id="{{ $plan->id }}" title="{{ $plan->title }}">{{ \Illuminate\Support\Str::limit($plan->title, 20) }}</a>
                        @endforeach
                    </div>
                @endforeach
            @endforeach
        </div>
    @elseif($activeView === 'table')
        <div style="overflow-x:auto">
            <table class="cp-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Lang</th>
                        <th>Category</th>
                        <th>Tags</th>
                        <th>Author</th>
                        <th>Assigned to</th>
                        <th>Stage</th>
                        <th>Priority</th>
                        <th>SEO</th>
                        <th>AI</th>
                        <th>Read.</th>
                        <th>Publish date</th>
                        <th>Last updated</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->tablePlans as $plan)
                        @php($score = $this->scoreCardFor($plan))
                        <tr>
                            <td>{{ $plan->title }}</td>
                            <td><span class="cp-badge">{{ strtoupper($plan->locale) }}</span></td>
                            <td>{{ $plan->category ?? '—' }}</td>
                            <td>
                                @foreach($plan->tags as $tag)
                                    <span class="cp-badge">{{ $tag->name }}</span>
                                @endforeach
                            </td>
                            <td>{{ $plan->author?->name ?? '—' }}</td>
                            <td>{{ $plan->assignee?->name ?? '—' }}</td>
                            <td><span class="cp-badge" style="background:{{ $plan->workflowStage?->color ?? '#9ca3af' }};color:#fff">{{ $plan->workflowStage?->label }}</span></td>
                            <td><span class="cp-badge cp-priority-{{ $plan->priority }}">{{ ucfirst($plan->priority) }}</span></td>
                            <td>{{ $score['categories']['seo']['score'] ?? '—' }}</td>
                            <td>{{ $score['overall'] ?? '—' }}</td>
                            <td>{{ $score['categories']['readability']['score'] ?? '—' }}</td>
                            <td>{{ optional($plan->effectivePublishDate())->format('Y-m-d') ?? '—' }}</td>
                            <td>{{ $plan->updated_at->diffForHumans() }}</td>
                            <td><a href="{{ $this->editUrlFor($plan) }}" wire:navigate>Edit →</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="14" class="cp-empty">No content plans match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @elseif($activeView === 'dashboard')
        <div class="cp-stats">
            <div class="cp-stat"><span class="cp-stat-value">{{ $this->dashboardStats['ideas'] }}</span><span class="cp-stat-label">Ideas</span></div>
            <div class="cp-stat"><span class="cp-stat-value">{{ $this->dashboardStats['drafts'] }}</span><span class="cp-stat-label">Drafts</span></div>
            <div class="cp-stat"><span class="cp-stat-value">{{ $this->dashboardStats['reviews'] }}</span><span class="cp-stat-label">In Review</span></div>
            <div class="cp-stat"><span class="cp-stat-value">{{ $this->dashboardStats['scheduled'] }}</span><span class="cp-stat-label">Scheduled</span></div>
            <div class="cp-stat"><span class="cp-stat-value">{{ $this->dashboardStats['published'] }}</span><span class="cp-stat-label">Published</span></div>
            <div class="cp-stat"><span class="cp-stat-value">{{ $this->dashboardStats['avg_publishing_days'] ?? '—' }}</span><span class="cp-stat-label">Avg. days to publish</span></div>
            <div class="cp-stat"><span class="cp-stat-value">{{ $this->dashboardStats['avg_review_days'] ?? '—' }}</span><span class="cp-stat-label">Avg. days in review</span></div>
        </div>

        <div class="cp-chart-card">
            <h3>Content production per month</h3>
            @php($maxCount = max(1, collect($this->productionPerMonth)->max('count')))
            <div class="cp-bars">
                @foreach($this->productionPerMonth as $month)
                    <div class="cp-bar-col">
                        <div class="cp-bar" style="height:{{ $month['count'] > 0 ? max(6, round(($month['count'] / $maxCount) * 100)) : 2 }}px" title="{{ $month['count'] }} published"></div>
                        <span class="cp-bar-count">{{ $month['count'] }}</span>
                        <span class="cp-bar-label">{{ $month['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <script>
        (function () {
            const board = document.getElementById('contentPlannerKanban');
            if (!board) return;

            function wireUp() {
                board.querySelectorAll('.cp-card').forEach(function (card) {
                    card.addEventListener('dragstart', function (e) {
                        e.dataTransfer.setData('text/plain', card.dataset.planId);
                        e.dataTransfer.effectAllowed = 'move';
                    });
                });

                board.querySelectorAll('.cp-column').forEach(function (column) {
                    column.addEventListener('dragover', function (e) {
                        e.preventDefault();
                        column.classList.add('dragover');
                    });
                    column.addEventListener('dragleave', function () {
                        column.classList.remove('dragover');
                    });
                    column.addEventListener('drop', function (e) {
                        e.preventDefault();
                        column.classList.remove('dragover');
                        var planId = e.dataTransfer.getData('text/plain');
                        var stageId = column.dataset.stageId;
                        if (!planId || !stageId) return;
                        @this.call('moveCard', parseInt(planId, 10), parseInt(stageId, 10));
                    });
                });
            }

            wireUp();
            document.addEventListener('livewire:navigated', wireUp);
            Livewire.hook('morph.updated', ({ el }) => {
                if (el === board || board.contains(el)) wireUp();
            });
        })();

        (function () {
            const grid = document.getElementById('contentPlannerCalendar');
            if (!grid) return;

            function wireUp() {
                grid.querySelectorAll('.cpcal-chip').forEach(function (chip) {
                    chip.addEventListener('dragstart', function (e) {
                        e.dataTransfer.setData('text/plain', JSON.stringify({
                            kind: chip.dataset.kind,
                            type: chip.dataset.type || '',
                            id: chip.dataset.id,
                        }));
                        e.dataTransfer.effectAllowed = 'move';
                    });
                });

                grid.querySelectorAll('.cpcal-day').forEach(function (cell) {
                    cell.addEventListener('dragover', function (e) {
                        e.preventDefault();
                        cell.classList.add('dragover');
                    });
                    cell.addEventListener('dragleave', function () {
                        cell.classList.remove('dragover');
                    });
                    cell.addEventListener('drop', function (e) {
                        e.preventDefault();
                        cell.classList.remove('dragover');
                        var raw = e.dataTransfer.getData('text/plain');
                        var newDate = cell.dataset.date;
                        if (!raw || !newDate) return;
                        var payload = JSON.parse(raw);
                        @this.call('rescheduleItem', payload.kind, payload.type, parseInt(payload.id, 10), newDate);
                    });
                });
            }

            wireUp();
            document.addEventListener('livewire:navigated', wireUp);
            Livewire.hook('morph.updated', ({ el }) => {
                if (el === grid || grid.contains(el)) wireUp();
            });
        })();
    </script>
</x-filament-panels::page>
