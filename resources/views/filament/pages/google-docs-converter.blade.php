<x-filament::page>
    <form wire:submit.prevent="convertToHtml">
        <x-filament::input
            label="Google Docs URL"
            wire:model="docUrl"
            placeholder="Paste your Google Docs URL here"
        />

        <x-filament::button type="submit" class="mt-2">Convert to HTML</x-filament::button>
    </form>

    @if($htmlContent)
        <div class="mt-5">
            <h2 class="text-lg font-bold mb-2">Converted HTML</h2>
            <div class="p-4 border rounded bg-gray-50">
                {!! $htmlContent !!}
            </div>
        </div>
    @endif
</x-filament::page>
