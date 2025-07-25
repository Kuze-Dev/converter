<?php

namespace App\Services\Import;
use App\Models\MapData;
use Illuminate\Support\Collection;

class DataMapper
{
    protected array $mapData;

    public function __construct()
    {
        $this->mapData = MapData::get()
            ->pluck('mapped_data', 'original_data')
            ->mapWithKeys(fn($value, $key) => [mb_strtolower(trim($key)) => $value])
            ->toArray();
    }

    public function mapRow(array $csvRow, Collection $mappings, array $headers): array
    {
        $importData = [];
        
        foreach ($mappings as $mapping) {
            if (empty($mapping['csv_column']) || !isset($csvRow[$mapping['csv_column']])) {
                continue;
            }
            
            $value = $this->transformValue($csvRow[$mapping['csv_column']], $mapping['type']);
            $this->setNestedValue($importData, $mapping['path'], $value);
        }
        
        return $this->applyMappingRecursive($importData);
    }

    protected function transformValue($value, string $type)
    {
        $trimmed = trim((string) $value);
        
        if ($trimmed === '' || strtolower($trimmed) === 'null') {
            return null;
        }

        return match($type) {
            'integer' => (int) $value,
            'double' => (float) $value,
            'boolean' => in_array(strtolower($value), ['true', '1', 'yes', 'on']),
            'array' => json_decode($value, true) ?? explode(',', $value),
            default => $trimmed
        };
    }

    protected function setNestedValue(array &$data, string $path, $value): void
    {
        $keys = explode('.', $path);
        $current = &$data;
        
        for ($i = 0; $i < count($keys) - 1; $i++) {
            if (!isset($current[$keys[$i]])) {
                $current[$keys[$i]] = [];
            }
            $current = &$current[$keys[$i]];
        }
        
        $current[end($keys)] = $value;
    }

    protected function applyMappingRecursive(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->applyMappingRecursive($value);
            } elseif (is_string($value)) {
                $data[$key] = $this->mapStringValue($value);
            }
        }

        return $data;
    }

    protected function mapStringValue(string $value): string
    {
        if (str_contains($value, ',')) {
            $keywords = preg_split('/\s*,\s*/', $value);
            $mapped = array_map(function ($keyword) {
                $lowerKeyword = mb_strtolower(trim($keyword));
                return $this->mapData[$lowerKeyword] ?? $keyword;
            }, $keywords);
            return implode(', ', $mapped);
        }

        $lowerValue = mb_strtolower(trim($value));
        return $this->mapData[$lowerValue] ?? $value;
    }
}