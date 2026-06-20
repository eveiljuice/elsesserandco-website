/**
 * Chat JavaScript - Elsesser & Co.
 * Логика чата с AJAX polling
 */

(function() {
    'use strict';
    
    // Elements
    const chatMessages = document.getElementById('chatMessages');
    const chatForm = document.getElementById('chatForm');
    const messageInput = document.getElementById('messageInput');
    const sendButton = document.getElementById('sendButton');
    const chatSidebar = document.getElementById('chatSidebar');
    const chatBack = document.getElementById('chatBack');
    
    // State
    let lastMessageId = 0;
    let pollingInterval = null;
    let eventSource = null;
    let isLoading = false;

    function csrfHeaders() {
        const m = document.querySelector('meta[name="csrf-token"]');
        return {
            'Content-Type': 'application/json',
            'X-CSRF-Token': m ? m.content : ''
        };
    }
    
    // Initialize
    function init() {
        if (!chatMessages || !RECEIVER_ID) return;
        
        // Load initial messages
        loadMessages();
        
        startSSE();
        
        // Form submit
        if (chatForm) {
            chatForm.addEventListener('submit', handleSendMessage);
        }
        
        // Auto-resize textarea
        if (messageInput) {
            messageInput.addEventListener('input', autoResizeTextarea);
            messageInput.addEventListener('keydown', handleKeyDown);
        }
        
        // Mobile back button
        if (chatBack) {
            chatBack.addEventListener('click', () => {
                chatSidebar.classList.add('chat-sidebar--open');
            });
        }
        
        // Handle visibility change (pause polling when tab is hidden)
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                stopSSE();
            } else {
                startSSE();
            }
        });
    }
    
    // Load messages
    async function loadMessages(isPolling = false) {
        if (isLoading || !RECEIVER_ID) return;
        
        isLoading = true;
        
        try {
            const url = `/php/chat/get_messages.php?user_id=${RECEIVER_ID}&last_id=${isPolling ? lastMessageId : 0}`;
            const response = await fetch(url);
            const data = await response.json();

            if (data.success && data.messages.length > 0) {
                // Если на странице есть welcome-block — убираем его, заменяем реальными сообщениями
                const welcome = chatMessages.querySelector('.chat-welcome');
                if (welcome) welcome.remove();

                if (!isPolling) {
                    // Initial load - replace all messages
                    renderMessages(data.messages);
                } else {
                    // Polling - append new messages
                    appendMessages(data.messages);
                }

                // Update last message ID
                const lastMsg = data.messages[data.messages.length - 1];
                if (lastMsg) {
                    lastMessageId = lastMsg.id;
                }

                // Scroll to bottom on initial load or new messages
                scrollToBottom(!isPolling);
            } else if (!isPolling) {
                // Нет сообщений — оставляем welcome-block с приветствием
                // (он уже есть в HTML при заходе на ?user=X с новым собеседником)
                const existingWelcome = chatMessages.querySelector('.chat-welcome');
                if (!existingWelcome) {
                    // Фоллбек на случай динамической загрузки без welcome
                    chatMessages.innerHTML = `
                        <div class="chat-messages__empty">
                            <div class="empty-icon"><i class="far fa-comment-dots"></i></div>
                            <h3>Начните диалог</h3>
                            <p>Напишите первое сообщение — история переписки появится здесь.</p>
                        </div>
                    `;
                }
            }
        } catch (error) {
            console.error('Error loading messages:', error);
        } finally {
            isLoading = false;
        }
    }
    
    // Render messages (initial load)
    function renderMessages(messages) {
        let html = '';
        let currentDate = '';
        
        messages.forEach(msg => {
            const msgDate = formatDate(msg.created_at);
            
            // Date separator
            if (msgDate !== currentDate) {
                currentDate = msgDate;
                html += `<div class="chat-date-separator"><span>${msgDate}</span></div>`;
            }
            
            html += createMessageHTML(msg);
        });
        
        chatMessages.innerHTML = html;
    }
    
    // Append new messages (polling)
    function appendMessages(messages) {
        messages.forEach(msg => {
            // Check if message already exists
            if (document.querySelector(`[data-message-id="${msg.id}"]`)) return;
            
            const msgElement = document.createElement('div');
            msgElement.innerHTML = createMessageHTML(msg);
            chatMessages.appendChild(msgElement.firstElementChild);
        });
    }
    
    // Create message HTML
    function createMessageHTML(msg) {
        const isMine = msg.sender_id === CURRENT_USER_ID;
        const time = formatTime(msg.created_at);
        // Берём первую букву имени отправителя; если её нет — фоллбек "•"
        const senderLetter = (msg.sender_name || ' ').trim().charAt(0).toUpperCase() || '•';
        const senderAvatar = msg.sender_avatar || '';

        let propertyLink = '';
        if (msg.property_id && msg.property_title) {
            propertyLink = `
                <a href="/property.php?id=${msg.property_id}" class="chat-message__property" target="_blank">
                    <i class="fas fa-home"></i> ${escapeHtml(msg.property_title)}
                </a>
            `;
        }

        // Аватар-блок: если есть avatar_url — <img>, иначе фоллбек-буква
        const avatarBlock = senderAvatar
            ? `<img class="chat-avatar-img" src="${escapeHtml(senderAvatar)}" alt="${escapeHtml(senderLetter)}" data-letter="${escapeHtml(senderLetter)}" loading="lazy" onerror="this.style.display='none';this.nextElementSibling&&(this.nextElementSibling.style.display='flex');">` +
              `<span class="chat-avatar-fallback" data-letter="${escapeHtml(senderLetter)}" style="display:none;">${escapeHtml(senderLetter)}</span>`
            : `<span class="chat-avatar-fallback" data-letter="${escapeHtml(senderLetter)}">${escapeHtml(senderLetter)}</span>`;

        // Иконка статуса: pending — часы, failed — ошибка, иначе — галочки
        let statusIcon = '';
        if (msg.pending) {
            statusIcon = '<span class="chat-message__status" title="Отправляется..."><i class="fas fa-clock"></i></span>';
        } else if (msg.failed) {
            statusIcon = '<span class="chat-message__status chat-message__status--failed" title="Не отправлено"><i class="fas fa-exclamation-circle"></i></span>';
        } else if (isMine) {
            statusIcon = `<span class="chat-message__status">${msg.is_read ? '<i class="fas fa-check-double"></i>' : '<i class="fas fa-check"></i>'}</span>`;
        }

        return `
            <div class="chat-message ${isMine ? 'chat-message--mine' : 'chat-message--theirs'}"
                 data-message-id="${escapeHtml(String(msg.id))}">
                ${!isMine ? `<div class="chat-message__avatar">${avatarBlock}</div>` : ''}
                <div class="chat-message__content">
                    ${propertyLink}
                    <div class="chat-message__bubble">
                        <div class="chat-message__text">${escapeHtml(msg.message)}</div>
                        <div class="chat-message__meta">
                            <span class="chat-message__time">${time}</span>
                            ${statusIcon}
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
    
    // Send message — оптимистичный UI.
    // Сообщение показывается сразу (статус pending), POST идёт в фоне.
    // При успехе — заменяем временный id на настоящий, снимаем pending.
    // При ошибке — помечаем сообщение красным, текст остаётся в textarea.
    // sendButton НЕ блокируется на время POST — пользователь может
    // отправлять следующее сообщение сразу.
    async function handleSendMessage(e) {
        e.preventDefault();

        const message = messageInput.value.trim();
        if (!message || !RECEIVER_ID) return;

        // 1) Сразу очищаем поле и сбрасываем высоту — UI отзывчивый
        messageInput.value = '';
        autoResizeTextarea();
        messageInput.focus();

        // 2) Убираем welcome-блок (если это первое сообщение)
        const welcome = chatMessages.querySelector('.chat-welcome');
        if (welcome) welcome.remove();

        // 3) Оптимистичный рендер — сообщение появляется мгновенно
        const tempId = 'tmp_' + Date.now() + '_' + Math.random().toString(36).slice(2, 8);
        const tempMsg = {
            id: tempId,
            sender_id: CURRENT_USER_ID,
            message: message,
            created_at: new Date().toISOString().replace('T', ' ').slice(0, 19),
            sender_name: CURRENT_USER_NAME || '',
            sender_avatar: CURRENT_USER_AVATAR || '',
            is_read: false,
            pending: true,
            property_id: null,
            property_title: null
        };
        const tempNode = appendSingleMessage(tempMsg);
        scrollToBottom(true);

        // 4) Отправляем POST в фоне (НЕ блокируем форму)
        try {
            const response = await fetch('/php/chat/send_message.php', {
                method: 'POST',
                headers: csrfHeaders(),
                body: JSON.stringify({
                    receiver_id: RECEIVER_ID,
                    message: message,
                    property_id: getPropertyIdFromForm()
                })
            });

            const data = await response.json();

            if (data.success) {
                // Заменяем temp-сообщение реальным (снимаем pending)
                if (tempNode && tempNode.parentNode) {
                    const newNode = appendSingleMessage({
                        id: data.message.id,
                        sender_id: CURRENT_USER_ID,
                        message: data.message.message,
                        created_at: data.message.created_at,
                        sender_name: data.message.sender_name,
                        sender_avatar: CURRENT_USER_AVATAR || '',
                        is_read: false,
                        property_id: null,
                        property_title: null
                    });
                    tempNode.parentNode.replaceChild(newNode, tempNode);
                }
                lastMessageId = Math.max(lastMessageId, data.message.id || 0);
            } else {
                markMessageFailed(tempNode);
                var msg = data.message || data.error;
                var friendly = friendlyChatError(msg, response.status);
                alert(friendly);
            }
        } catch (error) {
            console.error('Send error:', error);
            markMessageFailed(tempNode);
            alert('Ошибка сети. Сообщение помечено как недоставленное.');
        }
    }

    function getPropertyIdFromForm() {
        // Берём property_id из скрытого поля формы (если есть).
        var input = chatForm && chatForm.querySelector('input[name="property_id"]');
        return input ? (input.value || null) : null;
    }

    function appendSingleMessage(msg) {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = createMessageHTML(msg);
        const node = wrapper.firstElementChild;
        if (node) chatMessages.appendChild(node);
        return node;
    }

    function markMessageFailed(node) {
        if (!node) return;
        node.classList.add('chat-message--failed');
        // Меняем иконку clock → exclamation-circle через data-атрибут,
        // который createMessageHTML уже нарисовал.
        var statusEl = node.querySelector('.chat-message__status');
        if (statusEl) {
            statusEl.classList.add('chat-message__status--failed');
            statusEl.setAttribute('title', 'Не отправлено — кликните для повтора');
            statusEl.innerHTML = '<i class="fas fa-exclamation-circle"></i>';
            statusEl.style.cursor = 'pointer';
            statusEl.onclick = function () {
                var text = node.querySelector('.chat-message__text');
                if (text) {
                    messageInput.value = text.textContent;
                    messageInput.focus();
                }
                node.remove();
            };
        }
    }

    // Переводим серверные ошибки в понятные пользователю сообщения.
    function friendlyChatError(rawError, status) {
        if (!rawError) return 'Не удалось отправить сообщение.';
        var e = String(rawError).toLowerCase();
        if (status === 401 || e.indexOf('unauthor') !== -1) return 'Сессия истекла. Войдите снова.';
        if (status === 403) return 'Нет прав для отправки сообщений.';
        if (status === 429 || e.indexOf('rate') !== -1 || e.indexOf('too many') !== -1) {
            return 'Слишком много сообщений. Подождите минуту.';
        }
        if (e.indexOf('email_not_verified') !== -1) return 'Подтвердите почту, чтобы писать сообщения.';
        if (e.indexOf('missing') !== -1) return 'Заполните текст сообщения.';
        if (e === 'server error' || status >= 500) return 'Серверная ошибка. Попробуйте позже.';
        return rawError;
    }
    
    // Handle Enter key
    function handleKeyDown(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            chatForm.dispatchEvent(new Event('submit'));
        }
    }
    
    // Auto-resize textarea
    function autoResizeTextarea() {
        messageInput.style.height = 'auto';
        messageInput.style.height = Math.min(messageInput.scrollHeight, 120) + 'px';
    }
    
    // Scroll to bottom
    function scrollToBottom(smooth = false) {
        if (!chatMessages) return;
        chatMessages.scrollTo({
            top: chatMessages.scrollHeight,
            behavior: smooth ? 'smooth' : 'auto'
        });
    }
    
    function startSSE() {
        stopSSE();
        if (!window.EventSource || !RECEIVER_ID) {
            startPolling();
            return;
        }
        const url = `/php/chat/stream.php?user_id=${RECEIVER_ID}&last_id=${lastMessageId}`;
        eventSource = new EventSource(url);
        eventSource.addEventListener('messages', (ev) => {
            try {
                const data = JSON.parse(ev.data);
                if (data.messages && data.messages.length) {
                    appendMessages(data.messages.map((m) => ({
                        id: m.id,
                        sender_id: parseInt(m.sender_id, 10),
                        message: m.message,
                        created_at: m.created_at,
                        sender_name: m.sender_first_name || '',
                        sender_avatar: m.sender_avatar || '',
                        is_read: !!m.is_read,
                        property_id: null,
                        property_title: null
                    })));
                    lastMessageId = data.last_id;
                    scrollToBottom(true);
                }
            } catch (e) { console.error(e); }
        });
        eventSource.addEventListener('done', () => {
            eventSource.close();
            if (!document.hidden) setTimeout(startSSE, 300);
        });
        eventSource.onerror = () => {
            stopSSE();
            startPolling();
        };
    }

    function stopSSE() {
        if (eventSource) {
            eventSource.close();
            eventSource = null;
        }
        stopPolling();
    }

    function startPolling() {
        stopPolling();
        pollingInterval = setInterval(() => loadMessages(true), 3000);
    }

    function stopPolling() {
        if (pollingInterval) {
            clearInterval(pollingInterval);
            pollingInterval = null;
        }
    }
    
    // Format date
    function formatDate(dateString) {
        const date = new Date(dateString);
        const today = new Date();
        const yesterday = new Date(today);
        yesterday.setDate(yesterday.getDate() - 1);
        
        if (date.toDateString() === today.toDateString()) {
            return 'Сегодня';
        } else if (date.toDateString() === yesterday.toDateString()) {
            return 'Вчера';
        } else {
            return date.toLocaleDateString('ru-RU', {
                day: 'numeric',
                month: 'long'
            });
        }
    }
    
    // Format time
    function formatTime(dateString) {
        const date = new Date(dateString);
        return date.toLocaleTimeString('ru-RU', {
            hour: '2-digit',
            minute: '2-digit'
        });
    }
    
    // Escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Initialize when DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
