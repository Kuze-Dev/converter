<x-filament-panels::page>
    {{ $this->form }}

    <x-filament::button wire:click="askAI" color="primary" class="mt-4 w-full">
        Ask OpenAI
    </x-filament::button>

    @if($response)
        <div class="mt-6 p-4 bg-gray-100 rounded-lg">
            <h3 class="font-semibold">AI Response:</h3>
            <p class="mt-2 whitespace-pre-line">{{ $response }}</p>
        </div>
    @endif
</x-filament-panels::page>