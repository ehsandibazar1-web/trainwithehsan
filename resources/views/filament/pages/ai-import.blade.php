<x-filament-panels::page>

    <form wire:submit.prevent>
        {{ $this->form }}

        <div style="display:flex;gap:.75rem;margin-top:1.5rem;flex-wrap:wrap">
            <x-filament::button color="gray" wire:click="runValidate" wire:loading.attr="disabled">
                Validate
            </x-filament::button>
            <x-filament::button color="info" wire:click="runPreview" wire:loading.attr="disabled">
                Preview
            </x-filament::button>
            <x-filament::button color="primary" wire:click="runImport" wire:confirm="Import this article into the CMS now?" wire:loading.attr="disabled">
                Import
            </x-filament::button>
        </div>
    </form>

    {{-- ============ نتیجه‌ی ایمپورت موفق ============ --}}
    @if($importedInfo)
        <x-filament::section>
            <x-slot name="heading">✅ Imported successfully</x-slot>
            <p style="margin-bottom:.75rem"><strong>{{ $importedInfo['title'] }}</strong> — {{ ucfirst($importedInfo['status']) }}</p>
            <div style="display:flex;gap:.75rem;flex-wrap:wrap">
                <x-filament::button tag="a" href="{{ $importedInfo['edit_url'] }}" color="primary" size="sm">Open in the article editor</x-filament::button>
                @if($importedInfo['status'] === 'published')
                    <x-filament::button tag="a" href="{{ $importedInfo['public_url'] }}" target="_blank" color="gray" size="sm">View on the site</x-filament::button>
                @endif
            </div>
            @if(!empty($importedInfo['warnings']))
                <ul style="margin-top:.75rem;padding-left:1.25rem;list-style:disc;font-size:.85rem;color:#92700c">
                    @foreach($importedInfo['warnings'] as $w)<li>{{ $w }}</li>@endforeach
                </ul>
            @endif
        </x-filament::section>
    @endif

    {{-- ============ خطاها و هشدارهای اعتبارسنجی ============ --}}
    @if($analysis)
        @if($analysis['errors'] !== [])
            <x-filament::section>
                <x-slot name="heading">❌ Problems that must be fixed ({{ count($analysis['errors']) }})</x-slot>
                <ul style="padding-left:1.25rem;list-style:disc;color:#b91c1c;font-size:.9rem;line-height:1.9">
                    @foreach($analysis['errors'] as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </x-filament::section>
        @endif

        @if($analysis['warnings'] !== [])
            <x-filament::section collapsible>
                <x-slot name="heading">⚠️ Notes ({{ count($analysis['warnings']) }})</x-slot>
                <ul style="padding-left:1.25rem;list-style:disc;color:#92700c;font-size:.9rem;line-height:1.9">
                    @foreach($analysis['warnings'] as $warning)<li>{{ $warning }}</li>@endforeach
                </ul>
            </x-filament::section>
        @endif

        @if($analysis['errors'] === [] && ($analysis['mapping']['mapped'] ?? []) !== [])
            <x-filament::section collapsible collapsed>
                <x-slot name="heading">🧭 Where each field will go</x-slot>
                <div style="font-size:.9rem;line-height:2">
                    @foreach($analysis['mapping']['mapped'] as $field => $target)
                        <div><code style="background:rgba(0,0,0,.06);padding:1px 6px;border-radius:4px">{{ $field }}</code> → {{ $target }}</div>
                    @endforeach
                    @foreach($analysis['mapping']['auto'] as $field => $reason)
                        <div><code style="background:rgba(0,0,0,.06);padding:1px 6px;border-radius:4px">{{ $field }}</code> → <em>automatic:</em> {{ $reason }}</div>
                    @endforeach
                    @foreach($analysis['mapping']['skipped'] as $field => $reason)
                        <div><code style="background:rgba(0,0,0,.06);padding:1px 6px;border-radius:4px">{{ $field }}</code> → <em>skipped:</em> {{ $reason }}</div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif
    @endif

    {{-- ============ پیش‌نمایش کامل — قبل از هر ذخیره‌سازی ============ --}}
    @if($preview)
        <x-filament::section>
            <x-slot name="heading">👁 Preview — nothing has been saved yet</x-slot>

            <div style="font-size:.85rem;margin-bottom:1rem;display:flex;gap:1.5rem;flex-wrap:wrap;color:#555">
                <span><strong>Language:</strong> {{ $preview['locale'] }}</span>
                <span><strong>Status:</strong> {{ $preview['status'] }}</span>
                @if($preview['published_at'])<span><strong>Publish date:</strong> {{ $preview['published_at'] }}</span>@endif
                @if($preview['category'])<span><strong>Category:</strong> {{ $preview['category'] }}</span>@endif
            </div>

            @if($preview['image'])
                <img src="{{ $preview['image'] }}" alt="Featured image preview" style="max-width:420px;width:100%;height:auto;border-radius:8px;margin-bottom:1rem">
            @endif

            <h2 style="font-size:1.4rem;font-weight:700;margin-bottom:.5rem">{{ $preview['title'] }}</h2>
            @if($preview['excerpt'])
                <p style="font-style:italic;color:#666;margin-bottom:1rem">{{ $preview['excerpt'] }}</p>
            @endif

            <div style="border:1px solid #e5e5e5;border-radius:8px;padding:1rem 1.25rem;line-height:1.9;font-size:.95rem;max-height:420px;overflow-y:auto">
                {!! $preview['body'] !!}
            </div>

            @if($preview['faqs'] !== [])
                <h3 style="font-size:1.05rem;font-weight:700;margin:1.25rem 0 .5rem">FAQ ({{ count($preview['faqs']) }})</h3>
                @foreach($preview['faqs'] as $faq)
                    <details style="border:1px solid #e5e5e5;border-radius:8px;padding:.6rem .9rem;margin-bottom:.5rem">
                        <summary style="font-weight:600;cursor:pointer">{{ $faq['question'] }}</summary>
                        <div style="padding-top:.5rem;color:#555;white-space:pre-line">{{ $faq['answer'] }}</div>
                    </details>
                @endforeach
            @endif

            <h3 style="font-size:1.05rem;font-weight:700;margin:1.25rem 0 .5rem">SEO</h3>
            <div style="font-size:.85rem;line-height:2;color:#555">
                <div><strong>Page title:</strong> {{ $preview['seo']['page_title'] }}</div>
                <div><strong>Meta description:</strong> {{ $preview['seo']['meta_description'] }}</div>
                <div><strong>URL:</strong> {{ $preview['seo']['canonical'] }}</div>
                <div><strong>Structured data:</strong> Article schema{{ $preview['faqs'] !== [] ? ' + FAQ schema' : '' }} — generated automatically</div>
            </div>
        </x-filament::section>
    @endif

    {{-- ============ راهنمای قالب استاندارد ============ --}}
    <x-filament::section collapsible collapsed>
        <x-slot name="heading">📋 Format guide — what to ask the AI to produce</x-slot>
        <p style="font-size:.85rem;color:#555;margin-bottom:.75rem">
            Give your AI (Claude, ChatGPT, …) this JSON structure. Only <strong>title</strong> and <strong>content</strong> are required — everything else is optional.
            Markdown with a front-matter header (and an optional <code>## FAQ</code> section at the end) is accepted too.
        </p>
@verbatim
<pre style="background:rgba(0,0,0,.05);border-radius:8px;padding:1rem;font-size:.78rem;overflow-x:auto;line-height:1.7">{
  "language": "en",
  "title": "Article title",
  "slug": "article-title",
  "excerpt": "One or two standalone sentences used as the meta description.",
  "content": "&lt;p&gt;Full article body in clean HTML (or Markdown with \"content_format\": \"markdown\").&lt;/p&gt;",
  "category": "Self-Defense",
  "featured_image": "https://example.com/image.jpg  (or an existing media path like articles/photo.jpg)",
  "faq": [
    {"question": "A question?", "answer": "Its answer."}
  ],
  "publish_status": "draft | scheduled | published",
  "publish_date": "2026-08-01 09:00",
  "translation_of": "slug-of-the-other-language-version",
  "provider": "claude"
}</pre>
@endverbatim
    </x-filament::section>

    {{-- ============ لاگ ایمپورت‌های اخیر ============ --}}
    @php($logs = \App\Models\ImportLog::with('user')->latest()->take(15)->get())
    @if($logs->isNotEmpty())
        <x-filament::section collapsible>
            <x-slot name="heading">🗂 Recent imports</x-slot>
            <div style="overflow-x:auto">
                <table style="width:100%;font-size:.82rem;border-collapse:collapse">
                    <thead>
                        <tr style="text-align:left;border-bottom:1px solid #e5e5e5">
                            <th style="padding:.5rem .6rem">When</th>
                            <th style="padding:.5rem .6rem">By</th>
                            <th style="padding:.5rem .6rem">Provider</th>
                            <th style="padding:.5rem .6rem">Format</th>
                            <th style="padding:.5rem .6rem">Result</th>
                            <th style="padding:.5rem .6rem">Article</th>
                            <th style="padding:.5rem .6rem">Lang</th>
                            <th style="padding:.5rem .6rem">FAQs</th>
                            <th style="padding:.5rem .6rem">Images</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($logs as $log)
                        <tr style="border-bottom:1px solid #f0f0f0">
                            <td style="padding:.45rem .6rem;white-space:nowrap">{{ $log->created_at->format('Y-m-d H:i') }}</td>
                            <td style="padding:.45rem .6rem">{{ $log->user->name ?? '—' }}</td>
                            <td style="padding:.45rem .6rem">{{ $log->ai_provider ?? '—' }}</td>
                            <td style="padding:.45rem .6rem">{{ $log->format ?? '—' }}</td>
                            <td style="padding:.45rem .6rem">
                                @if($log->status === 'imported')
                                    <span style="color:#15803d;font-weight:600">Imported</span>
                                @else
                                    <span style="color:#b91c1c;font-weight:600" title="{{ implode(' | ', $log->errors ?? []) }}">Failed</span>
                                @endif
                            </td>
                            <td style="padding:.45rem .6rem">{{ \Illuminate\Support\Str::limit($log->article_title, 40) ?? '—' }}</td>
                            <td style="padding:.45rem .6rem">{{ strtoupper($log->locale ?? '') ?: '—' }}</td>
                            <td style="padding:.45rem .6rem">{{ $log->faq_count }}</td>
                            <td style="padding:.45rem .6rem">{{ $log->image_count }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif

</x-filament-panels::page>
