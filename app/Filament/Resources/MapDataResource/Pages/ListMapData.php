<?php

namespace App\Filament\Resources\MapDataResource\Pages;

use App\Filament\Resources\MapDataResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMapData extends ListRecords
{
    protected static string $resource = MapDataResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
