<x-filament-panels::page>
    <p style="margin-bottom: 1rem; color: rgb(107 114 128);">
        Every enabled section below is automatically included in every AI Studio prompt (generate, improve,
        translate, chat) — no need to paste brand instructions each time. Disable a section to leave it out
        without deleting its content.
    </p>

    <form wire:submit="save">
        {{ $this->form }}

        <div style="margin-top: 1.5rem;">
            <x-filament::button type="submit">
                Save Brand Memory
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
