/**
 * PWA bootstrap + Web Push subscribe — Elsesser & Co.
 *
 * Регистрация SW + хелпер подписки на push + обработчик кнопки «Установить приложение».
 * Public VAPID key прокидывается через <meta name="vapid-public-key" content="...">.
 * Кнопка должна иметь id="pwaInstallBtn" (по умолчанию скрыта через hidden).
 */
(function () {
    'use strict';

    // ---- Service Worker ----
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', async () => {
            try {
                const reg = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
                window.__swReg = reg;
            } catch (e) {
                console.warn('SW registration failed:', e);
            }
        });
    }

    // ---- Install prompt (button #pwaInstallBtn) ----
    // Chrome/Edge шлёт beforeinstallprompt один раз за сессию. Запоминаем событие,
    // показываем кнопку. На клике — вызываем prompt(). После — очищаем и прячем кнопку.
    let deferredPrompt = null;
    let installInProgress = false;

    function showInstallButton(btn) {
        if (!btn) return;
        btn.hidden = false;
    }

    function hideInstallButton(btn) {
        if (!btn) return;
        btn.hidden = true;
    }

    function setupInstallButton() {
        const btn = document.getElementById('pwaInstallBtn');
        if (!btn) return;

        // Если приложение уже запущено как PWA — скрыть кнопку сразу
        if (window.matchMedia('(display-mode: standalone)').matches ||
            window.navigator.standalone === true) {
            hideInstallButton(btn);
            return;
        }

        // Кнопка уже видима из HTML (hidden снят раньше в dashboard.php) —
        // это нормально, но не будем её показывать, пока не пришёл промпт.
        // Если hidden=true — оставляем; покажем по событию.
        if (!btn.hidden) {
            // уже показывается — оставим как есть
        }

        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            if (installInProgress) return;

            if (!deferredPrompt) {
                // Промпт уже использован или ещё не пришёл.
                // Прячем кнопку — повторно показать её нельзя (Chrome не повторяет событие).
                hideInstallButton(btn);
                alert('Приложение уже установлено, либо установка недоступна в этом браузере.');
                return;
            }

            installInProgress = true;
            try {
                deferredPrompt.prompt();
                const choice = await deferredPrompt.userChoice;
                // Не важно, accepted или dismissed — повторного промпта не будет.
                if (choice && choice.outcome === 'accepted') {
                    // пользователь принял — приложение установится
                }
            } catch (err) {
                console.warn('Install prompt error:', err);
            } finally {
                deferredPrompt = null;
                installInProgress = false;
                hideInstallButton(btn);
            }
        });
    }

    // Подписываемся на событие. Если обработчик навешан до того, как событие
    // сработает — отлично. Если позже — потеряем, но кнопка останется скрытой,
    // что лучше, чем падающий клик.
    window.addEventListener('beforeinstallprompt', (e) => {
        // Событие приходит один раз. Чтобы браузер не показал свой мини-баннер
        // (в Chrome), превентим default — но в нашем случае мы хотим именно наш
        // баннер через кнопку, поэтому preventDefault() нужен.
        e.preventDefault();
        deferredPrompt = e;
        const btn = document.getElementById('pwaInstallBtn');
        showInstallButton(btn);
    });

    // После установки — кнопка больше не нужна
    window.addEventListener('appinstalled', () => {
        deferredPrompt = null;
        const btn = document.getElementById('pwaInstallBtn');
        hideInstallButton(btn);
    });

    // Инициализируем обработчик кнопки при готовности DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupInstallButton);
    } else {
        setupInstallButton();
    }

    // ---- Web Push helpers ----
    function urlBase64ToUint8Array(base64) {
        const padding = '='.repeat((4 - base64.length % 4) % 4);
        const b64 = (base64 + padding).replace(/-/g, '+').replace(/_/g, '/');
        const raw = atob(b64);
        const out = new Uint8Array(raw.length);
        for (let i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
        return out;
    }

    window.EcoPush = {
        async enable() {
            const meta = document.querySelector('meta[name="vapid-public-key"]');
            const vapid = meta && meta.content;
            if (!vapid) { alert('Push не настроен (нет VAPID ключа)'); return false; }
            if (!('Notification' in window) || !('PushManager' in window)) {
                alert('Браузер не поддерживает push-уведомления');
                return false;
            }

            const reg = window.__swReg || await navigator.serviceWorker.ready;
            const perm = await Notification.requestPermission();
            if (perm !== 'granted') return false;

            const existing = await reg.pushManager.getSubscription();
            const sub = existing || await reg.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(vapid)
            });

            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            await fetch('/php/push/subscribe.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfMeta ? csrfMeta.content : ''
                },
                body: JSON.stringify(sub)
            });
            return true;
        },
        async disable() {
            const reg = window.__swReg || await navigator.serviceWorker.ready;
            const sub = await reg.pushManager.getSubscription();
            if (!sub) return;
            const endpoint = sub.endpoint;
            await sub.unsubscribe();
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            await fetch('/php/push/unsubscribe.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfMeta ? csrfMeta.content : ''
                },
                body: JSON.stringify({ endpoint })
            });
        }
    };
})();
