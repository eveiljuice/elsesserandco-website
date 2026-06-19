/**
 * Cookie Banner — Elsesser & Co.
 * Категоризированное согласие на cookie (152-ФЗ).
 *
 * Использование:
 *   <div id="cookieBanner">…</div>            ← разметка
 *   <script src="js/cookie-banner.js"></script>
 *
 * API:
 *   window.EcoCookieBanner.show()    — принудительно открыть баннер
 *   window.EcoCookieBanner.hide()    — скрыть
 *   window.EcoCookieBanner.reset()   — очистить localStorage и открыть
 *   window.EcoCookieBanner.get()     — текущее согласие (или null)
 *
 * События:
 *   document.addEventListener('eco:cookie-consent', e => console.log(e.detail))
 */

(function () {
    'use strict';

    var STORAGE_KEY = 'eco_cookie_consent';
    var TTL_DAYS = 180;
    var DEFAULT_CONSENT = {
        accepted: false,
        necessary: true,
        analytics: false,
        marketing: false,
        ts: 0
    };

    /** @returns {object|null} */
    function readConsent() {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return null;
            var data = JSON.parse(raw);
            // Протухло — игнорируем
            var ageDays = (Date.now() - (data.ts || 0)) / (1000 * 60 * 60 * 24);
            if (ageDays > TTL_DAYS) return null;
            return data;
        } catch (e) {
            return null;
        }
    }

    function writeConsent(consent) {
        consent.ts = Date.now();
        localStorage.setItem(STORAGE_KEY, JSON.stringify(consent));
        // Уведомляем подписчиков (метрика, аналитика и т.п.)
        try {
            document.dispatchEvent(new CustomEvent('eco:cookie-consent', { detail: consent }));
        } catch (e) {
            // Старые браузеры без CustomEvent — игнор.
        }
    }

    function showBanner() {
        var banner = document.getElementById('cookieBanner');
        if (!banner) return;
        banner.hidden = false;
    }

    function hideBanner() {
        var banner = document.getElementById('cookieBanner');
        if (!banner) return;
        banner.hidden = true;
    }

    function showCategories() {
        var cats = document.getElementById('cookieCategories');
        var saveBtn = document.querySelector('[data-cookie-action="save-selection"]');
        var settingsBtn = document.querySelector('[data-cookie-action="settings"]');
        if (cats) cats.hidden = false;
        if (saveBtn) saveBtn.hidden = false;
        if (settingsBtn) settingsBtn.hidden = true;
    }

    function readCategoriesFromUI() {
        var analyticsEl = document.getElementById('cookieAnalytics');
        var marketingEl = document.getElementById('cookieMarketing');
        return {
            analytics: !!(analyticsEl && analyticsEl.checked),
            marketing: !!(marketingEl && marketingEl.checked)
        };
    }

    function init() {
        var existing = readConsent();
        if (!existing) {
            showBanner();
        } else {
            // Уже есть согласие — баннер скрыт, но событие прошло при загрузке.
            try {
                document.dispatchEvent(new CustomEvent('eco:cookie-consent', { detail: existing }));
            } catch (e) { /* noop */ }
        }

        // Навешиваем обработчики на кнопки
        document.querySelectorAll('[data-cookie-action]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var action = btn.getAttribute('data-cookie-action');
                if (action === 'accept-all') {
                    writeConsent({
                        accepted: true,
                        necessary: true,
                        analytics: true,
                        marketing: true
                    });
                    hideBanner();
                } else if (action === 'necessary-only') {
                    writeConsent({
                        accepted: 'necessary',
                        necessary: true,
                        analytics: false,
                        marketing: false
                    });
                    hideBanner();
                } else if (action === 'settings') {
                    showCategories();
                } else if (action === 'save-selection') {
                    var sel = readCategoriesFromUI();
                    writeConsent({
                        accepted: 'custom',
                        necessary: true,
                        analytics: sel.analytics,
                        marketing: sel.marketing
                    });
                    hideBanner();
                }
            });
        });
    }

    window.EcoCookieBanner = {
        show: showBanner,
        hide: hideBanner,
        reset: function () {
            localStorage.removeItem(STORAGE_KEY);
            showBanner();
        },
        get: readConsent
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();