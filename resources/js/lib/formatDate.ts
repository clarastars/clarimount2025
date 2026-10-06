/**
 * Parse a calendar date without UTC day-shift for YYYY-MM-DD values.
 */
export function parseCalendarDate(value: string | Date | null | undefined): Date | null {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    if (value instanceof Date) {
        return Number.isNaN(value.getTime()) ? null : value;
    }

    const raw = String(value).trim();
    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(raw);

    if (match) {
        const year = Number(match[1]);
        const month = Number(match[2]);
        const day = Number(match[3]);
        const date = new Date(year, month - 1, day);

        return Number.isNaN(date.getTime()) ? null : date;
    }

    const date = new Date(raw);

    return Number.isNaN(date.getTime()) ? null : date;
}

function resolveDateLocale(locale: string): string {
    return locale.startsWith('ar') ? 'ar-SA-u-ca-gregory' : 'en-GB';
}

/**
 * Unambiguous calendar date (day + month name + year) so 5/10 is never confused
 * between 5 Oct and 10 May.
 */
export function formatDisplayDate(
    value: string | Date | null | undefined,
    locale: string = 'en',
    empty = '-',
): string {
    const date = parseCalendarDate(value);

    if (!date) {
        return empty;
    }

    return new Intl.DateTimeFormat(resolveDateLocale(locale), {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(date);
}

/**
 * Numeric Gregorian date in day/month/year order (never US month-first).
 */
export function formatDisplayDateNumeric(
    value: string | Date | null | undefined,
    locale: string = 'en',
    empty = '-',
): string {
    const date = parseCalendarDate(value);

    if (!date) {
        return empty;
    }

    return new Intl.DateTimeFormat(resolveDateLocale(locale), {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(date);
}
