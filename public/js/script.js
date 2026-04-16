document.addEventListener('DOMContentLoaded', function() {

    const assistantIcon    = document.getElementById('ai-assistant'),
          chat             = document.getElementById('ai-chat'),
          sendButton       = document.getElementById('ai-send-button'),
          closeChat        = document.getElementById('ai-close-chat'),
          expandChat       = document.getElementById('ai-expand-chat'),
          clearChat        = document.getElementById('ai-clear-chat'),
          chatForm         = document.getElementById('ai-chat-form'),
          userMessageInput = document.getElementById('ai-user-message'),
          chatWrapper      = document.getElementById('ai-chat-wrapper');

    const STORAGE_KEY = 'ai_assistant_chat';
    const EXPIRY_MS   = 24 * 60 * 60 * 1000; // 24 hours

    let chatHistory = [];

    // -------------------------------------------------------------------------
    // Storage
    // -------------------------------------------------------------------------

    function saveToStorage() {
        try {
            // Cap stored history to 100 messages to avoid hitting localStorage limits
            const messagesToStore = chatHistory.length > 100
                ? chatHistory.slice(-100)
                : chatHistory;
            localStorage.setItem(STORAGE_KEY, JSON.stringify({
                expires:  Date.now() + EXPIRY_MS,
                messages: messagesToStore
            }));
        } catch (e) {}
    }

    function loadFromStorage() {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return null;
            const payload = JSON.parse(raw);
            if (!payload.expires || Date.now() > payload.expires) {
                localStorage.removeItem(STORAGE_KEY);
                return null;
            }
            return Array.isArray(payload.messages) ? payload.messages : null;
        } catch (e) {
            return null;
        }
    }

    // -------------------------------------------------------------------------
    // Event listeners
    // -------------------------------------------------------------------------

    assistantIcon.addEventListener('click', toggleChatVisibility);
    closeChat.addEventListener('click', toggleChatVisibility);
    clearChat.addEventListener('click', clearMessages);
    expandChat.addEventListener('click', toggleExpand);
    chatForm.addEventListener('submit', function(event) {
        event.preventDefault();
        sendMessage();
    });

    // Enter sends the message; Shift+Enter adds a new line
    userMessageInput.addEventListener('keydown', function(event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            if (!sendButton.disabled) {
                sendMessage();
            }
        }
    });

    // -------------------------------------------------------------------------
    // Restore chat from storage
    // -------------------------------------------------------------------------

    const saved = loadFromStorage();
    if (saved && saved.length > 0) {
        chatHistory = saved;
        saved.forEach(msg => {
            if (msg.type === 'user') {
                renderUserBubble(msg.text);
            } else if (msg.type === 'ai') {
                renderAiBubble(msg.text);
            }
        });
    }

    // -------------------------------------------------------------------------
    // Toggle / clear
    // -------------------------------------------------------------------------

    function toggleChatVisibility() {
        chat.classList.toggle('invisible');
        assistantIcon.classList.toggle('invisible');
    }

    function toggleExpand() {
        const isExpanded = chat.classList.toggle('expanded');
        expandChat.querySelector('i').className = isExpanded
            ? 'fa-solid fa-compress'
            : 'fa-solid fa-expand';
    }

    function clearMessages() {
        while (chatWrapper.firstChild) {
            chatWrapper.removeChild(chatWrapper.firstChild);
        }
        chatHistory = [];
        try { localStorage.removeItem(STORAGE_KEY); } catch (e) {}
    }

    // -------------------------------------------------------------------------
    // Send message
    // -------------------------------------------------------------------------

    // Streaming is enabled only if the admin flag is set AND the browser supports ReadableStream
    const useStreaming = aiAssistant.streaming === '1' && typeof window.ReadableStream !== 'undefined';

    function sendMessage() {
        const userMessage = DOMPurify.sanitize(userMessageInput.value.trim());
        if (!userMessage) {
            renderErrorBubble('Please enter a valid message.');
            return;
        }

        sendButton.disabled = true;

        // Build history BEFORE pushing the current message, then trim to max_history
        const historyForApi = chatHistory
            .slice(-aiAssistant.max_history)
            .map(msg => ({ role: msg.type === 'ai' ? 'assistant' : 'user', content: msg.text }));

        renderUserBubble(userMessage);
        chatHistory.push({ type: 'user', text: userMessage });
        saveToStorage();
        userMessageInput.value = '';

        const data = new FormData();
        data.append('nonce', aiAssistant.nonce);
        data.append('user_message', userMessage);
        data.append('history', JSON.stringify(historyForApi));

        if (useStreaming) {
            sendStreaming(data);
        } else {
            sendJson(data);
        }
    }

    // Streaming path
    function sendStreaming(data) {
        data.append('action', 'ai_assistant_stream');
        const bubble = createStreamingBubble();
        const { textEl, finalize, showError } = bubble;
        let fullText = '';

        fetch(aiAssistant.ajax_url, { method: 'POST', body: data })
            .then(response => {
                // Validation errors arrive as JSON (before SSE headers are set)
                const contentType = response.headers.get('Content-Type') || '';
                if (contentType.includes('application/json')) {
                    return response.json().then(json => {
                        showError(json.data || 'An error occurred. Please try again.');
                        sendButton.disabled = false;
                    });
                }

                const reader  = response.body.getReader();
                const decoder = new TextDecoder();

                function read() {
                    return reader.read().then(({ done, value }) => {
                        if (done) {
                            finalize(fullText);
                            sendButton.disabled = false;
                            return;
                        }
                        const lines = decoder.decode(value, { stream: true }).split('\n');
                        for (const line of lines) {
                            if (!line.startsWith('data: ')) continue;
                            const payload = line.slice(6).trim();
                            if (payload === '[DONE]') {
                                finalize(fullText);
                                sendButton.disabled = false;
                                return;
                            }
                            try {
                                const parsed = JSON.parse(payload);
                                if (parsed.error) {
                                    showError(parsed.error);
                                    sendButton.disabled = false;
                                    return;
                                }
                                if (parsed.text) {
                                    fullText += parsed.text;
                                    textEl.textContent = fullText;
                                    chatWrapper.scrollTop = chatWrapper.scrollHeight;
                                }
                            } catch (e) {}
                        }
                        return read();
                    });
                }
                return read();
            })
            .catch(() => {
                // Streaming failed — reuse the existing bubble for the JSON fallback
                sendJson(data, bubble);
            });
    }

    // JSON fallback path — accepts an existing bubble to reuse, or creates a new one
    function sendJson(data, existingBubble = null) {
        data.set('action', 'ai_assistant_chat');
        const { textEl, finalize } = existingBubble || createStreamingBubble();

        fetch(aiAssistant.ajax_url, { method: 'POST', body: data })
            .then(response => response.json())
            .then(response => {
                if (response.success) {
                    finalize(response.data);
                } else {
                    textEl.textContent = response.data || 'An error occurred. Please try again.';
                }
            })
            .catch(() => {
                textEl.textContent = 'Sorry, there was an error communicating with the server.';
            })
            .finally(() => {
                sendButton.disabled = false;
            });
    }

    // -------------------------------------------------------------------------
    // Bubble builders
    // -------------------------------------------------------------------------

    // Shared AI container — used by both streaming and static rendering
    function buildAiContainer() {
        const container = document.createElement('div');
        container.classList.add('d-flex', 'flex-row', 'justify-content-start', 'mb-4');

        const avatarImg = document.createElement('img');
        avatarImg.classList.add('avatar-img');
        avatarImg.src = `${aiAssistant.images}bot.png`;
        avatarImg.alt = 'AI';

        const messageContent = document.createElement('div');
        messageContent.classList.add('p-3', 'ms-3', 'ai-message');

        const messageText = document.createElement('p');
        messageText.classList.add('small', 'mb-0');

        messageContent.appendChild(messageText);
        container.appendChild(avatarImg);
        container.appendChild(messageContent);

        return { container, messageContent, messageText };
    }

    // Static AI bubble — used when restoring from storage
    function renderAiBubble(text) {
        const { container, messageContent, messageText } = buildAiContainer();
        messageText.innerHTML = DOMPurify.sanitize(parseMarkdown(text));
        addCopyIcon(messageContent, text);
        chatWrapper.appendChild(container);
    }

    // Streaming AI bubble — starts with animated cursor, finalized on stream end
    function createStreamingBubble() {
        const { container, messageContent, messageText } = buildAiContainer();

        // Cursor is a separate element — never mixed with text content
        const cursor = document.createElement('span');
        cursor.classList.add('ai-cursor');
        messageContent.appendChild(cursor);

        chatWrapper.appendChild(container);
        container.scrollIntoView({ behavior: 'smooth' });

        function finalize(fullText) {
            cursor.remove();
            messageText.innerHTML = DOMPurify.sanitize(parseMarkdown(fullText));
            addCopyIcon(messageContent, fullText);
            chatHistory.push({ type: 'ai', text: fullText });
            saveToStorage();
            container.scrollIntoView({ behavior: 'smooth' });
        }

        function showError(message) {
            cursor.remove();
            messageText.textContent = message;
        }

        return { textEl: messageText, finalize, showError };
    }

    // User bubble
    function renderUserBubble(text) {
        const container = document.createElement('div');
        container.classList.add('d-flex', 'flex-row', 'justify-content-end', 'mb-4');

        const avatarImg = document.createElement('img');
        avatarImg.classList.add('avatar-img');
        avatarImg.src = `${aiAssistant.images}user.png`;
        avatarImg.alt = 'User';

        const messageContent = document.createElement('div');
        messageContent.classList.add('p-3', 'me-3', 'user-message');

        const messageText = document.createElement('p');
        messageText.classList.add('small', 'mb-0');
        messageText.textContent = text;

        messageContent.appendChild(messageText);
        container.appendChild(messageContent);
        container.appendChild(avatarImg);
        chatWrapper.appendChild(container);
        container.scrollIntoView({ behavior: 'smooth' });
    }

    // Error bubble (not saved to history)
    function renderErrorBubble(message) {
        const { container, messageText } = buildAiContainer();
        messageText.textContent = message;
        chatWrapper.appendChild(container);
        container.scrollIntoView({ behavior: 'smooth' });
    }

    // -------------------------------------------------------------------------
    // Clipboard
    // -------------------------------------------------------------------------

    function addCopyIcon(messageContent, text) {
        const copyIcon = document.createElement('i');
        copyIcon.classList.add('fa-solid', 'fa-copy');
        copyIcon.style.cursor = 'pointer';
        copyIcon.addEventListener('click', () => {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(() => {
                    copyIcon.classList.replace('fa-copy', 'fa-check');
                    setTimeout(() => copyIcon.classList.replace('fa-check', 'fa-copy'), 2000);
                });
            } else {
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.style.cssText = 'position:fixed;opacity:0';
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                copyIcon.classList.replace('fa-copy', 'fa-check');
                setTimeout(() => copyIcon.classList.replace('fa-check', 'fa-copy'), 2000);
            }
        });
        messageContent.appendChild(copyIcon);
    }

    // -------------------------------------------------------------------------
    // Markdown parser
    // -------------------------------------------------------------------------

    function escapeHtml(str) {
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function parseMarkdown(text) {
        const blocks = [];

        // Extract fenced code blocks first to protect them from inline transforms
        text = text.replace(/```(\w*)\n?([\s\S]*?)```/g, (_, lang, code) => {
            const langAttr = lang ? ` class="language-${escapeHtml(lang)}"` : '';
            blocks.push(`<pre><code${langAttr}>${escapeHtml(code.trim())}</code></pre>`);
            return `\x00BLOCK${blocks.length - 1}\x00`;
        });

        // Inline transforms
        text = text.split('\n').map(line => {
            line = line.replace(/`([^`]+)`/g,     (_, c) => `<code>${escapeHtml(c)}</code>`);
            line = line.replace(/\*\*(.+?)\*\*/g, (_, s) => `<strong>${escapeHtml(s)}</strong>`);
            line = line.replace(/\*(.+?)\*/g,      (_, s) => `<em>${escapeHtml(s)}</em>`);
            return line;
        }).join('\n');

        // Paragraphs
        text = text
            .split(/\n{2,}/)
            .map(p => `<p>${p.replace(/\n/g, '<br>')}</p>`)
            .join('');

        // Restore code blocks
        text = text.replace(/\x00BLOCK(\d+)\x00/g, (_, i) => blocks[parseInt(i, 10)]);

        return text;
    }

});
