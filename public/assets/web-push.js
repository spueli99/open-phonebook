'use strict';

(() => {
    const root = document.getElementById('web-push-setup');

    if (root === null) {
        return;
    }

    const status = document.getElementById('web-push-status');
    const enable = document.getElementById('web-push-enable');
    const disable = document.getElementById('web-push-disable');

    if (
        status === null
        || enable === null
        || disable === null
    ) {
        return;
    }

    const label = (name) => root.dataset[name] || '';
    const setStatus = (text) => {
        status.textContent = text;
    };
    const browserId = () => {
        const key = 'openPhonebookWebPushBrowserId';
        const existing = localStorage.getItem(key);

        if (/^[a-f0-9]{32}$/.test(existing || '')) {
            return existing;
        }

        const bytes = new Uint8Array(16);
        crypto.getRandomValues(bytes);
        const generated = Array.from(
            bytes,
            (value) => value.toString(16).padStart(2, '0')
        ).join('');
        localStorage.setItem(key, generated);

        return generated;
    };
    const base64UrlToUint8Array = (value) => {
        const padding = '='.repeat((4 - value.length % 4) % 4);
        const base64 = (value + padding)
            .replace(/-/g, '+')
            .replace(/_/g, '/');
        const raw = atob(base64);
        return Uint8Array.from(raw, (character) =>
            character.charCodeAt(0)
        );
    };
    const supported = () =>
        window.isSecureContext
        && 'serviceWorker' in navigator
        && 'PushManager' in window
        && 'Notification' in window
        && 'crypto' in window;
    const post = async (url, fields) => {
        const body = new URLSearchParams(fields);
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type':
                    'application/x-www-form-urlencoded;charset=UTF-8',
            },
            body,
        });

        if (!response.ok) {
            throw new Error('Web Push request failed.');
        }
    };
    const registration = async () => {
        await navigator.serviceWorker.register('/service-worker.js', {
            scope: '/',
        });

        return navigator.serviceWorker.ready;
    };
    const syncSubscription = async (subscription) => {
        const serialized = subscription.toJSON();
        const keys = serialized.keys || {};
        const encodings =
            PushManager.supportedContentEncodings || [];

        await post('/notifications/web-push/subscribe/', {
            _csrf: root.dataset.csrfToken || '',
            endpoint: serialized.endpoint || '',
            p256dh: keys.p256dh || '',
            auth: keys.auth || '',
            content_encoding: encodings[0] || 'aes128gcm',
            browser_id: browserId(),
        });
    };
    const refresh = async () => {
        if (!supported()) {
            setStatus(label('labelUnsupported'));
            enable.disabled = true;
            disable.disabled = true;
            return;
        }

        if (Notification.permission === 'denied') {
            setStatus(label('labelDenied'));
            enable.disabled = true;
            disable.disabled = false;
            return;
        }

        try {
            const current = await (await registration())
                .pushManager
                .getSubscription();
            if (current !== null) {
                await syncSubscription(current);
            }

            setStatus(
                current === null
                    ? label('labelDisabled')
                    : label('labelEnabled')
            );
            enable.disabled = current !== null;
            disable.disabled = current === null;
        } catch {
            setStatus(label('labelError'));
        }
    };

    enable.addEventListener('click', async () => {
        try {
            if (!supported()) {
                setStatus(label('labelUnsupported'));
                return;
            }

            const permission = await Notification.requestPermission();

            if (permission !== 'granted') {
                setStatus(label('labelDenied'));
                return;
            }

            const worker = await registration();
            let subscription =
                await worker.pushManager.getSubscription();

            if (subscription === null) {
                subscription = await worker.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: base64UrlToUint8Array(
                        root.dataset.vapidPublicKey || ''
                    ),
                });
            }

            await syncSubscription(subscription);

            await refresh();
        } catch {
            setStatus(label('labelError'));
        }
    });

    disable.addEventListener('click', async () => {
        try {
            await post('/notifications/web-push/unsubscribe/', {
                _csrf: root.dataset.csrfToken || '',
                browser_id: browserId(),
            });

            const worker = await registration();
            const subscription =
                await worker.pushManager.getSubscription();

            if (subscription !== null) {
                await subscription.unsubscribe();
            }

            await refresh();
        } catch {
            setStatus(label('labelError'));
        }
    });

    refresh();
})();
