<x-filament-panels::page>
    @livewire('ai-assistant-panel', [
        'recordType' => $recordType,
        'recordId' => $record->id,
        'standalone' => true,
    ])
</x-filament-panels::page>
