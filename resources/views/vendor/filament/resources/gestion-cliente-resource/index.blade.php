{{-- resources/views/vendor/filament/resources/gestion-cliente-resource/index.blade.php --}}

<x-filament-panels::page
    @class([
        'fi-resource-list-records-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
    ])
>
    <div class="flex flex-col gap-y-6">
        <x-filament-panels::resources.tabs />

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE, scopes: $this->getRenderHookScopes()) }}

        {{ $this->table }}

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER, scopes: $this->getRenderHookScopes()) }}
    </div>
</x-filament-panels::page>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const params = new URLSearchParams(window.location.search);
        const record = params.get('record');
        const action = params.get('action');

        if (record && action === 'documentacion') {
            // Un pequeño retraso para que Filament pinte la tabla y los botones
            setTimeout(() => {
                const selector =
                  `button[wire\\:click*="mountTableAction('documentacion', '${record}')"]`;
                const btn = document.querySelector(selector);

                if (btn) {
                    btn.click();
                } else {
                    console.warn(
                      '[QR Loader] No encontré el botón documentacion para record=%s',
                      record
                    );
                }
            }, 300);
        }
    });
</script>
@endpush
