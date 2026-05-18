/**
 * Mortgage Calculator — Elsesser & Co.
 * Аннуитетная формула: P = S * (i * (1+i)^n) / ((1+i)^n - 1), i — месячная ставка, n — мес.
 */
(function () {
    'use strict';

    function fmt(num) {
        return new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 0 }).format(Math.round(num));
    }

    function calc(price, downPct, ratePct, years) {
        const S = Math.max(0, price - price * (downPct / 100));
        const i = ratePct / 100 / 12;
        const n = years * 12;
        if (S <= 0 || n <= 0) {
            return { monthly: 0, total: 0, overpay: 0, loanAmount: S };
        }
        const monthly = i === 0
            ? S / n
            : S * (i * Math.pow(1 + i, n)) / (Math.pow(1 + i, n) - 1);
        const total = monthly * n;
        return { monthly, total, overpay: total - S, loanAmount: S };
    }

    function init(root) {
        const price   = root.querySelector('[data-mortgage-price]');
        const down    = root.querySelector('[data-mortgage-down]');
        const rate    = root.querySelector('[data-mortgage-rate]');
        const years   = root.querySelector('[data-mortgage-years]');
        const downOut = root.querySelector('[data-mortgage-down-out]');
        const rateOut = root.querySelector('[data-mortgage-rate-out]');
        const yearsOut= root.querySelector('[data-mortgage-years-out]');

        const outMonthly = root.querySelector('[data-mortgage-monthly]');
        const outTotal   = root.querySelector('[data-mortgage-total]');
        const outOverpay = root.querySelector('[data-mortgage-overpay]');

        function update() {
            if (downOut)  downOut.textContent  = down.value + ' %';
            if (rateOut)  rateOut.textContent  = rate.value + ' %';
            if (yearsOut) yearsOut.textContent = years.value + ' лет';

            const r = calc(+price.value || 0, +down.value || 0, +rate.value || 0, +years.value || 0);
            outMonthly.textContent = fmt(r.monthly) + ' ₽';
            outTotal.textContent   = fmt(r.total)   + ' ₽';
            outOverpay.textContent = fmt(r.overpay) + ' ₽';
        }

        [price, down, rate, years].forEach(el => el && el.addEventListener('input', update));
        update();
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-mortgage]').forEach(init);
    });
})();
