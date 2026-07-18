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

    <x-filament::section>
        <x-slot name="heading">Media storage link</x-slot>
        <x-slot name="description">
            Uploaded images and files are served through a link from the public web folder. If this ever shows a problem, uploads still save but images across the site will fail to display — tell a developer, it needs <code>php artisan storage:link</code> on the server.
        </x-slot>

        @if ($this->storageLinkHealthy)
            <div class="flex items-center gap-2 text-sm" style="color:#15803d">
                <x-filament::icon icon="heroicon-o-check-circle" class="h-5 w-5" />
                <span>Working — uploaded files are publicly reachable.</span>
            </div>
        @else
            <div class="flex items-center gap-2 text-sm" style="color:#b91c1c">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-5 w-5" />
                <span>Not set up — the link at <code>{{ $this->storageLinkPath }}</code> is missing or points to the wrong place. Images will not display until a developer runs <code>php artisan storage:link</code>.</span>
            </div>
        @endif
    </x-filament::section>

    @if ($lastOutput)
        <x-filament::section>
            <x-slot name="heading">Last result</x-slot>

            <pre class="whitespace-pre-wrap text-sm">{{ $lastOutput }}</pre>
        </x-filament::section>
    @endif
</x-filament-panels::page>
