<?php

namespace App\Livewire;

use App\Models\User;
use Livewire\Component;
use Illuminate\Support\Facades\Http;

class ChatWithAI extends Component
{
    public $messages = [];
    public $messageInput = '';

    public function render()
    {
        return view('livewire.chat-with-a-i');
    }

    public function sendMessage()
    {
        $userMessage = trim($this->messageInput);
        $this->messages[] = ['from' => 'user', 'text' => $userMessage];
        $this->messageInput = '';

        $this->askGemini($this->messages);
    }

    public function askGemini($messages)
    {
        $apiKey = env('GEMINI_API_KEY');

        $systemPrompt = [
            [
                'role' => 'user',
                'parts' => [[
                    'text' => <<<PROMPT
You are a chatbot assistant built into a Laravel admin panel.

Your name must be Butcheennn in this project.

You must be friendly and helpful.

Supported function:
- "create a user": Collect both email and password from the user through conversation, then respond in this EXACT format:
  CREATE_USER|email@example.com|password123

When user asks to create a user:
1. Ask for email if not provided
2. Ask for password if not provided  
3. Once you have both, respond with: CREATE_USER|[email]|[password]

DO NOT say you cannot perform the action.
Guide the user step by step to collect the required information.
PROMPT
                ]]
            ]
        ];

        $chatHistory = array_merge(
            $systemPrompt,
            collect($messages)->map(fn($m) => [
                'role' => $m['from'] === 'user' ? 'user' : 'model',
                'parts' => [['text' => $m['text']]],
            ])->toArray()
        );

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}", [
            'contents' => $chatHistory,
        ]);

        $data = $response->json();

        $aiText = $data['candidates'][0]['content']['parts'][0]['text'] ?? 'Sorry, I could not understand that.';

        if (str_starts_with($aiText, 'CREATE_USER|')) {
            $parts = explode('|', $aiText);
            if (count($parts) === 3) {
                $email = $parts[1];
                $password = $parts[2];
                
                try {
                    $user = User::create([
                        'name' => $email,
                        'email' => $email,
                        'password' => bcrypt($password),
                    ]);

                    $this->messages[] = ['from' => 'ai', 'text' => "✅ User `{$user->email}` created successfully."];
                } catch (\Exception $e) {
                    $this->messages[] = ['from' => 'ai', 'text' => "❌ Failed to create user. Error: " . $e->getMessage()];
                }
            } else {
                $this->messages[] = ['from' => 'ai', 'text' => "❌ Invalid user creation format."];
            }
        } else {
            $this->messages[] = [
                'from' => 'ai',
                'text' => $aiText,
            ];
        }
    }
}