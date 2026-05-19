(function () {
    'use strict';
    const root = document.querySelector('[data-utilities-calc]');
    if (!root) return;

    const area = root.querySelector('[data-u-area]');
    const hoa = root.querySelector('[data-u-hoa]');
    const tax = root.querySelector('[data-u-tax]');
    const out = root.querySelector('[data-u-total]');

    function calc() {
        const a = parseFloat(area?.value) || 0;
        const h = parseFloat(hoa?.value) || 0;
        const t = parseFloat(tax?.value) || 0;
        const monthly = h + t / 12;
        const yearly = monthly * 12;
        if (out) {
            out.textContent = new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 0 }).format(monthly)
                + ' ₽/мес · '
                + new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 0 }).format(yearly)
                + ' ₽/год (при ' + a + ' м²)';
        }
    }

    [area, hoa, tax].forEach((i) => i && i.addEventListener('input', calc));
    calc();
})();
