<?php

namespace App\Filament\Resources\ImportedDataResource\Pages;

use App\Filament\Resources\ImportedDataResource;
use App\Models\ImportedData;
use Filament\Actions;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListImportedData extends ListRecords
{
    protected static string $resource = ImportedDataResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export_csv')
                ->label('Export CSV in Batch')
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
                    return $this->exportCsv((int) $data['limit']);
                })
                ->requiresConfirmation()
                ->color('primary'),
        ];
    }

    /**
     * Export CSV in batches and loop when all rows are done.
     */
    protected function exportCsv(int $limit): StreamedResponse
    {
        $totalRecords = ImportedData::count();

        if ($totalRecords === 0) {
            abort(404, 'No records available for export.');
        }

        // Get the current batch number from session or default to 0
        $currentBatch = session('export_batch_number', 0);

        // Calculate offset based on current batch and limit
        $offset = $currentBatch * $limit;

        // If we've reached the end, loop back to the beginning
        if ($offset >= $totalRecords) {
            $offset = 0;
            $currentBatch = 0;
        }

        // Get records for the current batch
        $records = ImportedData::offset($offset)
            ->limit($limit)
            ->get();

        // Batch number for naming (1-based)
        $batchDisplay = $currentBatch + 1;

        $fileName = "imported_data_batch_{$batchDisplay}_" . now()->format('Ymd_His') . ".csv";

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$fileName\"",
        ];

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

        $callback = function () use ($records, $columns) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);

            foreach ($records as $record) {
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
        };

        // Store the next batch number in session
        session(['export_batch_number' => $currentBatch + 1]);

        return response()->stream($callback, 200, $headers);
    }

    public function canCreate(): bool
    {
        return false;
    }
}
