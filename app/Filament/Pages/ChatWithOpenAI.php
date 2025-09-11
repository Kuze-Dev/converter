<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Illuminate\Support\Facades\Http;

class ChatWithOpenAI extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationGroup = 'Ai Chat';
    protected static ?string $navigationLabel = 'Chat with Butcheennn AI v2';
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-oval-left-ellipsis';

    protected static string $view = 'filament.pages.chat-with-openai';

    public ?string $fileUrl = '';
    public ?string $prompt = '';
    public ?string $response = null;

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\TextInput::make('fileUrl')
                ->label('File URL')
                ->url()
                ->required()
                ->placeholder('https://example.com/file.pdf'),

            Forms\Components\Textarea::make('prompt')
                ->label('Your Question / Prompt')
                ->rows(5)
                ->required()
                ->placeholder('Ask something about the file...'),
        ];
    }

    public function askAI(): void
    {
        if (! $this->fileUrl || ! $this->prompt) {
            $this->response = "⚠️ Please provide a file URL and a prompt.";
            return;
        }
    
        try {
            $result = Http::withToken(config('services.openai.key'))
                ->post('https://api.openai.com/v1/responses', [
                    'model' => 'gpt-5', // or gpt-4.1, gpt-4o depending on your plan
                    'input' => [[
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'input_text',
                                'text' => $this->prompt,
                            ],
                            [
                                'type' => 'input_file',
                                'file_url' => $this->fileUrl,
                            ],
                        ],
                    ]],
                ]);
    
            $this->response = $result->json('output[0].content[0].text') ?? 'No response.';
            \Log::info($result);
        } catch (\Exception $e) {
            $this->response = "❌ Error: " . $e->getMessage();
        }
    }
}
