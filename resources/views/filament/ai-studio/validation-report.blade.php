<div style="font-size:.88rem;line-height:1.9">
    <div style="display:flex;gap:1.5rem;flex-wrap:wrap;color:#555;margin-bottom:1rem">
        <span><strong>Result:</strong> {{ $log->isRolledBack() ? 'Rolled back' : ucfirst($log->status) }}</span>
        <span><strong>By:</strong> {{ $log->user->name ?? '—' }}</span>
        <span><strong>AI provider:</strong> {{ $log->ai_provider ?? '—' }}</span>
        <span><strong>Format:</strong> {{ $log->format ?? '—' }}</span>
        @if($log->article_title)<span><strong>Article:</strong> {{ $log->article_title }}</span>@endif
        <span><strong>FAQs:</strong> {{ $log->faq_count }}</span>
        <span><strong>Images:</strong> {{ $log->image_count }}</span>
    </div>

    @if($log->isRolledBack())
        <div style="border:1px solid #f5d0a9;background:#fdf6ec;border-radius:8px;padding:.7rem 1rem;margin-bottom:1rem;color:#92600c">
            Rolled back on {{ $log->rolled_back_at->format('Y-m-d H:i') }}
            by {{ $log->rolledBackBy->name ?? '—' }} — the imported article was deleted.
        </div>
    @endif

    @if(!empty($log->errors))
        <h4 style="font-weight:700;margin-bottom:.25rem;color:#b91c1c">Problems found ({{ count($log->errors) }})</h4>
        <ul style="padding-left:1.25rem;list-style:disc;color:#b91c1c;margin-bottom:1rem">
            @foreach($log->errors as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    @endif

    @if(!empty($log->warnings))
        <h4 style="font-weight:700;margin-bottom:.25rem;color:#92700c">Notes ({{ count($log->warnings) }})</h4>
        <ul style="padding-left:1.25rem;list-style:disc;color:#92700c;margin-bottom:1rem">
            @foreach($log->warnings as $warning)<li>{{ $warning }}</li>@endforeach
        </ul>
    @endif

    @if(empty($log->errors) && empty($log->warnings))
        <p style="color:#15803d">✓ No validation problems or notes — everything mapped cleanly.</p>
    @endif
</div>
