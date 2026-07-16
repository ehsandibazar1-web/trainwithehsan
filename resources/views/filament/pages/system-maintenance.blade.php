<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Deployment tools</x-slot>
        <x-slot name="description">
            Use these after new code is deployed to the server, if a developer tells you a database update or a fresh start is needed. Safe to run any time — running them again when nothing has changed does nothing harmful.
        </x-slot>

        <div class="flex flex-wrap gap-3">
            <x-filament::button wire:click="runMigrations" icon="heroicon-o-circle-stack">
                Run pending database updates
            </x-filament::button>

            <x-filament::button color="gray" wire:click="clearCache" icon="heroicon-o-arrow-path">
                Clear cache
            </x-filament::button>
        </div>
    </x-filament::section>

    @if ($lastOutput)
        <x-filament::section>
            <x-slot name="heading">Last result</x-slot>

            <pre class="whitespace-pre-wrap text-sm">{{ $lastOutput }}</pre>
        </x-filament::section>
    @endif
</x-filament-panels::page>
