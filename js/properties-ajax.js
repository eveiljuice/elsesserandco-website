(function () {
    'use strict';
    const form = document.getElementById('filterForm') || document.getElementById('propertiesFilters');
    const container = document.getElementById('catalogResults');
    if (!form || !container) return;

    let timer;

    function paramsFromForm() {
        return new URLSearchParams(new FormData(form)).toString();
    }

    async function loadCatalog(pushState = true) {
        const qs = paramsFromForm();
        const url = '/php/properties/fragment.php?' + qs;
        container.classList.add('is-loading');
        try {
            const res = await fetch(url);
            const data = await res.json();
            if (!data.success) return;
            container.innerHTML = data.html;
            if (pushState) {
                const newUrl = form.action.split('?')[0] + '?' + qs;
                history.pushState({ catalog: true }, '', newUrl);
            }
            document.dispatchEvent(new CustomEvent('favorites:rebind'));
        } finally {
            container.classList.remove('is-loading');
        }
    }

    form.addEventListener('change', () => {
        clearTimeout(timer);
        timer = setTimeout(() => loadCatalog(true), 350);
    });
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        loadCatalog(true);
    });

    container.addEventListener('click', (e) => {
        const a = e.target.closest('.pagination__btn[data-page]');
        if (!a) return;
        e.preventDefault();
        const pageInput = form.querySelector('[name="page"]');
        if (pageInput) pageInput.value = a.dataset.page;
        loadCatalog(true);
    });

    window.addEventListener('popstate', () => location.reload());

    const saveBtn = document.getElementById('saveSearchBtn');
    if (saveBtn && window.EcoApi) {
        saveBtn.addEventListener('click', async () => {
            const filters = Object.fromEntries(new FormData(form).entries());
            delete filters.page;
            const name = prompt('Название поиска', 'Мой поиск');
            if (!name) return;
            await EcoApi.fetch('/php/saved_searches/save.php', {
                method: 'POST',
                body: JSON.stringify({ name, filters })
            });
            alert('Поиск сохранён. Уведомления придут на email.');
        });
    }
})();
