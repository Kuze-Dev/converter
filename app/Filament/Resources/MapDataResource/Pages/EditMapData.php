<?php

namespace App\Filament\Resources\MapDataResource\Pages;

use App\Filament\Resources\MapDataResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMapData extends EditRecord
{
    protected static string $resource = MapDataResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
