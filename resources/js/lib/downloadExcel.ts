/**
 * Validate and trigger a browser download for an Excel (.xlsx) response.
 * Rejects HTML/JSON/login redirects masquerading as successful downloads.
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

export async function downloadExcelResponse(
    response: Response,
    fallbackFilename: string,
): Promise<void> {
    if (response.type === 'opaqueredirect' || (response.status >= 300 && response.status < 400)) {
        throw new ExcelDownloadError('Redirected');
    }

    if (!response.ok) {
        throw new ExcelDownloadError(`HTTP ${response.status}`);
    }

    const contentType = (response.headers.get('Content-Type') || '').toLowerCase();
    if (
        contentType.includes('text/html') ||
        contentType.includes('application/json') ||
        contentType.includes('text/json') ||
        contentType.includes('application/problem+json')
    ) {
        throw new ExcelDownloadError('Invalid content type');
    }

    const blob = await response.blob();
    if (!(await looksLikeXlsx(blob))) {
        throw new ExcelDownloadError('Invalid excel payload');
    }

    const disposition = response.headers.get('Content-Disposition') || '';
    const filename = parseFilename(disposition, fallbackFilename);
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename.endsWith('.xlsx') ? filename : `${filename}.xlsx`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
}
