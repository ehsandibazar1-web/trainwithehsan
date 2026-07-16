<x-filament-panels::page>
    <style>
        .ai-edit-layout{display:flex;gap:1.5rem;align-items:flex-start}
        .ai-edit-main{flex:1;min-width:0}

        .ai-edit-sidebar{width:400px;flex-shrink:0;position:sticky;top:1rem;max-height:calc(100vh - 2rem);overflow-y:auto;border:1px solid rgb(229 231 235);border-radius:.75rem;background:#fff;padding:1rem}
        .ai-edit-sidebar.closed{display:none}
        .ai-edit-sidebar-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem}
        .ai-edit-sidebar-header .heading{font-size:.85rem;font-weight:600;color:#111827}
        .ai-edit-sidebar-collapse{background:none;border:none;color:#6b7280;cursor:pointer;font-size:.75rem;padding:.2rem .4rem}

        .ai-edit-backdrop{display:none}

        @media (prefers-color-scheme: dark) {
            .ai-edit-sidebar{background:#1f2937;border-color:#374151}
            .ai-edit-sidebar-header .heading{color:#f9fafb}
        }

        @media (max-width: 1024px) {
            .ai-edit-layout{display:block}
            .ai-edit-sidebar{position:fixed;left:0;right:0;bottom:0;top:auto;width:auto;max-height:75vh;border-radius:1rem 1rem 0 0;transform:translateY(100%);transition:transform .25s ease;z-index:40;box-shadow:0 -4px 20px rgba(0,0,0,.15)}
            .ai-edit-sidebar.open{display:block;transform:translateY(0)}
            .ai-edit-sidebar.closed{display:block;transform:translateY(100%)}
            .ai-edit-backdrop{display:block;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:39;opacity:0;pointer-events:none;transition:opacity .25s}
            .ai-edit-backdrop.open{opacity:1;pointer-events:auto}
        }
    </style>

    <div
        x-data="{ open: (localStorage.getItem('aiSidebarOpen') ?? 'true') === 'true' }"
        x-init="$watch('open', value => localStorage.setItem('aiSidebarOpen', value))"
        x-on:toggle-ai-sidebar.window="open = ! open"
    >
        <div class="ai-edit-backdrop" x-bind:class="open ? 'open' : 'closed'" x-on:click="open = false"></div>

        <div class="ai-edit-layout">
            <div class="ai-edit-main">
                {{ $this->content }}
            </div>

            <aside class="ai-edit-sidebar" x-bind:class="open ? 'open' : 'closed'">
                <div class="ai-edit-sidebar-header">
                    <span class="heading">AI Assistant</span>
                    <button type="button" class="ai-edit-sidebar-collapse" x-on:click="open = false">Collapse ✕</button>
                </div>

                @livewire('ai-assistant-panel', [
                    'recordType' => 'Article',
                    'recordId' => $record->id,
                    'standalone' => false,
                ])
            </aside>
        </div>
    </div>
</x-filament-panels::page>
