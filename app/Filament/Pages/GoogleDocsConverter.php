<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Google\Client;
use Google\Service\Docs;
use Google\Service\Drive;

class GoogleDocsConverter extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static string $view = 'filament.pages.google-docs-converter';

    public string $docUrl = '';
    public ?string $htmlContent = null;

    public function convertToHtml()
    {
        // Extract document ID from URL
        preg_match('/\/d\/([a-zA-Z0-9-_]+)/', $this->docUrl, $matches);
        if (!isset($matches[1])) {
            $this->htmlContent = "Invalid Google Docs URL.";
            return;
        }
        $documentId = $matches[1];

        // Initialize Google Client
        $client = new Client();
        $client->setAuthConfig(storage_path('app/google/service-account.json'));
        $client->addScope(Drive::DRIVE_READONLY);
        
        $driveService = new Drive($client);
        
        try {
            $response = $driveService->files->export($documentId, 'text/html', ['alt' => 'media']);
            $this->htmlContent = $response->getBody()->getContents();
        } catch (\Exception $e) {
            $this->htmlContent = "Error: " . $e->getMessage();
        }

        
        dd($response);
    }
}
