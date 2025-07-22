<?php

namespace App\Filament\Resources\ImportedDataResource\Pages;

use App\Filament\Resources\ImportedDataResource;
use App\Models\ImportedData;
use Filament\Actions;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class ListImportedData extends ListRecords
{
    protected static string $resource = ImportedDataResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export_csv_batches')
                ->label('Export All in Batches (ZIP)')
                ->icon('heroicon-m-arrow-down-tray')
                ->form([
                    TextInput::make('limit')
                        ->label('Rows per batch')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(10000)
                        ->default(30)
                        ->required(),
                ])
                ->action(function (array $data) {
                    return $this->exportCsvInBatches((int) $data['limit']);
                })
                ->requiresConfirmation()
                ->color('primary'),
        ];
    }

    /**
     * Export all records into CSV batch files, zip them, and stream the ZIP.
     */
    protected function exportCsvInBatches(int $limit)
    {
        $records = ImportedData::all();
        $total = $records->count();

        if ($total === 0) {
            abort(404, 'No records found for export.');
        }

        $columns = [
            'data',
            'content',
            'title',
            'status',
            'locale',
            'taxonomy_terms',
            'route_url',
            'published_at',
            'sites',
        ];

        $batchFiles = [];
        $chunks = $records->chunk($limit);
        $tempDir = storage_path('app/export_batches_' . Str::uuid());

        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        foreach ($chunks as $index => $batch) {
            $batchNumber = $index + 1;
            $filename = "batch_{$batchNumber}.csv";
            $path = "{$tempDir}/{$filename}";

            $handle = fopen($path, 'w');
            fputcsv($handle, $columns);

            foreach ($batch as $record) {
                $row = [];

                foreach ($columns as $column) {
                    $value = $record->$column;

                    if (in_array($column, ['data', 'taxonomy_terms', 'sites'])) {
                        $value = is_array($value) || is_object($value) ? json_encode($value) : $value;
                    }

                    $row[] = $value;
                }

                fputcsv($handle, $row);
            }

            fclose($handle);
            $batchFiles[] = $path;
        }

        $zipName = 'imported_data_batches_' . now()->format('Ymd_His') . '.zip';
        $zipPath = storage_path("app/{$zipName}");

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            foreach ($batchFiles as $filePath) {
                $zip->addFile($filePath, basename($filePath));
            }
            $zip->close();
        }

        // Cleanup temp CSV files
        foreach ($batchFiles as $file) {
            unlink($file);
        }
        rmdir($tempDir);

        // Return the zip file for download and delete it after
        return response()->download($zipPath)->deleteFileAfterSend(true);
    }

    public function canCreate(): bool
    {
        return false;
    }
}
