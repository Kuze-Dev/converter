<?php

namespace App\Services\Import;

use App\Models\Taxonomy;

class TaxonomyExtractor
{
    protected array $taxonomyMap;

    public function __construct()
    {
        $this->taxonomyMap = Taxonomy::get()
            ->pluck('converted_value', 'original_data')
            ->mapWithKeys(fn($value, $key) => [mb_strtolower(trim($key)) => $value])
            ->toArray();
    }

    public function extract(array $data): array
    {
        $matched = [];
        $flatValues = $this->flattenArray($data);

        foreach ($flatValues as $value) {
            if (is_string($value)) {
                $terms = preg_split('/\s*,\s*/', $value);
                foreach ($terms as $term) {
                    $key = mb_strtolower(trim($term));
                    if (isset($this->taxonomyMap[$key])) {
                        $matched[] = $this->taxonomyMap[$key];
                    }
                }
            }
        }

        return array_unique($matched);
    }

    protected function flattenArray(array $data): array
    {
        $result = [];
        foreach ($data as $value) {
            if (is_array($value)) {
                $result = array_merge($result, $this->flattenArray($value));
            } else {
                $result[] = $value;
            }
        }
        return $result;
    }
}