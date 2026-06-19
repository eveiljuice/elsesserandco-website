/**
 * Cookie Banner + Floating Toggle — Elsesser & Co.
 * Категоризированное согласие на cookie (152-ФЗ).
 *
 * Поведение:
 *  - При первом визите (нет записи в localStorage) показывается нижняя плашка.
 *  - После согласия плашка скрывается, остаётся маленькая кнопка сбоку
 *    (правый нижний угол) с иконкой cookie. По клику — снова открывает
 *    баннер для повторного изменения настроек.
 *  - Кнопка видна ВСЕГДА, в том числе гостям (незалогиненным).
 *
 * Использование:
 *   <div id="cookieBanner">…</div>            ← разметка
 *   <button id="cookieToggle" hidden>…</button>  ← кнопка сбоку (опционально)
 *   <script src="js/cookie-banner.js"></script>
 *
 * API:
 *   window.EcoCookieBanner.show()    — открыть баннер
 *   window.EcoCookieBanner.hide()    — скрыть баннер
 *   window.EcoCookieBanner.reset()   — очистить localStorage и открыть
 *   window.EcoCookieBanner.get()     — текущее согласие (или null)
 *   window.EcoCookieBanner.openSettings()  — то же что reset()
 *
 * События:
 *   document.addEventListener('eco:cookie-consent', e => console.log(e.detail))
 */

(function () {
    'use strict';

    var STORAGE_KEY = 'eco_cookie_consent';
    var TTL_DAYS = 180;

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

    function showToggle() {
        var btn = document.getElementById('cookieToggle');
        if (!btn) return;
        btn.hidden = false;
    }

    function hideToggle() {
        var btn = document.getElementById('cookieToggle');
        if (!btn) return;
        btn.hidden = true;
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

    function bindToggle() {
        var btn = document.getElementById('cookieToggle');
        if (!btn) return;
        btn.addEventListener('click', function () {
            showBanner();
        });
    }

    function bindActions() {
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
                    showToggle();
                } else if (action === 'necessary-only') {
                    writeConsent({
                        accepted: 'necessary',
                        necessary: true,
                        analytics: false,
                        marketing: false
                    });
                    hideBanner();
                    showToggle();
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
                    showToggle();
                } else if (action === 'close') {
                    hideBanner();
                    showToggle();
                }
            });
        });
    }

    function init() {
        var existing = readConsent();
        if (!existing) {
            // Первый визит — показываем плашку, кнопку прячем (плашка сама служит CTA).
            showBanner();
            hideToggle();
        } else {
            // Согласие уже есть — баннер скрыт, показываем плавающую кнопку.
            hideBanner();
            showToggle();
            try {
                document.dispatchEvent(new CustomEvent('eco:cookie-consent', { detail: existing }));
            } catch (e) { /* noop */ }
        }

        bindToggle();
        bindActions();
    }

    window.EcoCookieBanner = {
        show: showBanner,
        hide: function () { hideBanner(); showToggle(); },
        reset: function () {
            localStorage.removeItem(STORAGE_KEY);
            hideToggle();
            showBanner();
        },
        openSettings: function () {
            localStorage.removeItem(STORAGE_KEY);
            hideToggle();
            showBanner();
            showCategories();
        },
        get: readConsent
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();