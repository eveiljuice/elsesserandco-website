/**
 * Hero search autocomplete — Elsesser & Co.
 * Прикрепляется к любому input[data-autocomplete] и тянет /php/search/autocomplete.php.
 */
(function () {
    'use strict';

    const DEBOUNCE = 220;
    const MIN_LEN  = 2;

    function debounce(fn, ms) {
        let t;
        return function (...args) {
            clearTimeout(t);
            t = setTimeout(() => fn.apply(this, args), ms);
        };
    }

    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, ch => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[ch]));
    }

    function buildDropdown(input) {
        const wrapper = input.closest('.search-box__input-wrapper') || input.parentElement;
        wrapper.style.position = 'relative';

        const dd = document.createElement('div');
        dd.className = 'autocomplete';
        dd.setAttribute('role', 'listbox');
        dd.hidden = true;
        wrapper.appendChild(dd);
        return dd;
    }

    function render(dd, results, query) {
        if (!results.length) {
            dd.innerHTML = `<div class="autocomplete__empty">Ничего не нашли по «${escapeHtml(query)}»</div>`;
            dd.hidden = false;
            return;
        }
        dd.innerHTML = results.map((r, idx) => {
            const icon = r.icon ? `fa-${r.icon}` : 'fa-home';
            const img  = r.image
                ? `<div class="autocomplete__img" style="background-image:url('${escapeHtml(r.image)}')"></div>`
                : `<div class="autocomplete__icon"><i class="fas ${icon}"></i></div>`;
            const price = r.price ? `<div class="autocomplete__price">${escapeHtml(r.price)}</div>` : '';
            return `
                <a href="${escapeHtml(r.url)}" class="autocomplete__item" data-idx="${idx}" role="option">
                    ${img}
                    <div class="autocomplete__body">
                        <div class="autocomplete__title">${escapeHtml(r.title)}</div>
                        ${r.subtitle ? `<div class="autocomplete__subtitle">${escapeHtml(r.subtitle)}</div>` : ''}
                    </div>
                    ${price}
                </a>
            `;
        }).join('');
        dd.hidden = false;
    }

    function attach(input) {
        const dd = buildDropdown(input);
        const form = input.closest('form');
        const typeInput = form?.querySelector('input[name="type"], input[name="category"]');

        let active = -1;
        let lastResults = [];

        const fetchResults = debounce(async (q) => {
            const type = (typeInput && typeInput.value) || 'sale';
            try {
                const r = await fetch(`/php/search/autocomplete.php?q=${encodeURIComponent(q)}&type=${encodeURIComponent(type)}`);
                const data = await r.json();
                lastResults = data.results || [];
                active = -1;
                render(dd, lastResults, q);
            } catch (e) {
                dd.hidden = true;
            }
        }, DEBOUNCE);

        input.addEventListener('input', () => {
            const q = input.value.trim();
            if (q.length < MIN_LEN) { dd.hidden = true; return; }
            fetchResults(q);
        });

        input.addEventListener('focus', () => {
            if (input.value.trim().length >= MIN_LEN && lastResults.length) dd.hidden = false;
        });

        input.addEventListener('keydown', (e) => {
            const items = dd.querySelectorAll('.autocomplete__item');
            if (!items.length) return;
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                active = e.key === 'ArrowDown'
                    ? Math.min(items.length - 1, active + 1)
                    : Math.max(0, active - 1);
                items.forEach((el, i) => el.classList.toggle('autocomplete__item--active', i === active));
                items[active]?.scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'Enter' && active >= 0) {
                e.preventDefault();
                items[active].click();
            } else if (e.key === 'Escape') {
                dd.hidden = true;
            }
        });

        document.addEventListener('click', (e) => {
            if (!dd.contains(e.target) && e.target !== input) dd.hidden = true;
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('input[data-autocomplete]').forEach(attach);
    });
})();
