<?php

namespace App\Services\Import;

use App\Dto\CsvData;
use App\Dto\ImportResult;
use Illuminate\Support\Carbon;
use App\Exceptions\ImportException;
use App\Services\Import\DataMapper;
use Illuminate\Support\Facades\Log;
use App\Services\Import\CsvProcessor;
use App\Services\Import\FileResolver;
use Filament\Notifications\Notification;
use App\Repositories\ImportedDataRepository;
use Illuminate\Support\Collection;

class CsvImportService
{
    protected FileResolver $fileResolver;
    protected CsvProcessor $csvProcessor;
    protected DataMapper $dataMapper;
    protected TaxonomyExtractor $taxonomyExtractor;
    protected ImportedDataRepository $repository;

    public function __construct(
        FileResolver $fileResolver,
        CsvProcessor $csvProcessor,
        DataMapper $dataMapper,
        TaxonomyExtractor $taxonomyExtractor,
        ImportedDataRepository $repository
    ) {
        $this->fileResolver = $fileResolver;
        $this->csvProcessor = $csvProcessor;
        $this->dataMapper = $dataMapper;
        $this->taxonomyExtractor = $taxonomyExtractor;
        $this->repository = $repository;
    }

    public $content = '';
    public function import(array $data): ImportResult
    {
        try {
            $filePath = $this->fileResolver->resolve($data['uploaded_file']);

            $mappings = collect($data['field_definitions'] ?? []);
            if ($mappings->isEmpty()) {
                throw new ImportException('No field mappings defined. Please map CSV columns to JSON fields.');
            }

            $csvData = $this->csvProcessor->process($filePath);

            $titleColumn = $data['title'] ?? null;
            if ($titleColumn && !in_array($titleColumn, $csvData->getHeaders())) {
                throw new ImportException("Selected title column '{$titleColumn}' not found in CSV headers.");
            }

            $result = $this->importRows($csvData, $mappings, $data);

            $this->sendNotification($result);

            return $result;

        } catch (\Throwable $e) {
            Log::error('Import failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            $this->sendErrorNotification($e->getMessage());
            throw $e;
        }
    }

    protected function importRows(CsvData $csvData, Collection $mappings, array $importConfig): ImportResult
    {
        $result = new ImportResult();
        $headers = $csvData->getHeaders();
        $titleColumn = $importConfig['title'] ?? null;
        $titleColumnIndex = $titleColumn ? array_search($titleColumn, $headers) : false;

        foreach ($csvData->getRows() as $rowIndex => $row) {
            try {
                $titleValue = null;

                if ($titleColumn) {
                    if (is_array($row) && isset($row[$titleColumn])) {
                        $titleValue = trim($row[$titleColumn]);
                    } elseif ($titleColumnIndex !== false && is_array($row) && isset($row[$titleColumnIndex])) {
                        $titleValue = trim($row[$titleColumnIndex]);
                    }

                    if (empty($titleValue)) {
                        continue;
                    }
                }

                $importData = $this->dataMapper->mapRow($row, $mappings, $headers);
                $taxonomyTerms = $this->taxonomyExtractor->extract($row);

                $record = $this->repository->create([
                    'data' => json_encode($importData),
                    'content' => $importConfig['content'] ?? null,
                    'title' => $titleValue,
                    'route_url' => $this->generateRouteUrl($titleValue, $row, $headers, $importConfig),
                    'status' => $this->parseStatus($this->getColumnValue($row, $headers, 'status')) ?? 1,
                    'sites' => $this->getColumnValue($row, $headers, 'sites') ?? null,
                    'locale' => $importConfig['locale'] ?? 'en',
                    'taxonomy_terms' => implode(', ', $taxonomyTerms),
                    'published_at' => $this->parseDate($this->getColumnValue($row, $headers, 'published_at')),
                ]);

                $result->addSuccess($record);
            } catch (\Exception $e) {
                Log::error("Error processing row " . ($rowIndex + 2) . ": " . $e->getMessage());
                $result->addError($rowIndex + 2, $e->getMessage());
            }
        }

        return $result;
    }

    protected function getColumnValue(array $row, array $headers, string $columnName): ?string
    {
        if (isset($row[$columnName])) {
            $value = trim($row[$columnName]);
            return $value ?: null;
        }

        $columnIndex = array_search($columnName, $headers);
        if ($columnIndex !== false && isset($row[$columnIndex])) {
            $value = trim($row[$columnIndex]);
            return $value ?: null;
        }

        return null;
    }

    protected function generateRouteUrl(?string $title, array $row, array $headers ,array $importConfig): ?string
    {
        $routeUrl = $this->getColumnValue($row, $headers, 'route_url');
        if ($routeUrl) {
            return $routeUrl;
        }

        if ($title) {
            return $importConfig['content'] . '/' . strtolower(str_replace([' ', '_'], '-', preg_replace('/[^A-Za-z0-9\s_-]/', '', $title)));
        }

        return null;
    }

    protected function parseStatus(?string $status): bool
    {
        if ($status === null) return true;
        return in_array(strtolower($status), ['true', '1', 'yes', 'on', 'active']);
    }

    protected function parseDate(?string $date): Carbon
    {
        return $date ? Carbon::parse($date) : now();
    }

    protected function sendNotification(ImportResult $result): void
    {
        $message = "Successfully imported {$result->getSuccessCount()} records.";
        if ($result->hasErrors()) {
            $message .= " {$result->getErrorCount()} rows had errors.";
            Log::warning('Import errors:', $result->getErrors());
        }

        Notification::make()
            ->title('Import Completed')
            ->body($message)
            ->success()
            ->send();
    }

    protected function sendErrorNotification(string $message): void
    {
        Notification::make()
            ->title('Import Failed')
            ->body($message)
            ->danger()
            ->send();
    }
}
