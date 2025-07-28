<div class="flex flex-col h-[80vh] w-full max-w-2xl mx-auto border rounded-xl shadow-lg overflow-hidden bg-white">
    <!-- Header -->
    <div class="p-4 bg-gray-100 border-b flex items-center justify-between">
        <h2 class="text-lg font-semibold text-gray-700">Chat with AI</h2>
        <span class="text-sm text-gray-500">AI Assistant</span>
    </div>

    <!-- Messages -->
    <div class="flex-1 overflow-y-auto p-4 space-y-4" wire:poll.3s>
        @foreach ($messages as $message)
            <div class="flex {{ $message['from'] === 'user' ? 'justify-end' : 'justify-start' }}">
                <div class="{{ $message['from'] === 'user' ? 'bg-blue-500 text-black' : 'bg-gray-200 text-gray-800' }} px-4 py-2 rounded-lg max-w-xs">
                    {{ $message['text'] }}
                </div>
            </div>
        @endforeach
    </div>

    <!-- Input -->
    <form wire:submit.prevent="sendMessage" class="border-t p-4 bg-white">
        <div class="flex items-center space-x-2">
            <input 
                type="text" 
                wire:model.defer="messageInput" 
                class="flex-1 border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="Type your message..." 
            />
            <button 
                type="submit" 
                class="bg-blue-500 hover:bg-blue-600 text-blue px-4 py-2 rounded-lg"
            >
                Send
            </button>
        </div>
    </form>
</div>
