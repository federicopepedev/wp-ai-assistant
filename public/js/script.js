document.addEventListener('DOMContentLoaded', function() {
    // Define all necessary DOM elements
    const assistantIcon = document.getElementById('ai-assistant'),
          chat = document.getElementById('ai-chat'),
          sendButton = document.getElementById('ai-send-button'),
          closeChat = document.getElementById('ai-close-chat'),
          clearChat = document.getElementById('ai-clear-chat'),
          chatForm = document.getElementById('ai-chat-form'),
          userMessageInput = document.getElementById('ai-user-message'),
          chatWrapper = document.getElementById('ai-chat-wrapper');

    // Toggle chat visibility
    function toggleChatVisibility() {
        chat.classList.toggle('invisible');
        assistantIcon.classList.toggle('invisible');
    }
    
    // Clear chat
    function clearMessages() {
        while (chatWrapper.firstChild) {
            chatWrapper.removeChild(chatWrapper.firstChild);
        }
    }

    // Event listeners for opening, closing and clear the chat
    assistantIcon.addEventListener('click', toggleChatVisibility);
    closeChat.addEventListener('click', toggleChatVisibility);
    clearChat.addEventListener('click', clearMessages);

    // Handle the form submission
    chatForm.addEventListener('submit', function(event) {
        event.preventDefault();
        sendMessage();
    });

    // Send the user's message and fetch the AI response
    function sendMessage() {
        // Sanitize user message
        const userMessage = DOMPurify.sanitize(userMessageInput.value.trim());
        // If user message is not valid
        if (!userMessage) {
            displayMessage('Please enter a valid message.', 'ai');
            return;
        }
        // Disable send button
        sendButton.disabled = true;
        // Display user message
        displayMessage(userMessage, 'user');
        // Clear user message input
        userMessageInput.value = "";

        const data = new FormData();
        data.append('action', 'ai_assistant_chat');
        // Pass nonce
        data.append('nonce', aiAssistant.nonce);
        data.append('user_message', userMessage);

        // Fetch API call for the AI response
        fetch(aiAssistant.ajax_url, {
            method: 'POST',
            body: data
        })
        .then(response => response.json())
        // Display AI message
        .then(response => displayMessage(response.data, 'ai'))
        // Catch and display error message
        .catch(() => displayMessage('Sorry, there was an error communicating with the server.', 'ai'))
        // Enable send button
        .finally(() => sendButton.disabled = false);
    }

    // Display message in the chat
    function displayMessage(message, type) {
        // Message container
        const messageContainer = document.createElement('div');
        messageContainer.classList.add('d-flex', 'flex-row', 'mb-4');
        messageContainer.classList.add(type === 'ai' ? 'justify-content-start' : 'justify-content-end');
        // Avatar
        const avatarImg = document.createElement('img');
        avatarImg.classList.add('avatar-img');
        avatarImg.src = `${aiAssistant.images}${type === 'ai' ? 'bot.png' : 'user.png'}`;
        // Message content
        const messageContent = document.createElement('div');
        messageContent.classList.add('p-3', type === 'ai' ? 'ms-3' : 'me-3', `${type}-message`);
        // Message text
        const messageText = document.createElement('p');
        messageText.classList.add('small', 'mb-0');
        messageText.textContent = message;
        messageContent.appendChild(messageText);
        // Copy message to clipboard
        const copyIcon = document.createElement('i');
        copyIcon.classList.add('fa-solid', 'fa-copy');
        copyIcon.addEventListener('click', () => {
            navigator.clipboard.writeText(message)
            .then(() => copyIcon.classList.replace('fa-copy', 'fa-check'))
        });
        // Avatar and message order
        if (type === 'ai') {
            messageContainer.appendChild(avatarImg);
            messageContainer.appendChild(messageContent);
            messageContent.appendChild(copyIcon);
        } else {
            messageContainer.appendChild(messageContent);
            messageContainer.appendChild(avatarImg);
        }
        // Append message container
        chatWrapper.appendChild(messageContainer);
        messageContainer.scrollIntoView({behavior: 'smooth'});
    }
});