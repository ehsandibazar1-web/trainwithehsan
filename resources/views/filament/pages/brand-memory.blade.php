<x-filament-panels::page>
    <p style="margin-bottom: 1rem; color: rgb(107 114 128);">
        Every enabled section below is automatically included in every AI Studio prompt (generate, improve,
        translate, chat) — no need to paste brand instructions each time. Disable a section to leave it out
        without deleting its content. Click "View version history" inside a section to see and restore
        previous versions.
    </p>

    @if ($this->previewPromptResult)
        <div style="border: 1px solid rgb(229 231 235); border-radius: 0.5rem; padding: 1rem; margin-bottom: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                <strong>Preview Prompt — exactly what is sent to the AI</strong>
                <button type="button" wire:click="closePreview" style="font-size: 0.8rem; background: none; border: none; cursor: pointer; text-decoration: underline;">
                    Close
                </button>
            </div>
            <pre style="white-space: pre-wrap; font-family: ui-monospace, monospace; font-size: 0.8rem; background: rgb(249 250 251); padding: 0.75rem; border-radius: 0.375rem;">{{ $this->previewPromptResult }}</pre>
        </div>
    @endif

    @if ($this->historySection)
        <div style="border: 1px solid rgb(229 231 235); border-radius: 0.5rem; padding: 1rem; margin-bottom: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                <strong>History — {{ $this->historySection->label }}</strong>
                <button type="button" wire:click="closeHistory" style="font-size: 0.8rem; background: none; border: none; cursor: pointer; text-decoration: underline;">
                    Close
                </button>
            </div>

            @include('filament.pages.partials.brand-memory-history', ['activities' => $this->historyActivities])
        </div>
    @endif

    <form wire:submit="save">
        {{ $this->form }}

        <div style="margin-top: 1.5rem;">
            <x-filament::button type="submit">
                Save Brand Memory
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
