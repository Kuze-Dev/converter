<?php

namespace App\Filament\Resources\GeminiInstructionsResource\Pages;

use App\Filament\Resources\GeminiInstructionsResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGeminiInstructions extends EditRecord
{
    protected static string $resource = GeminiInstructionsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
