/**
 * Dashboard banner: «Подтвердите почту» + повторная отправка письма.
 * Подключать только если на странице есть #resendVerification.
 */

(function () {
    'use strict';

    var btn = document.getElementById('resendVerification');
    var msg = document.getElementById('resendMessage');
    var banner = document.getElementById('verifyEmailBanner');
    var close = document.getElementById('verifyEmailBannerClose');

    if (!btn) return;

    function showMessage(text, isError) {
        if (!msg) return;
        msg.hidden = false;
        msg.textContent = text;
        msg.classList.toggle('verify-email-banner__message--error', !!isError);
    }

    btn.addEventListener('click', function () {
        if (btn.disabled) return;
        var csrf = btn.getAttribute('data-csrf') || '';
        btn.disabled = true;
        var originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Отправляем…';

        fetch('/php/auth/resend_verification.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-Token': csrf
            },
            body: 'csrf_token=' + encodeURIComponent(csrf)
        })
        .then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { return { status: r.status, body: j }; }); })
        .then(function (resp) {
            if (resp.status === 429) {
                var seconds = 60;
                var m = /(\d+)\s*сек/i.exec(resp.body.message || '');
                if (m) seconds = parseInt(m[1], 10) || 60;
                showMessage(resp.body.message || 'Слишком часто. Подождите минуту.', true);
                // Disable button until rate-limit expires
                setTimeout(function () { btn.disabled = false; btn.innerHTML = originalHtml; }, seconds * 1000);
                return;
            }
            if (resp.body && resp.body.success) {
                showMessage('Письмо отправлено повторно. Проверьте почту.', false);
                if (resp.body.warning === 'mail_disabled_in_production') {
                    showMessage('ВНИМАНИЕ: доставка писем в проде отключена (MAIL_TRANSPORT=log). Письмо только в логе.', true);
                }
                // Re-enable button after 60s
                setTimeout(function () { btn.disabled = false; btn.innerHTML = originalHtml; }, 60000);
            } else {
                showMessage((resp.body && (resp.body.message || resp.body.error)) || 'Не удалось отправить письмо.', true);
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        })
        .catch(function () {
            showMessage('Сетевая ошибка. Попробуйте позже.', true);
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        });
    });

    if (close && banner) {
        close.addEventListener('click', function () {
            banner.classList.add('verify-email-banner--hidden');
        });
    }
})();