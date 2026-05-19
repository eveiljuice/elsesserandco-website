/**
 * Общий fetch с CSRF для JSON API.
 */
(function (global) {
    'use strict';

    function csrfToken() {
        const m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.content : '';
    }

    async function apiFetch(url, options = {}) {
        const headers = Object.assign(
            { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken() },
            options.headers || {}
        );
        const res = await fetch(url, Object.assign({}, options, { headers }));
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            const err = new Error(data.error || res.statusText);
            err.status = res.status;
            err.data = data;
            throw err;
        }
        return data;
    }

    global.EcoApi = { fetch: apiFetch, csrfToken };
})(typeof window !== 'undefined' ? window : globalThis);
