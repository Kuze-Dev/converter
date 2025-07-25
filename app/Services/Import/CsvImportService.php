<?php

namespace App\Services\Import;

use App\Dto\CsvData;
use App\Dto\ImportResult;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use App\Exceptions\ImportException;
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;
use App\Services\Import\DataMapper;
use App\Services\Import\CsvProcessor;
use App\Services\Import\FileResolver;
use App\Models\ImportedImageUrl;
use App\Repositories\ImportedDataRepository;
use App\Services\Import\TaxonomyExtractor;

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
        ImportedDataRepository $repository,
    ) {
        $this->fileResolver = $fileResolver;
        $this->csvProcessor = $csvProcessor;
        $this->dataMapper = $dataMapper;
        $this->taxonomyExtractor = $taxonomyExtractor;
        $this->repository = $repository;
    }

    public function import(array $data): ImportResult
    {
        try {
            $filePath = $this->fileResolver->resolve($data['uploaded_file']);

            $mappings = collect($data['field_definitions'] ?? []);
            if ($mappings->isEmpty()) {
                throw new ImportException('No field mappings defined. Please map CSV columns to JSON fields.');
            }

            $csvData = $this->csvProcessor->process($filePath);

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

        foreach ($csvData->getRows() as $rowIndex => $row) {
            try {
                $importData = $this->dataMapper->mapRow($row, $mappings, $csvData->getHeaders());
                $taxonomyTerms = $this->taxonomyExtractor->extract($row);

                $baseUrl = $importConfig['base_url'] ?? 'https://islandproperty.com';

                // ✅ Clean and convert image URLs
                if (isset($importData['media']['images']) && is_array($importData['media']['images'])) {
                    $cleanedImages = [];

                    foreach ($importData['media']['images'] as $path) {
                        $clean = trim($path);

                        if (!empty($clean) && strtolower($clean) !== 'null') {
                            $fullUrl = rtrim($baseUrl, '/') . '/' . ltrim($clean, '/');

                            // Save to cleaned list
                            $cleanedImages[] = $fullUrl;

                            // Store in image URL table
                            ImportedImageUrl::create([
                                'image_url' => $fullUrl,
                            ]);
                        }
                    }

                    // ✅ Inject updated image URLs into media.images
                    $importData['media']['images'] = $cleanedImages;
                }

                $record = $this->repository->create([
                    'data' => json_encode($importData),
                    'content' => $importConfig['content'] ?? null,
                    'title' => $row['title'] ?? null,
                    'route_url' => $row['route_url'] ?? null,
                    'status' => $this->parseStatus($row['status'] ?? null),
                    'sites' => $row['sites'] ?? null,
                    'locale' => $importConfig['locale'] ?? 'en',
                    'taxonomy_terms' => implode(', ', $taxonomyTerms),
                    'published_at' => $this->parseDate($row['published_at'] ?? null),
                ]);

                $result->addSuccess($record);

            } catch (\Exception $e) {
                $result->addError($rowIndex + 2, $e->getMessage());
            }
        }

        return $result;
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
        }

        Notification::make()
            ->title('Import Completed')
            ->body($message)
            ->success()
            ->send();

        if ($result->hasErrors()) {
            Log::warning('Import errors:', $result->getErrors());
        }
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
