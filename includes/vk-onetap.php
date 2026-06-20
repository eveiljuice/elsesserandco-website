<?php
/**
 * VK ID SDK v3 — OneTap (плашка/кнопка «Войти с VK»).
 *
 * Использование: в login.php / register.php внутри формы авторизации.
 *
 * Поток:
 *  1) На сервере генерируем state (CSRF) + PKCE code_verifier/code_challenge.
 *     code_verifier сохраняем в сессии, code_challenge передаём в JS.
 *  2) Подгружаем SDK с CDN.
 *  3) SDK рисует OneTap-кнопку, по клику открывает попап VK ID.
 *  4) При успехе SDK получает токен, мы AJAX-ом шлём code+device_id на
 *     /oauth/vk/exchange.php, тот обменивает на токен server-to-server,
 *     получает профиль, логинит пользователя через OAuthHelper.
 *  5) AJAX-ответ содержит { ok, redirect }, делаем window.location.
 *
 * Fallback: если SDK не загрузился (CDN недоступен), показываем обычную
 * <a href="/oauth/vk/start.php"> кнопку.
 *
 * Требования к .env:
 *   VK_CLIENT_ID  — числовой app ID (54644917 у вас)
 *   VK_CLIENT_SECRET — защищённый ключ
 *   VK_REDIRECT_URI — абсолютный URL нашего /oauth/vk/callback.php
 *                     (для HTTPS-окружений, например:
 *                     https://elsesserandco-site.local/oauth/vk/callback.php)
 */

// Partial — должен быть подключён только когда классы уже загружены.
// login.php / register.php уже подключают Config через свой require_once,
// но на случай, если partial вызывается откуда-то ещё — подстрахуемся.
if (!class_exists('Config', false)) {
    require_once __DIR__ . '/config/Config.php';
}
if (!class_exists('OAuthHelper', false)) {
    require_once __DIR__ . '/auth/oauth_helper.php';
}
if (!function_exists('generateCSRFToken') && class_exists('Config', false)) {
    // check_auth.php нужен для generateCSRFToken; подключаем, если ещё не.
    require_once __DIR__ . '/auth/check_auth.php';
}

if (!Config::get('VK_CLIENT_ID')) {
    // VK не настроен — partial ничего не выводит (кнопки скрыты в форме)
    return;
}

// Генерируем state и PKCE на сервере
$state = OAuthHelper::generateState('vk');
$_SESSION['oauth_state']['mode'] = 'onetap';

$pkce = OAuthHelper::generatePkce();
OAuthHelper::storeCodeVerifier('vk', $pkce['verifier']);

$appId     = (int)Config::get('VK_CLIENT_ID');
$redirect  = (string)Config::get('VK_REDIRECT_URI');
$returnTo  = $_GET['return'] ?? $_GET['redirect'] ?? null;
$csrfToken = function_exists('generateCSRFToken') ? generateCSRFToken() : '';
?>
<div class="vk-onetap-wrap">
    <!-- Контейнер, в котором SDK нарисует OneTap-кнопку -->
    <div id="VkIdSdkOneTap"></div>

    <!-- Fallback-ссылка, если SDK не загрузится -->
    <a href="/oauth/vk/start.php<?= $returnTo ? '?return=' . urlencode($returnTo) : '' ?>"
       class="vk-onetap-fallback"
       id="vkOnetapFallback"
       style="display:none;">
        Войти через VK
    </a>
</div>

<script src="https://unpkg.com/@vkid/sdk@<3.0.0/dist-sdk/umd/index.js" defer></script>
<script>
(function () {
    'use strict';

    var APP_ID      = <?= json_encode((string)$appId) ?>;
    var REDIRECT    = <?= json_encode($redirect) ?>;
    var STATE       = <?= json_encode($state) ?>;
    var CHALLENGE   = <?= json_encode($pkce['challenge']) ?>;
    var CSRF_HEADER = <?= json_encode($csrfToken) ?>;
    var RETURN_TO   = <?= json_encode($returnTo) ?>;

    var fallbackShown = false;
    function showFallback() {
        if (fallbackShown) return;
        fallbackShown = true;
        var f = document.getElementById('vkOnetapFallback');
        if (f) f.style.display = '';
    }

    // Ждём готовности DOM и SDK
    function init() {
        if (typeof window.VKIDSDK === 'undefined') {
            // SDK не загрузился — показываем fallback
            showFallback();
            return;
        }
        var VKID = window.VKIDSDK;
        if (!VKID || !VKID.Config || !VKID.OneTap) {
            showFallback();
            return;
        }

        try {
            VKID.Config.init({
                app: APP_ID,
                redirectUrl: REDIRECT,
                state: STATE,
                codeVerifier: CHALLENGE,
                scope: 'email'
            });

            var oneTap = new VKID.OneTap();
            var container = document.getElementById('VkIdSdkOneTap');

            if (!container) {
                showFallback();
                return;
            }

            oneTap.render({ container: container })
                .on(VKID.WidgetEvents.ERROR, function (err) {
                    console.error('[VK OneTap]', err);
                    showFallback();
                });

            // Подписываемся на глобальное событие успешного логина
            // (SDK v3 не даёт OneTapInternalEvents; используем кастомный flow
            // через VKID.Auth.exchangeCode или window.location на callback).
            document.addEventListener('vkid:onetap:success', function (e) {
                handleSuccess(e.detail || {});
            });
            document.addEventListener('vkid:onetap:code', function (e) {
                handleCode(e.detail || {});
            });

            // Если SDK v3 сам редиректит на redirectUrl после успеха —
            // наш callback уже обработает code/state.
            // Если SDK даёт code через событие (более новые версии) —
            // обработаем ниже.
        } catch (e) {
            console.error('[VK OneTap init]', e);
            showFallback();
        }
    }

    function handleCode(payload) {
        if (!payload || !payload.code) return;
        fetch('/oauth/vk/exchange.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': CSRF_HEADER
            },
            body: JSON.stringify({
                code: payload.code,
                device_id: payload.device_id || '',
                state: STATE
            }),
            credentials: 'same-origin'
        })
        .then(function (r) { return r.json(); })
        .then(function (resp) {
            if (resp && resp.ok && resp.redirect) {
                window.location.href = resp.redirect;
            } else {
                console.error('[VK exchange]', resp);
                showFallback();
            }
        })
        .catch(function (e) {
            console.error('[VK exchange network]', e);
            showFallback();
        });
    }

    function handleSuccess(payload) {
        if (payload && payload.code) handleCode(payload);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>