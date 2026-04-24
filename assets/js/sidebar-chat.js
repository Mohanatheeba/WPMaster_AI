/**
 * ClawWP Sidebar Chat
 *
 * Handles the admin sidebar chat UI, sending messages via REST API,
 * and rendering streaming responses via SSE.
 */
(function () {
    'use strict';

    const config = window.clawwpChat || {};
    const restUrl = config.restUrl;
    const nonce = config.nonce;

    // DOM elements
    const sidebar = document.getElementById('clawwp-sidebar');
    const toggle = document.getElementById('clawwp-sidebar-toggle');
    const closeBtn = document.getElementById('clawwp-sidebar-close');
    const newChatBtn = document.getElementById('clawwp-new-chat');
    const messages = document.getElementById('clawwp-messages');
    const form = document.getElementById('clawwp-chat-form');
    const input = document.getElementById('clawwp-input');
    const sendBtn = document.getElementById('clawwp-send');
    const typing = document.getElementById('clawwp-typing');
    const tokenCount = document.getElementById('clawwp-token-count');

    if (!sidebar || !toggle) return;

    let conversationId = null;
    let isStreaming = false;

    // --- Toggle sidebar ---
    toggle.addEventListener('click', function () {
        sidebar.classList.remove('clawwp-sidebar--collapsed');
        sidebar.classList.add('clawwp-sidebar--expanded');
        input.focus();
    });

    closeBtn.addEventListener('click', function () {
        sidebar.classList.remove('clawwp-sidebar--expanded');
        sidebar.classList.add('clawwp-sidebar--collapsed');
    });

    // --- New chat ---
    newChatBtn.addEventListener('click', function () {
        conversationId = null;
        messages.innerHTML = '';
        addMessage('assistant', 'Starting a new conversation. How can I help with ' + config.siteName + '?');
        input.focus();
    });

    // --- Auto-resize textarea ---
    input.addEventListener('input', function () {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        sendBtn.disabled = !this.value.trim();
    });

    // --- Submit on Enter (Shift+Enter for newline) ---
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            if (this.value.trim() && !isStreaming) {
                form.dispatchEvent(new Event('submit'));
            }
        }
    });

    // --- Send message ---
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        const text = input.value.trim();
        if (!text || isStreaming) return;

        addMessage('user', text);
        input.value = '';
        input.style.height = 'auto';
        sendBtn.disabled = true;

        sendToAgent(text);
    });

    /**
     * Send a message to the ClawWP agent via REST API.
     */
    function sendToAgent(text) {
        isStreaming = true;
        typing.style.display = 'flex';
        scrollToBottom();

        var payload = {
            message: text,
            channel: 'webchat',
        };
        if (conversationId) {
            payload.conversation_id = parseInt(conversationId, 10);
        }

        fetch(restUrl + 'chat', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce,
            },
            body: JSON.stringify(payload),
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok) {
                        var errMsg = data.response || data.message || ('HTTP ' + response.status);
                        throw new Error(errMsg);
                    }
                    return data;
                });
            })
            .then(function (data) {
                typing.style.display = 'none';

                if (data.conversation_id) {
                    conversationId = data.conversation_id;
                }

                if (data.response) {
                    addMessage('assistant', data.response);
                }

                // Show tool calls if any
                if (data.tools_executed && data.tools_executed.length > 0) {
                    data.tools_executed.forEach(function (tool) {
                        addToolCall(tool.name, tool.result);
                    });
                }

                // Update token count
                if (data.usage) {
                    tokenCount.textContent = data.usage.tokens_in + '+' + data.usage.tokens_out + ' tokens (~$' + data.usage.estimated_cost + ')';
                }

                isStreaming = false;
            })
            .catch(function (err) {
                typing.style.display = 'none';
                isStreaming = false;
                addMessage('assistant', 'Sorry, something went wrong: ' + err.message);
            });
    }

    /**
     * Add a message bubble to the chat.
     */
    function addMessage(role, content) {
        var div = document.createElement('div');
        div.className = 'clawwp-message clawwp-message--' + role;

        var contentDiv = document.createElement('div');
        contentDiv.className = 'clawwp-message-content';
        contentDiv.innerHTML = formatContent(content);

        div.appendChild(contentDiv);
        messages.appendChild(div);
        scrollToBottom();
    }

    /**
     * Add a tool call indicator.
     */
    function addToolCall(name, result) {
        var div = document.createElement('div');
        div.className = 'clawwp-tool-call';
        div.textContent = '\u2699 ' + name;
        if (result) {
            div.title = typeof result === 'string' ? result : JSON.stringify(result);
        }
        messages.appendChild(div);
        scrollToBottom();
    }

    /**
     * Basic content formatting (newlines, bold, code).
     */
    function formatContent(text) {
        if (!text) return '';
        return text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/`([^`]+)`/g, '<code>$1</code>')
            .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
            .replace(/\n/g, '<br>');
    }

    /**
     * Scroll messages to bottom.
     */
    function scrollToBottom() {
        messages.scrollTop = messages.scrollHeight;
    }

    // --- Restore sidebar state from localStorage ---
    var savedState = localStorage.getItem('clawwp_sidebar_state');
    if (savedState === 'expanded') {
        sidebar.classList.remove('clawwp-sidebar--collapsed');
        sidebar.classList.add('clawwp-sidebar--expanded');
    }

    // Save state on toggle
    var observer = new MutationObserver(function () {
        if (sidebar.classList.contains('clawwp-sidebar--expanded')) {
            localStorage.setItem('clawwp_sidebar_state', 'expanded');
        } else {
            localStorage.setItem('clawwp_sidebar_state', 'collapsed');
        }
    });
    observer.observe(sidebar, { attributes: true, attributeFilter: ['class'] });
})();
