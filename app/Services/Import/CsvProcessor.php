<?php

namespace App\Services\Import;

use App\Dto\CsvData;
use App\Exceptions\ImportException;

class CsvProcessor
{
    public function process(string $filePath): CsvData
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new ImportException('Could not open CSV file for reading.');
        }

        try {
            $headers = $this->readHeaders($handle);
            $rows = $this->readRows($handle, $headers);
            
            return new CsvData($headers, $rows);
        } finally {
            fclose($handle);
        }
    }

    protected function readHeaders($handle): array
    {
        $headers = fgetcsv($handle);
        if (!$headers) {
            throw new ImportException('Could not read CSV headers.');
        }

        return array_map(function($header) {
            return trim(str_replace(["\xEF\xBB\xBF", "\uFEFF"], '', $header));
        }, $headers);
    }

    protected function readRows($handle, array $headers): array
    {
        $rows = [];
        $rowNumber = 2; // Start from 2 since headers are row 1
        
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) !== count($headers)) {
                throw new ImportException("Row {$rowNumber}: Column count mismatch");
            }
            
            $rows[] = array_combine($headers, $row);
            $rowNumber++;
        }
        
        return $rows;
    }
}