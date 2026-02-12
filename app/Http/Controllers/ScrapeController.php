<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Spatie\RouteAttributes\Attributes\Post;

class ScrapeController extends Controller
{
    #[Post('/fetch', name: 'render-html')]
    public function fetch(Request $request)
    {
        $validated = $request->validate([
            'url' => 'required|url',
        ]);

        $apiKey = 'ac5fe0a70bc0e7f9fafb62042463bb1d';

        try {
            // ScraperAPI endpoint
            $response = Http::timeout(60)
                ->get("http://api.scraperapi.com", [
                    'api_key' => $apiKey,
                    'url' => 'https://demo-2-bice.vercel.app/weathers/the-birth-of-aviation',
                    'render' => 'true', // enables JS rendering
                ]);


            if (! $response->ok()) {
                throw new \Exception("ScraperAPI request failed: {$response->status()}");
            }

            // The response body *is* the HTML
            $html = $response->body();

            if (empty($html)) {
                throw new \Exception('No HTML returned from ScraperAPI.');
            }

            return response()->json([
                'success' => true,
                'url' => $validated['url'],
                'html' => $html,
            ]);
        } catch (\Throwable $e) {
            \Log::error('ScraperAPI fetch failed', [
                'url' => $validated['url'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
