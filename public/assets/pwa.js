'use strict';

(() => {
    if (!('serviceWorker' in navigator) || !window.isSecureContext) {
        return;
    }

    navigator.serviceWorker.register('/service-worker.js', {
        scope: '/',
    }).catch(() => {
        // PWA registration failure must not break the application UI.
    });
})();
