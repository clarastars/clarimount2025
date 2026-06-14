/**
 * Resolve the current CSRF token for fetch/XHR requests.
 * Prefers the XSRF-TOKEN cookie (refreshed on each Laravel response),
 * then falls back to the meta tag from the initial page load.
 */
export function getCsrfToken(): string {
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

    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')?.trim() ?? '';
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
