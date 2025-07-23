<?php

namespace App\Filament\Imports;

use App\Models\MapData;
use Illuminate\Support\Str;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Override;

class MapDataImporter extends Importer
{
    protected static ?string $model = MapData::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('original_data')
                ->rules(['max:255']),
            ImportColumn::make('mapped_data')
                ->rules(['max:255']),
        ];
    }

    public function resolveRecord(): ?MapData
    {
        return new MapData();
    }

    public function beforeSave(): void
    {
        if (empty($this->record->mapped_data) && !empty($this->record->original_data)) {
            $this->record->mapped_data = Str::slug($this->record->original_data);
        }
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your map data import has completed and ' . number_format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }
}
