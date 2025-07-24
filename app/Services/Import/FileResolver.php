<?php

namespace App\Services\Import;
use App\Exceptions\ImportException;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class FileResolver
{
    public function resolve($uploadedFile): string
    {
        $filePath = null;
        
        if ($uploadedFile instanceof TemporaryUploadedFile) {
            $filePath = $uploadedFile->getRealPath();
        } elseif (is_string($uploadedFile)) {
            $filePath = $this->findStringFilePath($uploadedFile);
        } elseif (is_array($uploadedFile) && !empty($uploadedFile)) {
            $filePath = $this->findArrayFilePath($uploadedFile);
        }
        
        if (!$filePath || !file_exists($filePath)) {
            throw new ImportException('CSV file not found for import. File type: ' . gettype($uploadedFile));
        }
        
        return $filePath;
    }

    protected function findStringFilePath(string $uploadedFile): ?string
    {
        $possiblePaths = [
            storage_path('app/livewire-tmp/' . $uploadedFile),
            storage_path('app/public/' . $uploadedFile),
            storage_path('app/' . $uploadedFile),
            Storage::disk('public')->path($uploadedFile),
            $uploadedFile
        ];
        
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        
        return null;
    }

    protected function findArrayFilePath(array $uploadedFile): ?string
    {
        $firstFile = $uploadedFile[0];
        
        if ($firstFile instanceof TemporaryUploadedFile) {
            return $firstFile->getRealPath();
        } elseif (is_string($firstFile)) {
            return storage_path('app/livewire-tmp/' . $firstFile);
        }
        
        return null;
    }
}