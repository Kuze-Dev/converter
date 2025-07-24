<?php

namespace App\Repositories;

use App\Models\ImportedData;

class ImportedDataRepository
{
    public function create(array $data): ImportedData
    {
        return ImportedData::create($data);
    }
}