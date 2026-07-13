<x-filament-panels::page>
    <style>
        .cal-toolbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;flex-wrap:wrap;gap:.75rem}
        .cal-nav{display:flex;align-items:center;gap:.5rem}
        .cal-nav button{
            border:1px solid rgb(209 213 219);border-radius:.5rem;padding:.4rem .8rem;
            background:#fff;cursor:pointer;font-size:.85rem;
        }
        .cal-nav button:hover{background:#f9fafb}
        .cal-title{font-size:1.1rem;font-weight:700}

        .cal-legend{display:flex;gap:1rem;font-size:.8rem;color:#6b7280}
        .cal-legend span{display:inline-flex;align-items:center;gap:.35rem}
        .cal-dot{width:9px;height:9px;border-radius:50%;display:inline-block}
        .cal-dot.draft{background:#9ca3af}
        .cal-dot.scheduled{background:#f59e0b}
        .cal-dot.published{background:#22c55e}

        .cal-grid{
            display:grid;grid-template-columns:repeat(7,1fr);
            border:1px solid rgb(229 231 235);border-radius:.75rem;overflow:hidden;
        }
        .cal-headcell{
            background:#f9fafb;padding:.5rem;text-align:center;font-size:.75rem;
            font-weight:600;color:#6b7280;border-bottom:1px solid rgb(229 231 235);
        }
        .cal-day{
            min-height:110px;border-right:1px solid rgb(229 231 235);
            border-bottom:1px solid rgb(229 231 235);padding:.35rem;
            display:flex;flex-direction:column;gap:.25rem;transition:background .15s;
        }
        .cal-day:nth-child(7n){border-right:none}
        .cal-day.out{background:#fafafa;color:#c0c4cc}
        .cal-day.today{background:#fffbeb}
        .cal-day.dragover{background:#eef2ff}
        .cal-daynum{font-size:.75rem;font-weight:600;color:#4b5563}
        .cal-day.out .cal-daynum{color:#c0c4cc}

        .cal-chip{
            display:block;font-size:.7rem;line-height:1.3;padding:.25rem .4rem;
            border-radius:.4rem;color:#fff;text-decoration:none;cursor:grab;
            white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
        }
        .cal-chip:active{cursor:grabbing}
        .cal-chip.draft{background:#9ca3af}
        .cal-chip.scheduled{background:#f59e0b}
        .cal-chip.published{background:#22c55e}
        .cal-chip .lang{opacity:.85;font-size:.62rem;margin-left:.25rem}
    </style>

    <div class="cal-toolbar">
        <div class="cal-nav">
            <button type="button" wire:click="previousMonth">← Prev</button>
            <button type="button" wire:click="goToday">Today</button>
            <button type="button" wire:click="nextMonth">Next →</button>
            <span class="cal-title">{{ $this->getMonthLabel() }}</span>
        </div>
        <div class="cal-legend">
            <span><i class="cal-dot draft"></i> Draft</span>
            <span><i class="cal-dot scheduled"></i> Scheduled</span>
            <span><i class="cal-dot published"></i> Published</span>
        </div>
    </div>

    <div class="cal-grid" id="editorialCalendarGrid" wire:loading.class="opacity-50">
        @foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d)
        <div class="cal-headcell">{{ $d }}</div>
        @endforeach

        @foreach($this->getCalendarWeeks() as $week)
            @foreach($week as $day)
            <div
                class="cal-day @if(!$day['inMonth']) out @endif @if($day['isToday']) today @endif"
                data-date="{{ $day['date']->format('Y-m-d') }}"
            >
                <div class="cal-daynum">{{ $day['date']->format('j') }}</div>

                @foreach($day['articles'] as $article)
                <a
                    href="{{ $this->editUrl($article) }}"
                    class="cal-chip {{ $article->status }}"
                    draggable="true"
                    data-id="{{ $article->id }}"
                    title="{{ $article->title }}"
                >{{ \Illuminate\Support\Str::limit($article->title, 22) }}<span class="lang">{{ strtoupper($article->locale) }}</span></a>
                @endforeach
            </div>
            @endforeach
        @endforeach
    </div>

    <script>
        (function () {
            const grid = document.getElementById('editorialCalendarGrid');
            if (!grid) return;

            function wireUp() {
                grid.querySelectorAll('.cal-chip').forEach(function (chip) {
                    chip.addEventListener('dragstart', function (e) {
                        e.dataTransfer.setData('text/plain', chip.dataset.id);
                        e.dataTransfer.effectAllowed = 'move';
                    });
                });

                grid.querySelectorAll('.cal-day').forEach(function (cell) {
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
                        var articleId = e.dataTransfer.getData('text/plain');
                        var newDate = cell.dataset.date;
                        if (!articleId || !newDate) return;
                        @this.call('moveArticle', parseInt(articleId, 10), newDate);
                    });
                });
            }

            wireUp();
            // بعد از هر به‌روزرسانی Livewire (مثلاً بعد از جابه‌جایی)، دوباره رویدادها را وصل کن
            document.addEventListener('livewire:navigated', wireUp);
            Livewire.hook('morph.updated', ({ el }) => {
                if (el === grid || grid.contains(el)) wireUp();
            });
        })();
    </script>
</x-filament-panels::page>
