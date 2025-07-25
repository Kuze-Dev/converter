<?php

namespace App\Services\Import;

use App\Models\ImportedImageUrl;

class ImageUrlExtractorService
{
    public function extractAndStore(array $importedData, string $baseUrl): void
    {
        if (!isset($importedData['media']['images']) || !is_array($importedData['media']['images'])) {
            return;
        }

        foreach ($importedData['media']['images'] as $relativePath) {
            $cleanPath = trim($relativePath);

            if (empty($cleanPath) || str_contains(strtolower($cleanPath), 'null')) {
                continue;
            }

            $fullUrl = rtrim($baseUrl, '/') . '/' . ltrim($cleanPath, '/');

            ImportedImageUrl::create([
                'image_url' => $fullUrl,
            ]);
        }
    }
}
