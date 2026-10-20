/**
 * Validate and trigger a browser download for an Excel (.xlsx) response.
 * Prefers ZIP magic-byte detection over Content-Type, because some browsers,
 * proxies, and PHP responses mislabel successful .xlsx downloads.
 */
export class ExcelDownloadError extends Error {
    constructor(message: string) {
        super(message);
        this.name = 'ExcelDownloadError';
    }
}

function parseFilename(disposition: string, fallback: string): string {
    const utfMatch = /filename\*=UTF-8''([^;]+)/i.exec(disposition);
    if (utfMatch?.[1]) {
        try {
            return decodeURIComponent(utfMatch[1]);
        } catch {
            return utfMatch[1];
        }
    }

    const basicMatch = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/.exec(disposition);
    if (basicMatch?.[1]) {
        try {
            return decodeURIComponent(basicMatch[1].replace(/['"]/g, ''));
        } catch {
            return basicMatch[1].replace(/['"]/g, '');
        }
    }

    return fallback;
}

async function looksLikeXlsx(blob: Blob): Promise<boolean> {
    if (blob.size < 4) {
        return false;
    }

    const header = new Uint8Array(await blob.slice(0, 4).arrayBuffer());

    // XLSX is a ZIP package: PK\x03\x04
    return header[0] === 0x50 && header[1] === 0x4b && header[2] === 0x03 && header[3] === 0x04;
}

async function extractJsonMessage(blob: Blob): Promise<string | null> {
    try {
        const text = await blob.text();
        const data = JSON.parse(text) as { message?: unknown };
        if (typeof data?.message === 'string' && data.message.trim() !== '') {
            return data.message;
        }
    } catch {
        // ignore parse errors
    }

    return null;
}

function triggerBlobDownload(blob: Blob, filename: string): void {
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename.endsWith('.xlsx') ? filename : `${filename}.xlsx`;
    link.rel = 'noopener';
    link.style.display = 'none';
    document.body.appendChild(link);
    link.click();

    // Revoking immediately cancels the download on Safari and some Chromium builds.
    window.setTimeout(() => {
        link.remove();
        window.URL.revokeObjectURL(url);
    }, 60_000);
}

/**
 * Native browser navigation download (most reliable across devices).
 * Uses a hidden iframe so the current page state is preserved.
 */
export function downloadViaBrowserNavigation(url: string): void {
    const iframe = document.createElement('iframe');
    iframe.style.display = 'none';
    iframe.setAttribute('aria-hidden', 'true');
    iframe.src = url;
    document.body.appendChild(iframe);

    window.setTimeout(() => {
        iframe.remove();
    }, 120_000);
}

export async function downloadExcelResponse(
    response: Response,
    fallbackFilename: string,
): Promise<void> {
    if (response.type === 'opaqueredirect' || (response.status >= 300 && response.status < 400)) {
        throw new ExcelDownloadError('Redirected');
    }

    const contentType = (response.headers.get('Content-Type') || '').toLowerCase();
    const blob = await response.blob();

    // Successful Excel payload wins even when Content-Type is wrong/missing.
    if (await looksLikeXlsx(blob)) {
        const disposition = response.headers.get('Content-Disposition') || '';
        const filename = parseFilename(disposition, fallbackFilename);
        triggerBlobDownload(blob, filename);
        return;
    }

    const jsonMessage = await extractJsonMessage(blob);

    if (!response.ok) {
        throw new ExcelDownloadError(jsonMessage || `HTTP ${response.status}`);
    }

    if (response.redirected) {
        throw new ExcelDownloadError(jsonMessage || 'Redirected');
    }

    if (
        contentType.includes('text/html')
        || blob.type.includes('text/html')
    ) {
        throw new ExcelDownloadError(jsonMessage || 'Invalid content type');
    }

    if (
        contentType.includes('application/json')
        || contentType.includes('text/json')
        || contentType.includes('application/problem+json')
        || blob.type.includes('json')
    ) {
        throw new ExcelDownloadError(jsonMessage || 'Invalid content type');
    }

    throw new ExcelDownloadError(jsonMessage || 'Invalid excel payload');
}
