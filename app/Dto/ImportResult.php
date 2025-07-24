<?php 

namespace App\Dto;
class ImportResult
{
    protected array $successes = [];
    protected array $errors = [];

    public function addSuccess($record): void
    {
        $this->successes[] = $record;
    }

    public function addError(int $row, string $message): void
    {
        $this->errors[] = "Row {$row}: {$message}";
    }

    public function getSuccessCount(): int
    {
        return count($this->successes);
    }

    public function getErrorCount(): int
    {
        return count($this->errors);
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}