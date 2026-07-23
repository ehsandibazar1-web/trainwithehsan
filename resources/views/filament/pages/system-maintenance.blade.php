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

            <x-filament::button color="success" wire:click="optimizeCache" icon="heroicon-o-bolt">
                Rebuild speed cache
            </x-filament::button>

            <x-filament::button color="gray" wire:click="publishStaticAssets" icon="heroicon-o-paper-airplane">
                Publish design files
            </x-filament::button>
        </div>

        <p class="mt-3 text-sm" style="color:#6b7280">
            "Publish design files" copies fonts, styles and design images to the public website folder — click it after every "Update from Remote", so new design files actually reach visitors.
        </p>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Edge caching (Cloudflare)</x-slot>
        <x-slot name="description">
            Lets Cloudflare serve public pages (home, blog, articles, about) straight from its edge, so they load much faster for visitors. Only anonymous visitors get the cached copy — anyone logged in, and the contact/newsletter forms, keep working normally. Turn this on only after the Cloudflare "Cache Rule" is set up, then purge Cloudflare's cache once.
        </x-slot>

        @if ($this->edgeCacheEnabled)
            <div class="flex items-center gap-2 text-sm" style="color:#15803d">
                <x-filament::icon icon="heroicon-o-check-circle" class="h-5 w-5" />
                <span>On — public pages allow Cloudflare edge caching.</span>
            </div>
        @else
            <div class="flex items-center gap-2 text-sm" style="color:#6b7280">
                <x-filament::icon icon="heroicon-o-pause-circle" class="h-5 w-5" />
                <span>Off — the site serves fully dynamic HTML (the default, safest state).</span>
            </div>
        @endif

        <div class="mt-4 flex flex-wrap gap-3">
            @if ($this->edgeCacheEnabled)
                <x-filament::button color="danger" wire:click="disableEdgeCache" icon="heroicon-o-x-circle">
                    Turn edge caching off
                </x-filament::button>
            @else
                <x-filament::button color="success" wire:click="enableEdgeCache" icon="heroicon-o-bolt">
                    Turn edge caching on
                </x-filament::button>
            @endif
        </div>

        <p class="mt-3 text-sm" style="color:#6b7280">
            If a form ever misbehaves after turning this on, click "Turn edge caching off" and purge Cloudflare's cache — the site returns to its normal dynamic behavior immediately.
        </p>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Database backup</x-slot>
        <x-slot name="description">
            The database holds everything on this site — articles, pages, all settings. A snapshot is taken automatically every night and the last {{ \App\Services\Backup\DatabaseBackupService::KEEP }} copies are kept on the server. It's a good habit to also click "Download latest backup" now and then, so a copy lives safely on your own computer.
        </x-slot>

        @php($backupStatus = $this->databaseBackupStatus)
        @if ($backupStatus['latest'])
            <div class="flex items-center gap-2 text-sm" style="color:#15803d">
                <x-filament::icon icon="heroicon-o-check-circle" class="h-5 w-5" />
                <span>
                    Last backup: <strong>{{ \Illuminate\Support\Carbon::createFromTimestamp($backupStatus['latest']['created_at'])->diffForHumans() }}</strong>
                    ({{ number_format($backupStatus['latest']['size'] / 1024, 1) }} KB) — {{ $backupStatus['count'] }} {{ \Illuminate\Support\Str::plural('copy', $backupStatus['count']) }} kept on the server.
                </span>
            </div>
        @else
            <div class="flex items-start gap-2 text-sm" style="color:#b91c1c">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-5 w-5 shrink-0" />
                <span><strong>No backup exists yet.</strong> Click "Backup now" to create the first one. Automatic nightly backups need the site's scheduler to be active — the same one that publishes scheduled articles.</span>
            </div>
        @endif

        <div class="mt-4 flex flex-wrap gap-3">
            <x-filament::button wire:click="backupDatabase" icon="heroicon-o-archive-box-arrow-down">
                Backup now
            </x-filament::button>

            @if ($backupStatus['latest'])
                <x-filament::button color="gray" wire:click="downloadLatestBackup" icon="heroicon-o-arrow-down-tray">
                    Download latest backup
                </x-filament::button>
            @endif
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Media storage link</x-slot>
        <x-slot name="description">
            This checks that uploaded images and files are actually reachable on the web. It fetches a small test file through its public URL, so it reflects what visitors really see — however your host serves files.
        </x-slot>

        @if ($this->storageLinkHealthy)
            <div class="flex items-center gap-2 text-sm" style="color:#15803d">
                <x-filament::icon icon="heroicon-o-check-circle" class="h-5 w-5" />
                <span>Working — uploaded files are publicly reachable.</span>
            </div>
        @else
            <div class="flex items-start gap-2 text-sm" style="color:#b91c1c">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-5 w-5 shrink-0" />
                <span><strong>Could not confirm uploaded files are reachable.</strong> If images across the site look fine, you can ignore this. If images are actually missing, the storage link (<code>{{ $this->storageLinkPath }}</code>) likely needs setting up — try the button below, or ask your host to run <code>php artisan storage:link</code> / create the link, since some hosts block it.</span>
            </div>

            <div class="mt-4">
                <x-filament::button wire:click="linkStorage" icon="heroicon-o-link">
                    Try to fix media storage link
                </x-filament::button>
            </div>
        @endif
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Image optimization (WebP)</x-slot>
        <x-slot name="description">
            New image uploads are automatically converted to a smaller WebP version for faster loading. This needs the server's image library (PHP GD) to support WebP.
        </x-slot>

        @if ($this->imageWebpSupported)
            <div class="flex items-center gap-2 text-sm" style="color:#15803d">
                <x-filament::icon icon="heroicon-o-check-circle" class="h-5 w-5" />
                <span>Working — WebP conversion is available on this server.</span>
            </div>
        @else
            <div class="flex items-start gap-2 text-sm" style="color:#b91c1c">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-5 w-5 shrink-0" />
                <span><strong>This server's image library (GD) has no WebP support</strong> (<code>imagewebp()</code> is unavailable or disabled). Uploads are stored as their original file and still display correctly — but no smaller WebP version is generated. Ask your host to enable WebP support in the PHP GD extension; after that, re-upload the image (or replace it) to generate the WebP.</span>
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
