@if ($activities->isEmpty())
    <p style="color: rgb(107 114 128);">No changes recorded yet for this section.</p>
@else
    <div style="display: flex; flex-direction: column; gap: 0.75rem; max-height: 420px; overflow-y: auto;">
        @foreach ($activities as $activity)
            <div style="border: 1px solid rgb(229 231 235); border-radius: 0.5rem; padding: 0.75rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; gap: 0.5rem;">
                    <strong style="text-transform: uppercase; font-size: 0.75rem;">{{ $activity->subject?->locale ?? '?' }}</strong>
                    <span style="font-size: 0.75rem; color: rgb(107 114 128);">
                        {{ ucfirst($activity->event) }} · {{ $activity->created_at->diffForHumans() }}
                    </span>
                </div>
                <p style="margin-top: 0.375rem; white-space: pre-wrap;">{{ $activity->attribute_changes['attributes']['content'] ?? '—' }}</p>
                <button
                    type="button"
                    wire:click="restoreVersion({{ $activity->id }})"
                    wire:confirm="Restore this version? This becomes the current content."
                    style="margin-top: 0.5rem; font-size: 0.8rem; color: rgb(37 99 235); background: none; border: none; padding: 0; cursor: pointer; text-decoration: underline;"
                >
                    Restore this version
                </button>
            </div>
        @endforeach
    </div>
@endif
