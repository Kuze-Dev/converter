<?php

namespace App\Filament\Resources\MapDataResource\Pages;

use App\Filament\Resources\MapDataResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewMapData extends ViewRecord
{
    protected static string $resource = MapDataResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
