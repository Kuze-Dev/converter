<div class="flex flex-col h-[90vh] max-w-3xl w-full mx-auto rounded-2xl shadow-md overflow-hidden bg-white border border-gray-200">
    <!-- Header -->
    <div class="sticky top-0 z-10 bg-blue-50 p-4 border-b border-gray-200 flex justify-between items-center">
        <h2 class="text-xl font-semibold text-blue-800">Chat with AI</h2>
        <span class="text-sm text-gray-500">AI Assistant</span>
    </div>

    <!-- Messages -->
    <div class="flex-1 overflow-y-auto px-4 py-6 space-y-4 bg-white chat-messages">
        @foreach ($messages as $message)
            <div class="flex {{ $message['from'] === 'user' ? 'justify-end' : 'justify-start' }}">
                <div class="{{ $message['from'] === 'user' ? 'bg-blue-600 text-black' : 'bg-gray-100 text-gray-800' }} px-4 py-3 rounded-2xl max-w-xs sm:max-w-sm lg:max-w-md shadow-sm">
                    @if (!empty($message['text']))
                        <div class="whitespace-pre-wrap break-words">{{ $message['text'] }}</div>
                    @endif

                    @if (isset($message['image']))
                        <div class="mt-2">
                            <img 
                                src="data:{{ $message['mime_type'] ?? 'image/jpeg' }};base64,{{ $message['image'] }}" 
                                alt="Uploaded image" 
                                class="rounded-lg border {{ $message['from'] === 'user' ? 'border-blue-300' : 'border-gray-300' }} max-h-52"
                            />
                        </div>
                    @endif
                </div>
            </div>
        @endforeach

        @if ($isThinking)
        <div class="bg-gray-100 text-gray-800 px-4 py-3 rounded-2xl max-w-xs sm:max-w-sm lg:max-w-md shadow-sm">
            <div class="flex items-center space-x-4">
                <div class="typing-indicator mr-2">
                    <span class="dot"></span>
                    <span class="dot"></span>
                    <span class="dot"></span>
                </div>
                <span class="text-sm text-gray-500">AI is thinking...</span>
            </div>
        </div>
        @endif
    </div>

    @if ($selectedImage)
        <div class="bg-yellow-50 border-t border-b px-4 py-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <img src="{{ $selectedImage }}" alt="Selected image" class="w-16 h-16 object-cover rounded border">
                    <div>
                        <p class="text-sm font-medium text-gray-700">Image selected</p>
                        <p class="text-xs text-gray-500">It will be sent with your message</p>
                    </div>
                </div>
                <button wire:click="removeSelectedImage" class="text-red-500 hover:text-red-700 text-sm font-semibold">
                    Remove
                </button>
            </div>
        </div>
    @endif

    <!-- Input -->
    <form wire:submit.prevent="sendMessage" class="sticky bottom-0 bg-white p-4 border-t" id="chatForm">
        <div class="flex items-center gap-3">
            <label for="imageUpload" class="cursor-pointer text-gray-500 hover:text-blue-600 transition">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"></path>
                    </svg>
                <input type="file" wire:model="uploadedImage" accept="image/*" id="imageUpload" class="hidden" />
            </label>

            <input 
                type="text" 
                wire:model.defer="messageInput" 
                placeholder="Type a message..." 
                class="flex-1 px-4 py-2 border border-gray-300 rounded-full focus:outline-none focus:ring-2 focus:ring-blue-400"
                @if($isThinking) disabled @endif
                id="messageInput"
            />

            <button 
                type="submit" 
                class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-5 py-2 rounded-full transition disabled:opacity-50"
                @if($isThinking) disabled @endif
                id="sendButton"
            >
                <span wire:loading.remove>Send</span>
                <svg wire:loading class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.372 0 0 5.372 0 12h4z"></path>
                </svg>
            </button>
        </div>

        @if ($imageTooLarge)
            <p class="mt-2 text-sm text-red-600 bg-red-50 border p-2 rounded">
                Image file is too large. Please select a file smaller than 20MB.
            </p>
        @endif
    </form>
</div>

@push('styles')
<style>
    .chat-messages::-webkit-scrollbar {
        width: 6px;
    }
    .chat-messages::-webkit-scrollbar-thumb {
        background-color: #cbd5e0;
        border-radius: 3px;
    }
    .chat-messages {
        scroll-behavior: smooth;
    }
    .space-y-4 > div {
        animation: fadeInUp 0.3s ease-out both;
    }
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(12px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Thinking bubble animation */
    .typing-indicator {
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .typing-indicator .dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background-color: #9ca3af;
        animation: typing 1.4s infinite ease-in-out;
    }

    .typing-indicator .dot:nth-child(2) {
        animation-delay: 0.2s;
    }

    .typing-indicator .dot:nth-child(3) {
        animation-delay: 0.4s;
    }

    @keyframes typing {
        0%, 60%, 100% {
            transform: scale(0.8);
            opacity: 0.5;
        }
        30% {
            transform: scale(1);
            opacity: 1;
        }
    }

    .thinking-bubble-temp {
        animation: fadeInUp 0.3s ease-out both;
    }
</style>
@endpush

@push('scripts')
<script>
    let tempThinkingBubble = null;

    document.getElementById('chatForm').addEventListener('submit', function(e) {
        const messageInput = document.getElementById('messageInput');
        const hasMessage = messageInput.value.trim() !== '';
        const hasImage = document.querySelector('input[type="file"]').files.length > 0;
        
        if (hasMessage || hasImage) {
            showThinkingBubble();
        }
    });

    function showThinkingBubble() {
        removeTemporaryThinkingBubble();
        
        const container = document.querySelector('.chat-messages');
        tempThinkingBubble = document.createElement('div');
        tempThinkingBubble.className = 'flex justify-start thinking-bubble-temp';
        tempThinkingBubble.innerHTML = `
        <div class="bg-gray-100 text-gray-800 px-4 py-3 rounded-2xl max-w-xs sm:max-w-sm lg:max-w-md shadow-sm">
            <div class="flex items-center space-x-2">
                <div class="typing-indicator mr-2">
                    <span class="dot"></span>
                    <span class="dot"></span>
                    <span class="dot"></span>
                </div>
                <span class="text-sm text-gray-500">AI is thinking...</span>
            </div>
        </div>
    `;
    container.appendChild(tempThinkingBubble);

    container.scrollTop = container.scrollHeight;
}

function removeTemporaryThinkingBubble() {
    if (tempThinkingBubble && tempThinkingBubble.parentNode) {
        tempThinkingBubble.parentNode.removeChild(tempThinkingBubble);
        tempThinkingBubble = null;
    }
}

document.addEventListener("livewire:update", function () {
    removeTemporaryThinkingBubble();
});
</script>
@endpush