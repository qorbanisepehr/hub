import {
    CalendarDate,
    PersianCalendar,
    endOfMonth,
    getDayOfWeek,
    getLocalTimeZone,
    toCalendar,
    today,
} from "@internationalized/date";

/**
 * Jalali (Solar Hijri) calendar arithmetic for the client.
 *
 * Everywhere else the hub renders a Jalali *label* with
 * `Intl.DateTimeFormat("fa-IR")` (`lib/date-format`). A month *grid* needs
 * more than labels: it has to know how many days the month has, which
 * weekday the first day falls on, and which Gregorian range covers it so
 * the list endpoint's `held_on` window fetches exactly those days.
 *
 * `@internationalized/date` (already a dependency) owns the Persian
 * calendar, so the hub never hand-rolls leap-year rules: a `CalendarDate`
 * built in the Persian calendar exposes the Jalali `year`/`month`/`day`,
 * and `toString()` gives back the Gregorian ISO date the API speaks.
 */

const persianCalendar = new PersianCalendar();

export const JALALI_MONTHS: readonly string[] = [
    "فروردین",
    "اردیبهشت",
    "خرداد",
    "تیر",
    "مرداد",
    "شهریور",
    "مهر",
    "آبان",
    "آذر",
    "دی",
    "بهمن",
    "اسفند",
];

/** Persian week, Saturday first — the order the calendar grid renders in. */
export const JALALI_WEEKDAYS: readonly string[] = [
    "شنبه",
    "یک‌شنبه",
    "دوشنبه",
    "سه‌شنبه",
    "چهارشنبه",
    "پنج‌شنبه",
    "جمعه",
];

export type JalaliMonth = { year: number; month: number };

/** One day of a Jalali month, keyed by both calendars. */
export type JalaliMonthDay = {
    day: number;
    /** Jalali `yyyy-MM-dd`, the shape of `held_on_jalali`. */
    jalali: string;
    /** Gregorian `yyyy-MM-dd`, the shape of `held_on` and the API window. */
    gregorian: string;
};

function pad(value: number): string {
    return String(value).padStart(2, "0");
}

function jalaliDate({ year, month }: JalaliMonth, day: number): CalendarDate {
    return new CalendarDate(persianCalendar, year, month, day);
}

/** Today's month in the Persian calendar. */
export function todayJalaliMonth(): JalaliMonth {
    const current = toCalendar(today(getLocalTimeZone()), persianCalendar);

    return { year: current.year, month: current.month };
}

/** Today as a Gregorian ISO date — for highlighting the current cell. */
export function todayIso(): string {
    return today(getLocalTimeZone()).toString();
}

/** Move `delta` months forward (or back) from a Jalali month. */
export function shiftJalaliMonth(
    { year, month }: JalaliMonth,
    delta: number,
): JalaliMonth {
    let nextMonth = month + delta;
    let nextYear = year;

    while (nextMonth > 12) {
        nextMonth -= 12;
        nextYear += 1;
    }

    while (nextMonth < 1) {
        nextMonth += 12;
        nextYear -= 1;
    }

    return { year: nextYear, month: nextMonth };
}

/** «شهریور ۱۴۰۴» — month name plus Jalali year in Persian digits. */
export function jalaliMonthLabel({ year, month }: JalaliMonth): string {
    return `${JALALI_MONTHS[month - 1]} ${year.toLocaleString("fa-IR")}`;
}

/**
 * Gregorian `yyyy-MM-dd` bounds of a Jalali month, for the API's
 * `date_from`/`date_to` window (which filters the canonical `held_on`).
 */
export function jalaliMonthBounds(month: JalaliMonth): {
    from: string;
    to: string;
} {
    const first = jalaliDate(month, 1);

    return { from: first.toString(), to: endOfMonth(first).toString() };
}

/**
 * The Jalali month laid out as weeks of seven cells, Saturday first. `null`
 * marks the leading/trailing blanks that pad the first and last weeks.
 */
export function buildJalaliMonthWeeks(
    month: JalaliMonth,
): (JalaliMonthDay | null)[][] {
    const first = jalaliDate(month, 1);
    const leadingBlanks = getDayOfWeek(first, "fa-IR");
    const daysInMonth = endOfMonth(first).day;

    const cells: (JalaliMonthDay | null)[] = [];
    for (let blank = 0; blank < leadingBlanks; blank += 1) {
        cells.push(null);
    }

    for (let day = 1; day <= daysInMonth; day += 1) {
        cells.push({
            day,
            jalali: `${month.year}-${pad(month.month)}-${pad(day)}`,
            gregorian: jalaliDate(month, day).toString(),
        });
    }

    while (cells.length % 7 !== 0) {
        cells.push(null);
    }

    const weeks: (JalaliMonthDay | null)[][] = [];
    for (let index = 0; index < cells.length; index += 7) {
        weeks.push(cells.slice(index, index + 7));
    }

    return weeks;
}
