/**
 * Display formatting for dates in the UI: dd-mm-yyyy, and 24-hour time where a
 * time is shown.
 *
 * These are display-only. Nothing here touches what gets submitted: date inputs
 * are native `<input type="date">`, whose value is always ISO yyyy-mm-dd
 * regardless of how the browser chooses to render it, and that ISO string is
 * what reaches the server. Formatting and storage are deliberately separate --
 * keep it that way, or a display tweak becomes a data bug.
 *
 * This lived as four near-identical copies across the pages, which is how one
 * of them drifted to en-GB (dd/mm/yyyy) while the rest used dashes.
 */

const pad = (value: number) => String(value).padStart(2, '0')

/**
 * A bare yyyy-mm-dd (what a DATE column and a native date input both produce).
 * `new Date()` reads these as UTC midnight and the getters then report local
 * time, which rolls the day backwards for anyone west of UTC -- a joining date
 * of 2026-07-20 would render as 19-07-2026. Splitting the string keeps the day
 * the server actually sent.
 */
const DATE_ONLY = /^(\d{4})-(\d{2})-(\d{2})$/

function parse(value: string): Date | null {
    const dateOnly = DATE_ONLY.exec(value)

    if (dateOnly) {
        const [, year, month, day] = dateOnly

        return new Date(Number(year), Number(month) - 1, Number(day))
    }

    const date = new Date(value)

    return Number.isNaN(date.getTime())
        ? null
        : date
}

/**
 * dd-mm-yyyy. Empty values render as `fallback`; a value that is not a date is
 * returned untouched, so a bad record shows what it actually holds instead of
 * "NaN-NaN-NaN".
 */
export function formatDate(
    value?: string | null,
    fallback = '-',
): string {
    if (!value) {
        return fallback
    }

    const date = parse(value)

    if (!date) {
        return value
    }

    return asDate(date)
}

function asDate(date: Date): string {
    return [
        pad(date.getDate()),
        pad(date.getMonth() + 1),
        date.getFullYear(),
    ].join('-')
}

/**
 * dd-mm-yyyy, HH:mm:ss -- always 24-hour, never am/pm.
 */
export function formatDateTime(
    value?: string | null,
    fallback = '-',
): string {
    if (!value) {
        return fallback
    }

    const date = parse(value)

    if (!date) {
        return value
    }

    const time = [
        pad(date.getHours()),
        pad(date.getMinutes()),
        pad(date.getSeconds()),
    ].join(':')

    return `${asDate(date)}, ${time}`
}
