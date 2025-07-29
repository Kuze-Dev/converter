<?php

namespace App\Livewire;

use App\Models\User;
use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\GeminiInstructions;
use Illuminate\Support\Facades\Http;
use Filament\Notifications\Notification;

class ChatWithAI extends Component
{
    use WithFileUploads;

    public $messages = [];
    public $messageInput = '';
    public $uploadedImage; // For the actual file upload
    public $selectedImage; // For the preview data URL
    public $imageTooLarge = false;

    public function render()
    {
        return view('livewire.chat-with-a-i');
    }

    public function updatedUploadedImage()
    {
        $this->imageTooLarge = false;
        
        // Validate file size (20MB limit for Gemini API)
        if ($this->uploadedImage && $this->uploadedImage->getSize() > 20 * 1024 * 1024) {
            $this->imageTooLarge = true;
            $this->uploadedImage = null;
            $this->selectedImage = null;
            return;
        }

        if ($this->uploadedImage) {
            // Convert to base64 for preview
            $imageData = base64_encode(file_get_contents($this->uploadedImage->getRealPath()));
            $mimeType = $this->uploadedImage->getMimeType();
            
            $this->selectedImage = "data:{$mimeType};base64,{$imageData}";
        }
    }

    public function removeSelectedImage()
    {
        $this->uploadedImage = null;
        $this->selectedImage = null;
        $this->imageTooLarge = false;
    }

    public function sendMessage()
    {
        $userMessage = trim($this->messageInput);
        $hasImage = !empty($this->selectedImage);
        
        // Don't send empty messages without images
        if (empty($userMessage) && !$hasImage) {
            return;
        }

        // Prepare message data
        $messageData = [
            'from' => 'user',
            'text' => $userMessage ?: '[Image]'
        ];

        // Add image data if present
        if ($hasImage) {
            // Extract base64 data and mime type from data URL
            preg_match('/data:([^;]+);base64,(.+)/', $this->selectedImage, $matches);
            if (count($matches) === 3) {
                $messageData['image'] = $matches[2]; // base64 data
                $messageData['mime_type'] = $matches[1]; // mime type
            }
        }

        $this->messages[] = $messageData;
        $this->messageInput = '';
        $this->uploadedImage = null;
        $this->selectedImage = null;

        $this->askGemini($this->messages);
    }

