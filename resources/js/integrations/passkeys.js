const loadWebAuthn = () => import('@simplewebauthn/browser');
const browserRequest = (...args) => fetch(...args);
const defaultRoutes = Object.freeze({
    register: { options: '/user/passkeys/options', submit: '/user/passkeys' },
    verify: { options: '/passkeys/login/options', submit: '/passkeys/login' },
});

function cancelled() {
    return Object.assign(new Error('Passkey operation cancelled'), { name: 'UserCancelledError' });
}

function sameOriginRoute(route) {
    const url = new URL(route, window.location.href);
    if (url.origin !== window.location.origin || url.username || url.password) throw new Error('Invalid passkey endpoint');
    return url.href;
}

function csrfHeaders() {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (token) return { 'X-CSRF-TOKEN': token };
    const cookie = document.cookie.split('; ').find(value => value.startsWith('XSRF-TOKEN='))?.slice('XSRF-TOKEN='.length);
    return cookie ? { 'X-XSRF-TOKEN': decodeURIComponent(cookie) } : {};
}

export function createPasskeysAdapter(sdk = null, load = loadWebAuthn, request = browserRequest) {
    let activeOperation = null;
    let loading = null;

    function assertCurrent(current) {
        if (activeOperation !== current || current.controller.signal.aborted) throw cancelled();
    }

    function cancel(owner) {
        const current = activeOperation;
        if (current?.owner !== owner) return;
        activeOperation = null;
        current.controller.abort();
        if (current.ceremony) sdk.WebAuthnAbortService.cancelCeremony();
    }

    async function requestJson(current, url, method, body) {
        assertCurrent(current);
        const headers = { Accept: 'application/json' };
        if (body !== undefined) Object.assign(headers, { 'Content-Type': 'application/json' }, csrfHeaders());
        const response = await request(url, {
            method, headers, credentials: 'same-origin', redirect: 'error',
            signal: current.controller.signal,
            ...(body === undefined ? {} : { body: JSON.stringify(body) }),
        });
        assertCurrent(current);
        if (!response.ok) throw new Error('Passkey request failed');
        const payload = await response.json();
        assertCurrent(current);
        return payload;
    }

    async function run(owner, operation, options) {
        if (activeOperation) cancel(activeOperation.owner);
        const current = { owner, controller: new AbortController(), ceremony: false };
        activeOperation = current;
        try {
            if (!sdk) {
                loading ??= load().catch(error => { loading = null; throw error; });
                sdk = await loading;
            }
            assertCurrent(current);
            if (!sdk.browserSupportsWebAuthn()) throw Object.assign(new Error('WebAuthn unavailable'), { name: 'NotSupportedError' });
            const optionsUrl = sameOriginRoute(options.routes?.options ?? defaultRoutes[operation].options);
            const submitUrl = sameOriginRoute(options.routes?.submit ?? defaultRoutes[operation].submit);
            const payload = await requestJson(current, optionsUrl, 'GET');
            assertCurrent(current);
            current.ceremony = true;
            const credential = await sdk[operation === 'register' ? 'startRegistration' : 'startAuthentication']({ optionsJSON: payload.options });
            current.ceremony = false;
            assertCurrent(current);
            const body = operation === 'register'
                ? { name: options.name, credential }
                : { credential, remember: (typeof options.remember === 'function' ? options.remember() : options.remember) ?? false };
            const response = await requestJson(current, submitUrl, 'POST', body);
            assertCurrent(current);
            return response;
        } catch (error) {
            if (current.controller.signal.aborted || ['AbortError', 'NotAllowedError'].includes(error?.name)) throw cancelled();
            throw error;
        } finally {
            if (activeOperation === current) activeOperation = null;
        }
    }

    return {
        isSupported: () => sdk ? sdk.browserSupportsWebAuthn() : typeof globalThis.PublicKeyCredential === 'function',
        isCancelled: error => error?.name === 'UserCancelledError',
        register: (owner, options) => run(owner, 'register', options),
        verify: (owner, options = {}) => run(owner, 'verify', options),
        cancel,
    };
}
