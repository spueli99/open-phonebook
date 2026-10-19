(() => {
    const form = document.getElementById('invite-capture');
    const tokenField = document.getElementById('invite-token');
    const status = document.getElementById('invite-status');

    if (!(form instanceof HTMLFormElement) || !(tokenField instanceof HTMLInputElement)) {
        return;
    }

    const token = window.location.hash.slice(1);

    if (!/^[a-f0-9]{64}$/.test(token)) {
        if (status) {
            status.textContent = 'Invitation token is missing or invalid.';
        }

        return;
    }

    tokenField.value = token;
    history.replaceState(null, '', window.location.pathname);
    form.submit();
})();
