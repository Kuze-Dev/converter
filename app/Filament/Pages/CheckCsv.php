<?php

namespace App\Filament\Pages;

use App\Models\CsvImportLog;
use Filament\Forms;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;

class CheckCsv extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';
    protected static string $view = 'filament.pages.check-csv';

    public ?string $uploadedPath = null;


    public array $requiredHeaders = [
        'data', 'content', 'title', 'status', 'locale', 'taxonomy_terms', 'route_url', 'published_at', 'sites'
    ];

    public array $errors = [];
    public array $validRows = [];
    public bool $headerValid = false;
    public bool $cleaned = false;
    public ?CsvImportLog $log = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\FileUpload::make('uploaded_file')
                ->label('Upload CSV File')
                ->acceptedFileTypes(['text/csv', 'text/plain'])
                ->required()
                ->disk('local')
                ->directory('csv_uploads')
                ->preserveFilenames()
                ->openable()
                ->downloadable()
                ->maxFiles(1),
        ]);
    }

    public function submit(): void
{
    $this->errors = [];
    $this->validRows = [];
    $this->headerValid = false;
    $this->cleaned = false;

    $state = $this->form->getState();

    Log::info('🔍 Form state retrieved in submit()', ['state' => $state]);

    $uploadedFilePath = $state['uploaded_file'] ?? null;

    if (!$uploadedFilePath) {
        $this->errors[] = '❌ No file uploaded.';
        Log::warning('⚠️ No file uploaded during CSV validation.');
        return;
    }

    $this->uploadedPath = $uploadedFilePath;

    Log::info('📁 Uploaded file path set', ['uploadedPath' => $this->uploadedPath]);

    $this->validateCsv();

    Log::info('✅ CSV validation triggered.', [
        'user_id' => auth()->id(),
        'uploadedPath' => $this->uploadedPath,
        'errorsCount' => count($this->errors),
        'validRowsCount' => count($this->validRows),
    ]);
}

    public function cleanCsv(): void
    {
        if (!$this->uploadedPath) {
            $this->errors[] = '❌ No file to clean.';
            return;
        }

        $filePath = Storage::disk('local')->path($this->uploadedPath);

        if (!file_exists($filePath)) {
            $this->errors[] = "❌ File not found: {$this->uploadedPath}";
            return;
        }

        $fp = fopen($filePath, 'w');
        if (!$fp) {
            $this->errors[] = "❌ Could not open file for writing.";
            return;
        }

        fputcsv($fp, $this->requiredHeaders);
        foreach ($this->validRows as $row) {
            fputcsv($fp, $row);
        }
        fclose($fp);
        $this->cleaned = true;
    }

    protected function validateCsv(): void
    {
        if (empty($this->uploadedPath)) {
            $this->errors[] = "❌ Invalid file path.";
            return;
        }

        $filePath = Storage::disk('local')->path($this->uploadedPath);

        if (!file_exists($filePath)) {
            $this->errors[] = "❌ File not found: {$this->uploadedPath}";
            $this->errors[] = "❌ Full path checked: {$filePath}";
            return;
        }

        $fp = fopen($filePath, 'r');
        if (!$fp) {
            $this->errors[] = "❌ Could not open file for reading.";
            return;
        }

        $header = fgetcsv($fp);

        if ($header !== $this->requiredHeaders) {
            $this->errors[] = "❌ Header mismatch in file.";
            $this->errors[] = "❌ Expected: " . implode(', ', $this->requiredHeaders);
            $this->errors[] = "❌ Found: " . implode(', ', $header ?: []);
            fclose($fp);
            return;
        }

        $this->headerValid = true;
        $lineNumber = 1;
        $invalidCount = 0;

        while (($row = fgetcsv($fp)) !== false) {
            $lineNumber++;
            $rowErrors = [];

            if ($this->isRowValid($row, $lineNumber, $rowErrors)) {
                $this->validRows[] = $row;
            } else {
                $invalidCount++;
                $this->errors = array_merge($this->errors, $rowErrors);
            }
        }

        fclose($fp);

        $this->log = CsvImportLog::create([
            'filename' => basename($this->uploadedPath),
            'path' => $this->uploadedPath,
            'valid_rows' => count($this->validRows),
            'invalid_rows' => $invalidCount,
            'errors' => $this->errors,
            'user_id' => auth()->id(),
        ]);
    }

    protected function isRowValid(array $row, int $lineNumber, array &$errors): bool
    {
        if (count($row) !== count($this->requiredHeaders)) {
            $errors[] = "⚠️ Row {$lineNumber} has incorrect column count.";
            return false;
        }

        $jsonCell = str_replace('""', '"', $row[0]);
        $decoded = json_decode($jsonCell, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $errors[] = "⚠️ Invalid JSON at row {$lineNumber}: " . json_last_error_msg();
            return false;
        }

        $title = trim($row[2]) ?: '[No Title]';

        $badExtensions = ['JPG', 'PNG', 'JPEG', 'WEBP', 'GIF'];
        if (isset($decoded['media']['images']) && is_array($decoded['media']['images'])) {
            foreach ($decoded['media']['images'] as $imageUrl) {
                foreach ($badExtensions as $ext) {
                    if (preg_match('/\.' . $ext . '$/', $imageUrl)) {
                        $errors[] = "⚠️ Row {$lineNumber} (Title: '{$title}') has image with uppercase extension.";
                        return false;
                    }
                }
            }
        }

        $locale = trim($row[4]);
        if (!in_array($locale, ['en', 'fr', 'zh', 'es'])) {
            $errors[] = "⚠️ Unexpected locale '{$locale}' at row {$lineNumber}.";
            return false;
        }

        if ($title === '[No Title]') {
            $errors[] = "⚠️ Missing title at row {$lineNumber}.";
            return false;
        }

        return true;
    }
}
