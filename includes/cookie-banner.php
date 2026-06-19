<?php
/**
 * Cookie consent banner (152-ФЗ compliant).
 *
 * Категории:
 *  - necessary: всегда true, чекбокс disabled
 *  - analytics: метрика, аналитика
 *  - marketing: рекламные cookie, ретаргетинг
 *
 * Хранилище: localStorage['eco_cookie_consent'] = { accepted, necessary, analytics, marketing, ts }.
 * TTL: 180 дней (срок согласия по рекомендациям Роскомнадзора).
 *
 * JS: js/cookie-banner.js — управляет видимостью и пишет в localStorage.
 * Событие 'eco:cookie-consent' диспатчится на document при любом изменении.
 */
?>
<div id="cookieBanner" class="cookie-banner" role="dialog" aria-live="polite" aria-labelledby="cookieBannerTitle" hidden>
    <div class="cookie-banner__inner">
        <div class="cookie-banner__content">
            <h2 class="cookie-banner__title" id="cookieBannerTitle">Мы используем cookie</h2>
            <p class="cookie-banner__text">
                Этот сайт использует cookie для работы, аналитики и улучшения сервиса.
                Вы можете принять все cookie или настроить категории по отдельности.
                Подробнее — в <a href="/privacy.php" target="_blank">политике конфиденциальности</a>.
            </p>
        </div>

        <div class="cookie-banner__categories" id="cookieCategories" hidden>
            <label class="cookie-banner__category">
                <input type="checkbox" checked disabled>
                <span class="cookie-banner__category-label">
                    <strong>Необходимые</strong>
                    <small>Требуются для базовой работы сайта (сессия, авторизация, корзина). Всегда включены.</small>
                </span>
            </label>
            <label class="cookie-banner__category">
                <input type="checkbox" id="cookieAnalytics" data-cookie-category="analytics">
                <span class="cookie-banner__category-label">
                    <strong>Аналитика</strong>
                    <small>Помогают понять, как посетители используют сайт (Яндекс.Метрика и т.п.).</small>
                </span>
            </label>
            <label class="cookie-banner__category">
                <input type="checkbox" id="cookieMarketing" data-cookie-category="marketing">
                <span class="cookie-banner__category-label">
                    <strong>Маркетинг</strong>
                    <small>Используются для рекламы и ретаргетинга. По умолчанию выключены.</small>
                </span>
            </label>
        </div>

        <div class="cookie-banner__actions">
            <button type="button" class="cookie-banner__btn cookie-banner__btn--accept" data-cookie-action="accept-all">
                Принять все
            </button>
            <button type="button" class="cookie-banner__btn cookie-banner__btn--necessary" data-cookie-action="necessary-only">
                Только необходимые
            </button>
            <button type="button" class="cookie-banner__btn cookie-banner__btn--settings" data-cookie-action="settings">
                Настроить
            </button>
            <button type="button" class="cookie-banner__btn cookie-banner__btn--save" data-cookie-action="save-selection" hidden>
                Сохранить выбор
            </button>
        </div>
    </div>
</div>
<script src="js/cookie-banner.js" defer></script>