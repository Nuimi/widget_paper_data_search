// Shared by both lookup entry points; runs only in the extension service worker.
globalThis.createQFinderClient = function ({ storage, fetcher = fetch, now = () => Date.now(), onSession = async () => {} }) {
    const base = 'https://imitweby.uhk.cz/widget';
    let generation = 0;
    let lookupPending = false;
    let authPending = false;

    async function clearSession() {
        generation++;
        await storage.remove(['token', 'expiresAt', 'lastResult']);
        await onSession(null);
    }

    async function session() {
        const state = await storage.get(['token', 'expiresAt']);
        if (!state.token || !Number.isFinite(state.expiresAt) || state.expiresAt * 1000 <= now()) {
            await clearSession();
            return null;
        }
        return state;
    }

    async function request(path, options = {}) {
        let response;
        try {
            response = await fetcher(base + path, { ...options, cache: 'no-store', signal: AbortSignal.timeout(60000) });
            const data = await response.json();
            if (!data || typeof data !== 'object' || Array.isArray(data)) {
                throw new SyntaxError('Expected a JSON object');
            }
            return { response, data };
        } catch (error) {
            let message;
            let code;
            if (error?.name === 'TimeoutError' || error?.name === 'AbortError') {
                code = 'backend_timeout';
                message = 'The server did not finish the request within 60 seconds. Please retry.';
            } else if (response) {
                code = 'backend_invalid_response';
                message = `The server returned an unreadable response (HTTP ${response.status}). Ask the administrator to check the PHP/server error log.`;
            } else {
                code = 'backend_connection';
                message = 'Could not reach imitweby.uhk.cz. Check your connection, VPN and extension site permissions.';
            }
            // Log only a fixed category/status, never tokens, response bodies or query URLs.
            console.warn('Q-Finder request failed', { code, status: response?.status ?? 0 });
            return { response: { ok: false, status: response?.status ?? 0 }, data: { error: message, code } };
        }
    }

    return {
        async status() {
            const state = await session();
            if (state) await onSession(state.expiresAt); // Recreate alarms after browser restart.
            return { authenticated: Boolean(state) };
        },
        async login(login, password) {
            if (authPending) return { error: 'Authentication is already in progress.' };
            authPending = true;
            try {
                const existing = await session();
                if (existing) return { error: 'Log out before signing in again.' };
                const { response, data } = await request('/ajax/logIn', {
                    method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: new URLSearchParams({ login, password }).toString(),
                });
                if (!response.ok || !/^[a-f0-9]{64}$/.test(data.token || '') || !Number.isFinite(data.expiresAt) || data.expiresAt * 1000 <= now()) {
                    return { error: data.error || data.message || 'Login failed.' };
                }
                generation++;
                await storage.set({ token: data.token, expiresAt: data.expiresAt });
                await onSession(data.expiresAt);
                return { ok: true };
            } finally { authPending = false; }
        },
        async logout() {
            if (authPending) return { error: 'Authentication is already in progress.' };
            authPending = true;
            generation++; // Discard lookups that finish while revocation is pending.
            try {
                const state = await session();
                if (state) {
                    const { response, data } = await request('/ajax/revokeToken', {
                        method: 'POST', headers: { Authorization: `Bearer ${state.token}` },
                    });
                    if (!response.ok) return { error: data.error || 'Could not log out. Please retry.' };
                }
                await clearSession();
                return { ok: true };
            } finally { authPending = false; }
        },
        async lookup(rawQuery) {
            const query = typeof rawQuery === 'string' ? rawQuery.trim() : '';
            if (!query) return { error: 'Enter an article title or DOI.' };
            if (new TextEncoder().encode(query).length > 2000) return { error: 'Query is too long (maximum 2000 bytes).' };
            if (lookupPending || authPending) return { error: 'A request is already in progress. Please wait.' };
            lookupPending = true;
            try {
                const state = await session();
                if (!state) return { error: 'Please sign in again.', authRequired: true };
                const current = generation;
                const { response, data } = await request('/presenters/api.php?' + new URLSearchParams({ q: query }), {
                    headers: { Authorization: `Bearer ${state.token}` },
                });
                const latest = await session();
                if (current !== generation || latest?.token !== state.token) {
                    return { error: 'Session changed. Please search again.', authRequired: !latest, cancelled: true };
                }
                if (response.status === 401) {
                    await clearSession();
                    return { error: 'Session expired or revoked. Please sign in again.', authRequired: true };
                }
                if (!response.ok || data.error) return { error: data.error || 'Lookup failed. Please retry.' };
                await storage.set({ lastResult: data });
                return { ok: true, data };
            } finally { lookupPending = false; }
        },
    };
};
