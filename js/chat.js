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
    let isLoading = false;
    
    // Initialize
    function init() {
        if (!chatMessages || !RECEIVER_ID) return;
        
        // Load initial messages
        loadMessages();
        
        // Start polling every 3 seconds
        startPolling();
        
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
                stopPolling();
            } else {
                startPolling();
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
                // No messages on initial load
                chatMessages.innerHTML = `
                    <div class="chat-messages__empty">
                        <i class="fas fa-comments"></i>
                        <p>Начните диалог</p>
                    </div>
                `;
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
        
        let propertyLink = '';
        if (msg.property_id && msg.property_title) {
            propertyLink = `
                <a href="/property.php?id=${msg.property_id}" class="chat-message__property" target="_blank">
                    <i class="fas fa-home"></i> ${escapeHtml(msg.property_title)}
                </a>
            `;
        }
        
        return `
            <div class="chat-message ${isMine ? 'chat-message--mine' : 'chat-message--theirs'}" 
                 data-message-id="${msg.id}">
                ${!isMine ? `<div class="chat-message__avatar">${msg.sender_name.charAt(0).toUpperCase()}</div>` : ''}
                <div class="chat-message__content">
                    ${propertyLink}
                    <div class="chat-message__bubble">
                        <div class="chat-message__text">${escapeHtml(msg.message)}</div>
                        <div class="chat-message__meta">
                            <span class="chat-message__time">${time}</span>
                            ${isMine ? `<span class="chat-message__status">${msg.is_read ? '<i class="fas fa-check-double"></i>' : '<i class="fas fa-check"></i>'}</span>` : ''}
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
    
    // Send message
    async function handleSendMessage(e) {
        e.preventDefault();
        
        const message = messageInput.value.trim();
        if (!message || !RECEIVER_ID) return;
        
        // Disable form while sending
        sendButton.disabled = true;
        
        try {
            const formData = new FormData(chatForm);
            const response = await fetch('/php/chat/send_message.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    receiver_id: RECEIVER_ID,
                    message: message,
                    property_id: formData.get('property_id') || null
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Clear input
                messageInput.value = '';
                autoResizeTextarea();
                
                // Add message to chat
                const msgElement = document.createElement('div');
                msgElement.innerHTML = createMessageHTML({
                    id: data.message.id,
                    sender_id: CURRENT_USER_ID,
                    message: data.message.message,
                    created_at: data.message.created_at,
                    sender_name: data.message.sender_name,
                    is_read: false
                });
                
                // Remove empty state if exists
                const emptyState = chatMessages.querySelector('.chat-messages__empty');
                if (emptyState) emptyState.remove();
                
                chatMessages.appendChild(msgElement.firstElementChild);
                scrollToBottom(true);
                
                // Update last message ID
                lastMessageId = data.message.id;
            } else {
                alert(data.error || 'Ошибка отправки');
            }
        } catch (error) {
            console.error('Error sending message:', error);
            alert('Ошибка отправки сообщения');
        } finally {
            sendButton.disabled = false;
            messageInput.focus();
        }
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
        
        setTimeout(() => {
            chatMessages.scrollTo({
                top: chatMessages.scrollHeight,
                behavior: smooth ? 'smooth' : 'auto'
            });
        }, 100);
    }
    
    // Start polling
    function startPolling() {
        stopPolling();
        pollingInterval = setInterval(() => {
            loadMessages(true);
        }, 3000);
    }
    
    // Stop polling
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
