<?php
/**
 * VK ID SDK v3 — OneTap (плашка «Войти с VK» + альтернативный вход mail_ru / ok_ru).
 *
 * Официальный low-code snippet из доки VK:
 * https://id.vk.com/about/business/go/docs/ru/vkid/latest/vk-id/connection/start-integration/web/install
 *
 * Использование: в login.php / register.php внутри формы авторизации.
 * Подключается только когда VK_CLIENT_ID заполнен в .env.
 *
 * Требования к .env:
 *   VK_CLIENT_ID     — числовой app ID (например, 54644917)
 *   VK_CLIENT_SECRET — защищённый ключ приложения
 *   VK_REDIRECT_URI  — абсолютный URL /oauth/vk/callback.php
 *                      (на проде: https://elsesserandco.webtm.ru/oauth/vk/callback.php)
 */

// Partial — должен быть подключён только когда классы уже загружены.
if (!class_exists('Config', false)) {
    require_once __DIR__ . '/config/Config.php';
}
if (!class_exists('OAuthHelper', false)) {
    require_once __DIR__ . '/auth/oauth_helper.php';
}
if (!function_exists('generateCSRFToken') && class_exists('Config', false)) {
    require_once __DIR__ . '/auth/check_auth.php';
}

if (!Config::get('VK_CLIENT_ID')) {
    // VK не настроен — partial ничего не выводит
    return;
}

// state (CSRF) кладём в PHP-сессию — это наш, не зависит от SDK
$state = OAuthHelper::generateState('vk');
$_SESSION['oauth_state']['mode'] = 'onetap';

$appId    = (int)Config::get('VK_CLIENT_ID');
$redirect = (string)Config::get('VK_REDIRECT_URI');
?>
<div class="vk-onetap-wrap">
    <script nonce="csp_nonce" src="https://unpkg.com/@vkid/sdk@<3.0.0/dist-sdk/umd/index.js"></script>
    <script nonce="csp_nonce" type="text/javascript">
        if ('VKIDSDK' in window) {
            const VKID = window.VKIDSDK;

            VKID.Config.init({
                app: <?= json_encode((string)$appId) ?>,
                redirectUrl: <?= json_encode($redirect) ?>,
                responseMode: VKID.ConfigResponseMode.Callback,
                source: VKID.ConfigSource.LOWCODE,
                state: <?= json_encode($state) ?>
                // scope убран — пустая строка ломает инициализацию на проде
            });

            const oneTap = new VKID.OneTap();

            oneTap.render({
                container: document.currentScript.parentElement,
                showAlternativeLogin: true,
                oauthList: [
                    'mail_ru',
                    'ok_ru'
                ]
            })
            .on(VKID.WidgetEvents.ERROR, vkidOnError)
            .on(VKID.OneTapInternalEvents.LOGIN_SUCCESS, function (payload) {
                const code = payload.code;
                const deviceId = payload.device_id;

                VKID.Auth.exchangeCode(code, deviceId)
                    .then(vkidOnSuccess)
                    .catch(vkidOnError);
            });

            function vkidOnSuccess(data) {
                // Обработка полученного результата
            }

            function vkidOnError(error) {
                // Обработка ошибки
            }
        }
    </script>
</div>