const persianDateFormatter = new Intl.DateTimeFormat("fa-IR", {
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
});

export function toPersianDate(value: string | Date | null | undefined): string {
    if (!value) return "—";
    const date = typeof value === "string" ? new Date(value) : value;
    if (Number.isNaN(date.getTime())) {
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
    const date = typeof value === "string" ? new Date(value) : value;
    if (Number.isNaN(date.getTime())) {
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
    const date = typeof value === "string" ? new Date(value) : value;
    if (Number.isNaN(date.getTime())) {
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

/** Group a 16-digit card number as 4-4-4-4 for display; passes through others. */
export function formatCardNumber(
    value: string | null | undefined,
): string | null {
    if (!value) return null;
    const digits = value.replace(/\D/g, "");
    if (digits.length !== 16) return value;
    return digits.replace(/(\d{4})(?=\d)/g, "$1-");
}
