<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class ChatWithGemini extends Page
{
    protected static ?string $navigationGroup = 'Ai Chat';
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.chat-with-gemini';
}
