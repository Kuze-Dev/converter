<?php

namespace App\Filament\Resources\UserResource\Pages;

use GuzzleHttp\Client;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use PhpOffice\PhpWord\IOFactory;
use App\Filament\Resources\UserResource;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('uploadDocx')
                ->label('Upload DOCX to Create Users via Gemini AI')
                ->form([
                    FileUpload::make('upload')
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                        ->label('Word Document (.docx)')
                        ->required()
                        ->disk('local') // Ensure using local disk
                        ->directory('uploads/docs'),
                ])
                ->action(function (array $data): void {
                    $filePath = $data['upload'] ?? null;

                    if (! $filePath || ! Storage::exists($filePath)) {
                        Notification::make()
                            ->title('No file uploaded or file not found.')
                            ->danger()
                            ->send();
                        return;
                    }

                    $realPath = Storage::path($filePath);
                    $text = $this->extractTextFromDocx($realPath);
                    $users = $this->sendToGemini($text);

                    if (! is_array($users)) {
                        Notification::make()
                            ->title('Gemini returned invalid format.')
                            ->danger()
                            ->send();
                        return;
                    }

                    $created = 0;

                    foreach ($users as $user) {
                        if (! isset($user['name'], $user['email'], $user['password'])) {
                            continue;
                        }

                        if (User::where('email', $user['email'])->exists()) {
                            continue;
                        }

                        User::create([
                            'name' => $user['name'],
                            'email' => $user['email'],
                            'password' => Hash::make($user['password']),
                        ]);

                        $created++;
                    }

                    Notification::make()
                        ->title("{$created} users created via Gemini")
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function extractTextFromDocx(string $path): string
    {
        $phpWord = IOFactory::load($path);
        $text = '';

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (method_exists($element, 'getText')) {
                    $text .= $element->getText() . "\n";
                }
            }
        }

        return $text;
    }

    protected function sendToGemini(string $docText): array
    {
        $apiKey = env('GEMINI_API_KEY');
        $client = new Client();
    
        $prompt = <<<PROMPT
    The following document contains multiple user records. Each user has:
    
    - Name
    - Email
    - Password
    
    Fix typos, generate strong passwords if weak/missing, and return a valid JSON array like:
    [
      {"name": "Alice", "email": "alice@example.com", "password": "Strong123!"},
      {"name": "Bob", "email": "bob@example.com", "password": "SecureP@ss9"}
    ]
    
    Only return a raw JSON array — no Markdown, no code block, no explanation.
    
    Document:
    {$docText}
    PROMPT;
    
        try {
            $response = $client->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}", [
                'json' => [
                    'contents' => [[
                        'parts' => [[ 'text' => $prompt ]]
                    ]],
                ],
            ]);
    
            $body = json_decode($response->getBody()->getContents(), true);
    
            $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? '';
    
            // Clean unexpected formatting
            $text = trim($text);
            $text = preg_replace('/^```json|```$/', '', $text); // remove ```json blocks
            $text = trim($text);
    
            $parsed = json_decode($text, true);
    
            if (!is_array($parsed)) {
                \Log::warning('Gemini returned invalid JSON:', ['raw' => $text]);
                return [];
            }
    
            return $parsed;
    
        } catch (\Throwable $e) {
            \Log::error('Gemini API failed', ['error' => $e->getMessage()]);
            return [];
        }
    }
    
}
