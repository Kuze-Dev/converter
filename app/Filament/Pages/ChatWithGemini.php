<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class ChatWithGemini extends Page
{
    protected static ?string $navigationGroup = 'Ai Chat';
    protected static ?string $navigationLabel = 'Chat with Butcheennn AI';
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.chat-with-gemini';

    public function getTitle(): string
    {
        return 'Chat with Butcheennn AI';
    }
}
