<?php

namespace App\Filament\Imports;

use App\Models\Taxonomy;
use Illuminate\Support\Str;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Models\Import;

class TaxonomyImporter extends Importer
{
    protected static ?string $model = Taxonomy::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('original_data')
                ->rules(['max:255']),
            ImportColumn::make('converted_value')
                ->rules(['max:255']),
        ];
    }

    public function resolveRecord(): ?Taxonomy
    {
        return new Taxonomy();
    }

    public function beforeSave(): void
    {
        if (empty($this->record->converted_value) && !empty($this->record->original_data)) {
            $this->record->converted_value = Str::slug($this->record->original_data);
        }
    }    

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your taxonomy import has completed and ' . number_format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }
}
