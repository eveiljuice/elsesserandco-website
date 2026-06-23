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
        if (!chatMessages || !RECEIVER_ID) {
            console.warn('[chat] init aborted: chatMessages=' + !!chatMessages + ' RECEIVER_ID=' + RECEIVER_ID);
            return;
        }
        
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

        // Кнопка «Записаться на просмотр» — привязываем здесь, чтобы DOM
        // точно был готов после init()
        var scheduleBtn = document.getElementById('scheduleViewingBtn');
        if (scheduleBtn) {
            scheduleBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('[chat] schedule viewing button clicked');
                openScheduleModal();
            });
        } else {
            console.warn('[chat] scheduleViewingBtn not found in DOM');
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
                // Парсим metadata для system-сообщений
                const normalized = data.messages.map(normalizeSystem);

                // Если на странице есть welcome-block — убираем его, заменяем реальными сообщениями
                const welcome = chatMessages.querySelector('.chat-welcome');
                if (welcome) welcome.remove();

                if (!isPolling) {
                    // Initial load - replace all messages
                    renderMessages(normalized);
                } else {
                    // Polling - append new messages
                    appendMessages(normalized);
                }

                // Update last message ID
                const lastMsg = normalized[normalized.length - 1];
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

    // Парсер системного сообщения из API (обогащает metadata, если строка)
    function normalizeSystem(msg) {
        if (!msg.is_system) return msg;
        if (typeof msg.metadata === 'string') {
            try { msg.metadata = JSON.parse(msg.metadata); } catch (e) { msg.metadata = null; }
        }
        return msg;
    }
    
    // Create message HTML
    function createMessageHTML(msg) {
        const isMine = msg.sender_id === CURRENT_USER_ID;
        const time = formatTime(msg.created_at);

        // Системные сообщения (запись/отмена просмотра) — отдельный стиль
        if (msg.is_system) {
            return renderSystemMessage(msg, time);
        }

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

    // Рендер системного сообщения (запись/отмена просмотра)
    function renderSystemMessage(msg, time) {
        let meta = null;
        try { meta = msg.metadata ? JSON.parse(msg.metadata) : null; } catch (e) { meta = null; }
        const isCancel = meta && meta.kind === 'viewing_cancelled';
        const isSchedule = meta && meta.kind === 'viewing_scheduled';

        let actions = '';
        // Кнопка «Отменить» доступна автору записи, если запись scheduled и
        // он сейчас её видит (проверку доступа делает сервер).
        if (isSchedule && meta && meta.viewing_id && msg.sender_id === CURRENT_USER_ID) {
            actions = `
                <button type="button" class="chat-system__action" data-cancel-viewing="${meta.viewing_id}">
                    <i class="fas fa-times"></i> Отменить запись
                </button>
            `;
        }

        return `
            <div class="chat-system ${isCancel ? 'chat-system--cancel' : 'chat-system--schedule'}"
                 data-message-id="${escapeHtml(String(msg.id))}"
                 data-viewing-id="${meta && meta.viewing_id ? meta.viewing_id : ''}">
                <div class="chat-system__icon">
                    <i class="fas ${isCancel ? 'fa-calendar-xmark' : 'fa-calendar-check'}"></i>
                </div>
                <div class="chat-system__body">
                    <div class="chat-system__text">${escapeHtml(msg.message).replace(/\n/g, '<br>')}</div>
                    <div class="chat-system__meta">
                        <span class="chat-system__time">${time}</span>
                        ${actions}
                    </div>
                </div>
            </div>
        `;
    }

    // Открыть модалку записи на просмотр
    let scheduleModalOpen = false;
    function openScheduleModal() {
        if (scheduleModalOpen) return;
        scheduleModalOpen = true;

        const today = new Date();
        const minDate = today.toISOString().slice(0, 10);
        const tomorrow = new Date(today);
        tomorrow.setDate(tomorrow.getDate() + 1);
        const defDate = tomorrow.toISOString().slice(0, 10);

        const modalHtml = `
            <div class="chat-schedule-modal" id="scheduleModal">
                <div class="chat-schedule-modal__backdrop" data-close="1"></div>
                <div class="chat-schedule-modal__dialog">
                    <h3>Записаться на просмотр</h3>
                    <p class="chat-schedule-modal__sub">Агент получит уведомление. Вы сможете отменить запись из чата.</p>
                    <form id="scheduleForm">
                        <div class="form-group">
                            <label class="form-label">Дата</label>
                            <input type="date" name="date" class="form-input" required min="${minDate}" value="${defDate}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Время</label>
                            <input type="time" name="time" class="form-input" required value="14:00">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Комментарий (необязательно)</label>
                            <textarea name="note" class="form-input" rows="2" placeholder="Например: буду с семьёй, нужна парковка…"></textarea>
                        </div>
                        <div class="chat-schedule-modal__error" id="scheduleErr" style="display:none;"></div>
                        <div class="chat-schedule-modal__actions">
                            <button type="button" class="btn btn--secondary btn--sm" data-close="1">Отмена</button>
                            <button type="submit" class="btn btn--primary btn--sm" id="scheduleSubmit">Записаться</button>
                        </div>
                    </form>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        const modal = document.getElementById('scheduleModal');
        modal.querySelectorAll('[data-close]').forEach(el => {
            el.addEventListener('click', closeScheduleModal);
        });
        document.getElementById('scheduleForm').addEventListener('submit', submitSchedule);
        document.getElementById('scheduleSubmit').focus();
    }
    function closeScheduleModal() {
        const m = document.getElementById('scheduleModal');
        if (m) m.remove();
        scheduleModalOpen = false;
    }
    async function submitSchedule(e) {
        e.preventDefault();
        const errBox = document.getElementById('scheduleErr');
        errBox.style.display = 'none';
        const btn = document.getElementById('scheduleSubmit');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Отправляем…';

        const fd = new FormData(e.target);
        const payload = {
            csrf_token: CSRF_HEADER,
            agent_id:   RECEIVER_ID,
            property_id: getPropertyIdFromForm() || 0,
            date: fd.get('date'),
            time: fd.get('time'),
            note: fd.get('note') || ''
        };

        try {
            const resp = await fetch('/php/chat/schedule_viewing.php', {
                method: 'POST',
                headers: csrfHeaders(),
                body: JSON.stringify(payload)
            });
            const data = await resp.json();
            if (data.ok) {
                closeScheduleModal();
                // Сервер записал viewing + создал system-сообщение. Перезагрузим чат чтобы показать его.
                if (typeof loadMessages === 'function') {
                    lastMessageId = 0;
                    loadMessages();
                }
            } else {
                errBox.textContent = friendlyScheduleError(data.error);
                errBox.style.display = '';
            }
        } catch (err) {
            console.error(err);
            errBox.textContent = 'Сетевая ошибка. Попробуйте позже.';
            errBox.style.display = '';
        } finally {
            btn.disabled = false;
            btn.innerHTML = 'Записаться';
        }
    }
    function friendlyScheduleError(code) {
        switch (code) {
            case 'missing_fields':  return 'Заполните дату и время.';
            case 'bad_date_format': return 'Некорректный формат даты.';
            case 'date_in_past':    return 'Нельзя выбрать прошедшую дату.';
            case 'property_not_found': return 'Объект не найден.';
            case 'agent_mismatch':  return 'Этот агент не обслуживает объект.';
            case 'cannot_schedule_with_self': return 'Нельзя записаться к самому себе.';
            case 'unauthorized':    return 'Сессия истекла. Войдите снова.';
            case 'bad_csrf':        return 'Ошибка безопасности. Перезагрузите страницу.';
            default:                return 'Не удалось создать запись. Попробуйте позже.';
        }
    }

    // Клик «Отменить запись» в системном сообщении
    document.addEventListener('click', async function (e) {
        const btn = e.target.closest('[data-cancel-viewing]');
        if (!btn) return;
        const viewingId = btn.getAttribute('data-cancel-viewing');
        if (!viewingId) return;
        if (!confirm('Отменить запись на просмотр?')) return;
        btn.disabled = true;
        try {
            const resp = await fetch('/php/chat/cancel_viewing.php', {
                method: 'POST',
                headers: csrfHeaders(),
                body: JSON.stringify({ csrf_token: CSRF_HEADER, viewing_id: parseInt(viewingId, 10) })
            });
            const data = await resp.json();
            if (data.ok) {
                lastMessageId = 0;
                loadMessages();
            } else {
                alert(friendlyChatError(data.error || 'server error', 500));
            }
        } catch (err) {
            console.error(err);
            alert('Сетевая ошибка');
        } finally {
            btn.disabled = false;
        }
    });

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
                        is_system: !!m.is_system,
                        metadata: m.metadata || null,
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
