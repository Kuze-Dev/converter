<x-filament::page>
    <form wire:submit.prevent="submit" class="space-y-4">
        {{ $this->form }}

        <x-filament::button type="submit">
            📂 Upload & Validate
        </x-filament::button>
    </form>

    <div class="mt-6">
        @if ($headerValid)
            @if ($cleaned)
                <div class="text-green-600 font-semibold">✅ Cleaned CSV saved successfully.</div>
            @elseif (empty($errors))
                <div class="text-green-600 font-semibold">✅ All rows valid!</div>
            @else
                <div>
                    <h4 class="text-red-600 font-bold mt-4">Errors Found:</h4>
                    <ul class="list-disc list-inside text-red-600 space-y-1">
                        @foreach ($errors as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>

                    <form wire:submit.prevent="cleanCsv" class="mt-4">
                        <x-filament::button color="danger" type="submit">
                            🧹 Remove Invalid Rows and Save
                        </x-filament::button>
                    </form>
                </div>
            @endif
        @elseif ($errors)
            <div class="text-red-600 font-semibold mt-4">
                <ul class="list-disc list-inside">
                    @foreach ($errors as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</x-filament::page>
