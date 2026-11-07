const persianDateFormatter = new Intl.DateTimeFormat("fa-IR", {
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
});

function parseDate(value: string | Date | null | undefined): Date | null {
    if (!value) return null;
    const date = typeof value === "string" ? new Date(value) : value;
    return Number.isNaN(date.getTime()) ? null : date;
}

export function toPersianDate(value: string | Date | null | undefined): string {
    if (!value) return "—";
    const date = parseDate(value);
    if (!date) {
        return typeof value === "string" ? value : String(value);
    }
    return persianDateFormatter.format(date);
}

const persianTimeFormatter = new Intl.DateTimeFormat("fa-IR", {
    hour: "2-digit",
    minute: "2-digit",
});

export function toPersianTime(value: string | Date | null | undefined): string {
    if (!value) return "—";
    const date = parseDate(value);
    if (!date) {
        return typeof value === "string" ? value : String(value);
    }
    return persianTimeFormatter.format(date);
}

const persianRelativeFormatter = new Intl.RelativeTimeFormat("fa-IR", {
    numeric: "auto",
});

/**
 * «۳ روز پیش»-style stamp for a past (or clock-skewed future) timestamp.
 *
 * The largest unit that fits carries the sentence, so a minute-old write says
 * «چند لحظه پیش» and a three-month-old one does not pretend to more precision
 * than a list row needs.
 */
export function toPersianRelative(
    value: string | Date | null | undefined,
): string {
    if (!value) return "—";
    const date = parseDate(value);
    if (!date) {
        return typeof value === "string" ? value : String(value);
    }

    const seconds = Math.round((date.getTime() - Date.now()) / 1000);
    const units: [Intl.RelativeTimeFormatUnit, number][] = [
        ["year", 365 * 24 * 60 * 60],
        ["month", 30 * 24 * 60 * 60],
        ["week", 7 * 24 * 60 * 60],
        ["day", 24 * 60 * 60],
        ["hour", 60 * 60],
        ["minute", 60],
    ];

    for (const [unit, secondsInUnit] of units) {
        if (Math.abs(seconds) >= secondsInUnit) {
            return persianRelativeFormatter.format(
                Math.round(seconds / secondsInUnit),
                unit,
            );
        }
    }

    return persianRelativeFormatter.format(Math.round(seconds), "second");
}
