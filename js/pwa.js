/**
 * PWA bootstrap + Web Push subscribe — Elsesser & Co.
 *
 * Регистрация SW + хелпер подписки на push.
 * Public VAPID key прокидывается через <meta name="vapid-public-key" content="...">.
 */
(function () {
    'use strict';

    if (!('serviceWorker' in navigator)) return;

    window.addEventListener('load', async () => {
        try {
            const reg = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
            window.__swReg = reg;
        } catch (e) {
            console.warn('SW registration failed:', e);
        }
    });

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
