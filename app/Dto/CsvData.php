<?php

namespace App\Dto;

class CsvData
{
    public function __construct(
        protected array $headers,
        protected array $rows
    ) {}

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getRows(): array
    {
        return $this->rows;
    }
}