    public function askGemini($messages)
    {
        $apiKey = env('GEMINI_API_KEY');

        // Get dynamic instructions from database
        $instructions = GeminiInstructions::orderBy('created_at', 'asc')->get(['title', 'content', 'created_at']);

        $instructionText = $instructions->isNotEmpty()
            ? $instructions->map(function ($i) {
                return "### {$i->title} (Created at: {$i->created_at->format('Y-m-d H:i:s')})\n{$i->content}";
            })->implode("\n\n---\n\n")
            : "Sorry, I could not understand that.";

        $systemPrompt = [
            [
                'role' => 'user',
                'parts' => [[
                    'text' => $instructionText
                ]]
            ]
        ];

        $chatHistory = array_merge(
            $systemPrompt,
            collect($messages)->map(function($m) {
                $parts = [];
                
                // Add text if present
                if (!empty($m['text'])) {
                    $parts[] = ['text' => $m['text']];
                }
                
                // Add image if present
                if (isset($m['image']) && isset($m['mime_type'])) {
                    $parts[] = [
                        'inline_data' => [
                            'mime_type' => $m['mime_type'],
                            'data' => $m['image']
                        ]
                    ];
                }
                
                // Fallback to text if no parts
                if (empty($parts)) {
                    $parts[] = ['text' => '[Message]'];
                }

                return [
                    'role' => $m['from'] === 'user' ? 'user' : 'model',
                    'parts' => $parts
                ];
            })->toArray()
        );

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->timeout(30)->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}", [
                'contents' => $chatHistory,
            ]);

            $data = $response->json();

            if ($response->failed()) {
                $this->messages[] = [
                    'from' => 'ai',
                    'text' => 'Sorry, I encountered an error processing your request. Please try again.'
                ];
                return;
            }

            $aiText = $data['candidates'][0]['content']['parts'][0]['text'] ?? 'Sorry, I could not understand that.';

            // Handle different CRUD operations
            if (str_starts_with($aiText, 'CREATE_USER|')) {
                $this->handleCreateUser($aiText);
            } elseif (str_starts_with($aiText, 'LIST_USERS')) {
                $this->handleListUsers();
            } elseif (str_starts_with($aiText, 'GET_USER|')) {
                $this->handleGetUser($aiText);
            } elseif (str_starts_with($aiText, 'UPDATE_USER|')) {
                $this->handleUpdateUser($aiText);
            } elseif (str_starts_with($aiText, 'DELETE_USER|')) {
                $this->handleDeleteUser($aiText);
            } else {
                // Regular AI response
                $this->messages[] = [
                    'from' => 'ai',
                    'text' => $aiText,
                ];
            }
        } catch (\Exception $e) {
            $this->messages[] = [
                'from' => 'ai',
                'text' => 'Sorry, I encountered a connection error. Please try again later.'
            ];
        }
    }

    private function handleCreateUser($aiText)
    {
        $parts = explode('|', $aiText);
        if (count($parts) === 4) {
            $name = $parts[1];
            $email = $parts[2];
            $password = $parts[3];
            
            try {
                if (User::where('email', $email)->exists()) {
                    $this->messages[] = ['from' => 'ai', 'text' => " User with email `{$email}` already exists."];
                    return;
                }

                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'password' => bcrypt($password),
                ]);

                $this->messages[] = ['from' => 'ai', 'text' => " User `{$user->email}` created successfully with ID: {$user->id}"];
                Notification::make()
                    ->title('User Created')
                    ->success()
                    ->send();
            } catch (\Exception $e) {
                $this->messages[] = ['from' => 'ai', 'text' => " Failed to create user. Error: " . $e->getMessage()];
            }
        } else {
            $this->messages[] = ['from' => 'ai', 'text' => " Invalid user creation format."];
        }
    }

    private function handleListUsers()
    {
        try {
            $users = User::select('id', 'name', 'email', 'created_at')->get();
            
            if ($users->isEmpty()) {
                $this->messages[] = ['from' => 'ai', 'text' => " No users found in the system."];
                return;
            }

            $usersList = "📋 **Users List:**\n\n";
            foreach ($users as $user) {
                $usersList .= "**ID:** {$user->id}\n";
                $usersList .= "**Name:** {$user->name}\n";
                $usersList .= "**Email:** {$user->email}\n";
                $usersList .= "**Created:** {$user->created_at->format('Y-m-d H:i:s')}\n";
                $usersList .= "---\n";
            }

            $this->messages[] = ['from' => 'ai', 'text' => $usersList];
        } catch (\Exception $e) {
            $this->messages[] = ['from' => 'ai', 'text' => " Failed to fetch users. Error: " . $e->getMessage()];
        }
    }

    private function handleGetUser($aiText)
    {
        $parts = explode('|', $aiText);
        if (count($parts) === 2) {
            $identifier = $parts[1];
            
            try {
                $user = is_numeric($identifier) 
                    ? User::find($identifier)
                    : User::where('email', $identifier)->first();

                if (!$user) {
                    $this->messages[] = ['from' => 'ai', 'text' => " User not found with identifier: `{$identifier}`"];
                    return;
                }

                $userInfo = "👤 **User Details:**\n\n";
                $userInfo .= "**ID:** {$user->id}\n";
                $userInfo .= "**Name:** {$user->name}\n";
                $userInfo .= "**Email:** {$user->email}\n";
                $userInfo .= "**Created:** {$user->created_at->format('Y-m-d H:i:s')}\n";
                $userInfo .= "**Updated:** {$user->updated_at->format('Y-m-d H:i:s')}";

                $this->messages[] = ['from' => 'ai', 'text' => $userInfo];
            } catch (\Exception $e) {
                $this->messages[] = ['from' => 'ai', 'text' => " Failed to fetch user. Error: " . $e->getMessage()];
            }
        } else {
            $this->messages[] = ['from' => 'ai', 'text' => " Invalid get user format."];
        }
    }

    private function handleUpdateUser($aiText)
    {
        $parts = explode('|', $aiText);
        if (count($parts) >= 3) {
            $identifier = $parts[1];
            
            try {
                $user = is_numeric($identifier) 
                    ? User::find($identifier)
                    : User::where('email', $identifier)->first();

                if (!$user) {
                    $this->messages[] = ['from' => 'ai', 'text' => " User not found with identifier: `{$identifier}`"];
                    return;
                }

                $updateData = [];
                $updatedFields = [];

                // Parse update fields
                for ($i = 2; $i < count($parts); $i++) {
                    $fieldParts = explode(':', $parts[$i], 2);
                    if (count($fieldParts) === 2) {
                        $field = trim($fieldParts[0]);
                        $value = trim($fieldParts[1]);

                        switch ($field) {
                            case 'name':
                                $updateData['name'] = $value;
                                $updatedFields[] = "name";
                                break;
                            case 'email':
                                // Check if new email already exists
                                if (User::where('email', $value)->where('id', '!=', $user->id)->exists()) {
                                    $this->messages[] = ['from' => 'ai', 'text' => " Email `{$value}` is already taken by another user."];
                                    return;
                                }
                                $updateData['email'] = $value;
                                $updatedFields[] = "email";
                                break;
                            case 'password':
                                $updateData['password'] = bcrypt($value);
                                $updatedFields[] = "password";
                                break;
                        }
                    }
                }

                if (empty($updateData)) {
                    $this->messages[] = ['from' => 'ai', 'text' => " No valid fields to update."];
                    return;
                }

                $user->update($updateData);

                $fieldsUpdated = implode(', ', $updatedFields);
                $this->messages[] = ['from' => 'ai', 'text' => "User `{$user->email}` updated successfully. Fields updated: {$fieldsUpdated}"];
                
                Notification::make()
                    ->title('User Updated')
                    ->success()
                    ->send();
            } catch (\Exception $e) {
                $this->messages[] = ['from' => 'ai', 'text' => " Failed to update user. Error: " . $e->getMessage()];
            }
        } else {
            $this->messages[] = ['from' => 'ai', 'text' => " Invalid update user format."];
        }
    }

    private function handleDeleteUser($aiText)
    {
        $parts = explode('|', $aiText);
        if (count($parts) === 2) {
            $identifier = $parts[1];
            
            try {
                // Try to find user by ID first, then by email
                $user = is_numeric($identifier) 
                    ? User::find($identifier)
                    : User::where('email', $identifier)->first();

                if (!$user) {
                    $this->messages[] = ['from' => 'ai', 'text' => " User not found with identifier: `{$identifier}`"];
                    return;
                }

                $userEmail = $user->email;
                $user->delete();

                $this->messages[] = ['from' => 'ai', 'text' => " User `{$userEmail}` deleted successfully."];
                
                Notification::make()
                    ->title('User Deleted')
                    ->success()
                    ->send();
            } catch (\Exception $e) {
                $this->messages[] = ['from' => 'ai', 'text' => " Failed to delete user. Error: " . $e->getMessage()];
            }
        } else {
            $this->messages[] = ['from' => 'ai', 'text' => " Invalid delete user format."];
        }
    }
}