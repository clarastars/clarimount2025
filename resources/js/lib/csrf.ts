/**
 * Resolve the current CSRF token for fetch/XHR requests.
 * Prefers the meta tag (kept in sync after each Inertia navigation),
 * then falls back to the XSRF-TOKEN cookie.
 */
import { router } from '@inertiajs/vue3';

export function getCsrfToken(): string {
    const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')?.trim();
    if (metaToken) {
        return metaToken;
    }

    const cookie = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='));

    if (cookie) {
        const value = cookie.split('=').slice(1).join('=');
        if (value) {
            try {
                return decodeURIComponent(value);
            } catch {
                return value;
            }
        }
    }

    return '';
}

export function csrfHeaders(): Record<string, string> {
    const token = getCsrfToken();

    return {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(token ? { 'X-CSRF-TOKEN': token, 'X-XSRF-TOKEN': token } : {}),
    };
}

export function syncCsrfMetaToken(token: string | undefined | null): void {
    if (!token) {
        return;
    }

    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) {
        meta.setAttribute('content', token);
    }
}

/**
 * Refresh CSRF token from the server without losing page state (Inertia partial reload).
 */
export function refreshCsrfToken(): Promise<boolean> {
    return new Promise((resolve) => {
        let settled = false;

        const finish = (ok: boolean) => {
            if (settled) {
                return;
            }
            settled = true;
            resolve(ok);
        };

        router.reload({
            only: ['csrf_token'],
            preserveState: true,
            preserveScroll: true,
            onSuccess: (page) => {
                const token = (page.props as { csrf_token?: string }).csrf_token ?? getCsrfToken();
                syncCsrfMetaToken(token);
                finish(!!token);
            },
            onError: () => finish(false),
            onFinish: () => {
                if (!settled) {
                    finish(!!getCsrfToken());
                }
            },
        });
    });
}

/**
 * Reload the page when the session is fully expired (after a failed CSRF refresh + retry).
 */
export function reloadAfterSessionExpired(): void {
    router.reload();
}

function buildHeaders(init?: RequestInit): Headers {
    const headers = new Headers(init?.headers);

    for (const [key, value] of Object.entries(csrfHeaders())) {
        headers.set(key, value);
    }

    return headers;
}

/**
 * fetch() wrapper: sends CSRF headers, refreshes token and retries once on HTTP 419.
 */
export async function fetchWithCsrf(input: RequestInfo | URL, init: RequestInit = {}): Promise<Response> {
    const send = () =>
        fetch(input, {
            ...init,
            credentials: init.credentials ?? 'same-origin',
            headers: buildHeaders(init),
        });

    let response = await send();

    if (response.status !== 419) {
        return response;
    }

    const refreshed = await refreshCsrfToken();
    if (refreshed) {
        response = await send();
    }

    if (response.status === 419) {
        reloadAfterSessionExpired();
    }

    return response;
}
