'use strict';

(() => {
    const form = document.getElementById('password-reset-capture');
    const token = document.getElementById('password-reset-token');
    const status = document.getElementById('password-reset-status');

    if (form === null || token === null || status === null) {
        return;
    }

    const value = window.location.hash.slice(1).trim();
    window.history.replaceState(
        null,
        document.title,
        window.location.pathname
    );

    if (!/^[a-f0-9]{64}$/.test(value)) {
        status.textContent = status.dataset.invalid || '';
        return;
    }

    token.value = value;
    form.submit();
})();
