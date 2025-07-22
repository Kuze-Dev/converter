<?php

namespace App\Filament\Resources\ImportedDataResource\Pages;

use Filament\Actions;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\ImportedDataResource;

class ListImportedData extends ListRecords
{
    protected static string $resource = ImportedDataResource::class;

    protected function getHeaderActions(): array
    {
            return [
                ExportAction::make()
                    ->label('Export CSV')
                    ->icon('heroicon-m-arrow-down-tray'),


            ];
    }


    public function canCreate(): bool
    {
        return false;
    }
}
