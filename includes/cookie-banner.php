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
 * TTL: 180 дней.
 *
 * JS: js/cookie-banner.js — управляет видимостью плашки, плавающей кнопкой
 * и пишет в localStorage.
 * Событие 'eco:cookie-consent' диспатчится на document при любом изменении.
 *
 * Видимость:
 *  - На первом визите (нет записи в localStorage) — снизу появляется плашка.
 *  - После согласия (или на любом визите с записью) — в правом нижнем углу
 *    показывается маленькая плавающая кнопка. По клику открывает плашку
 *    снова для изменения настроек.
 */
?>
<div id="cookieBanner" class="cookie-banner" role="dialog" aria-live="polite" aria-labelledby="cookieBannerTitle" hidden>
    <button type="button" class="cookie-banner__close" data-cookie-action="close" aria-label="Закрыть">&times;</button>
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

<!-- Плавающая кнопка сбоку: видна, когда баннер скрыт (после согласия или при последующих визитах) -->
<button type="button" id="cookieToggle" class="cookie-toggle" aria-label="Настройки cookie" hidden>
    <i class="fas fa-cookie-bite"></i>
</button>

<script src="js/cookie-banner.js" defer></script>