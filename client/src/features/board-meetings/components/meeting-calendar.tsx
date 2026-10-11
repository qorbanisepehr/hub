import { Link } from "@tanstack/react-router";
import {
    IconCalendar,
    IconCalendarMonth,
    IconChevronDown,
    IconChevronLeft,
    IconChevronRight,
} from "@tabler/icons-react";
import { useMemo, useState, type ReactNode } from "react";

import { Button } from "@/components/ui/button";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import {
    ViewModeSwitcher,
    type DataTableViewOption,
} from "@/components/data-table";
import {
    JALALI_MONTHS,
    JALALI_WEEKDAYS,
    JALALI_WEEKDAYS_SHORT,
    buildJalaliMonthWeeks,
    formatJalaliYear,
    jalaliMonthLabel,
    shiftJalaliMonth,
    todayIso,
    todayJalaliMonth,
    type JalaliMonth,
    type JalaliMonthDay,
} from "@/lib/jalali";
import { cn } from "@/lib/utils";

import type { BoardMeetingDto } from "../types";

/** Which rendering the calendar shows: one month's days, or the whole year. */
export type CalendarSpan = "month" | "year";

type MeetingCalendarProps = {
    month: JalaliMonth;
    onMonthChange: (month: JalaliMonth) => void;
    /** The month's day grid, or the whole year's months. */
    span: CalendarSpan;
    onSpanChange: (span: CalendarSpan) => void;
    /** Meetings the page has already scoped to the active span. */
    meetings: BoardMeetingDto[];
};

/**
 * The drill-down selector the title opens: the months of the shown year, or a
 * page of years. It is a *selector*, not a rendering — opening it never
 * changes `span`, so «ماه | سال» stays exactly where the reader put it.
 */
type CalendarPicker = "months" | "years";

/** Years per year-picker page — a dozen fills a 3/4-column grid neatly. */
const YEARS_PER_PAGE = 12;
/** How far back the year dropdown reaches from today, so old registries stay reachable. */
const YEAR_LOOKBACK = 20;
/** How far forward the year dropdown reaches from today. */
const YEAR_LOOKAHEAD = 3;

const SPAN_VIEWS: DataTableViewOption[] = [
    { mode: "month", label: "نمای ماه", icon: IconCalendarMonth },
    { mode: "year", label: "نمای سال", icon: IconCalendar },
];

/**
 * The «تقویم» view of the board-meeting registry.
 *
 * Two orthogonal controls, deliberately not wired to each other:
 * - **`span`** is the *rendering*: «ماه» is the day grid, «سال» is every month
 *   of the year at a glance (the TCK layout). Switched by the «ماه | سال»
 *   control, which lives in the toolbar-topping header.
 * - **`picker`** is the *selector*: clicking the title opens the months of the
 *   shown year, and the year label there opens a page of years. Choosing, in
 *   either, lands on the month grid. This is the second way to pick a year and
 *   a month, beside the two `Select` dropdowns — both are always on screen so
 *   one can be kept and the other dropped later.
 *
 * Opening the picker never moves `span`; that was the bug that made the title
 * look like it switched the whole view to the year.
 *
 * The day/week layout comes from `@internationalized/date`'s Persian calendar
 * (`lib/jalali`), so month length and first weekday are never hand-rolled.
 */
export function MeetingCalendar({
    month,
    onMonthChange,
    span,
    onSpanChange,
    meetings,
}: MeetingCalendarProps) {
    const [picker, setPicker] = useState<CalendarPicker | null>(null);

    const weeks = useMemo(() => buildJalaliMonthWeeks(month), [month]);
    const today = useMemo(() => todayIso(), []);
    const todayMonth = useMemo(() => todayJalaliMonth(), []);

    // A flat cell list with a stable key per cell: the day's Gregorian date
    // when there is one, a positional key for the blank padding cells.
    const cells = useMemo(
        () =>
            weeks.flatMap((week, weekIndex) =>
                week.map((day, dayIndex) => ({
                    day,
                    key: day ? day.gregorian : `blank-${weekIndex}-${dayIndex}`,
                })),
            ),
        [weeks],
    );

    const meetingsByDate = useMemo(() => {
        const byDate = new Map<string, BoardMeetingDto[]>();
        for (const meeting of meetings) {
            if (!meeting.held_on) {
                continue;
            }
            const day = byDate.get(meeting.held_on) ?? [];
            day.push(meeting);
            byDate.set(meeting.held_on, day);
        }
        return byDate;
    }, [meetings]);

    // The twelve months of the year, each laid out once for the year view.
    const yearMonths = useMemo(() => {
        if (span !== "year") {
            return [];
        }

        return JALALI_MONTHS.map((name, index) => {
            const monthNumber = index + 1;
            const monthWeeks = buildJalaliMonthWeeks({
                year: month.year,
                month: monthNumber,
            });
            const monthCells = monthWeeks.flatMap((week, weekIndex) =>
                week.map((day, dayIndex) => ({
                    day,
                    key: day
                        ? day.gregorian
                        : `blank-${monthNumber}-${weekIndex}-${dayIndex}`,
                })),
            );

            return { monthNumber, name, cells: monthCells };
        });
    }, [span, month.year]);

    // The dropdown reaches a window around today, widened so the month in view
    // (however far the user browsed) always has its own year in the list.
    const yearOptions = useMemo(() => {
        const min = Math.min(todayMonth.year - YEAR_LOOKBACK, month.year);
        const max = Math.max(todayMonth.year + YEAR_LOOKAHEAD, month.year);
        const years: number[] = [];
        for (let year = min; year <= max; year += 1) {
            years.push(year);
        }
        return years;
    }, [month.year, todayMonth.year]);

    const decadeStart = month.year - ((month.year - 1) % YEARS_PER_PAGE);
    const decadeYears = Array.from(
        { length: YEARS_PER_PAGE },
        (_, index) => decadeStart + index,
    );
    const decadeLabel = `${formatJalaliYear(decadeStart)} – ${formatJalaliYear(
        decadeStart + YEARS_PER_PAGE - 1,
    )}`;

    /**
     * The one place «قبل»/«بعد» are defined, so an arrow can only ever step the
     * unit of the level it sits on: a day grid moves a month, the year view and
     * the month picker move a year, the year picker moves a page of years.
     */
    const step = (direction: -1 | 1) => {
        if (picker === "years") {
            onMonthChange({
                ...month,
                year: month.year + direction * YEARS_PER_PAGE,
            });
            return;
        }

        if (picker === "months" || span === "year") {
            onMonthChange({ ...month, year: month.year + direction });
            return;
        }

        onMonthChange(shiftJalaliMonth(month, direction));
    };

    /** A chosen month is the end of the pick: show it as a day grid. */
    const chooseMonth = (next: JalaliMonth) => {
        onMonthChange(next);
        onSpanChange("month");
        setPicker(null);
    };

    /**
     * A chosen year is the whole answer in the year view (which already shows
     * its months); from the month grid or the month picker it still needs a
     * month, so the picker stays open a step.
     */
    const chooseYear = (year: number) => {
        onMonthChange({ ...month, year });
        setPicker(span === "year" ? null : "months");
    };

    /** The title drills out one level, without touching which view is shown. */
    const openPicker = () => setPicker(span === "month" ? "months" : "years");

    const monthSelect = (
        <Select
            value={String(month.month)}
            onValueChange={(value: string | null) => {
                if (value) {
                    chooseMonth({ ...month, month: Number(value) });
                }
            }}
            itemToStringLabel={(value) =>
                value ? JALALI_MONTHS[Number(value as string) - 1] : ""
            }
        >
            <SelectTrigger size="sm" className="w-28" aria-label="ماه">
                <SelectValue placeholder={JALALI_MONTHS[month.month - 1]} />
            </SelectTrigger>
            <SelectContent>
                {JALALI_MONTHS.map((name, index) => (
                    <SelectItem key={name} value={String(index + 1)}>
                        {name}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );

    const yearSelect = (
        <Select
            value={String(month.year)}
            onValueChange={(value: string | null) => {
                if (value) {
                    onMonthChange({ ...month, year: Number(value) });
                    setPicker(null);
                }
            }}
            itemToStringLabel={(value) =>
                value ? formatJalaliYear(Number(value as string)) : ""
            }
        >
            <SelectTrigger size="sm" className="w-24" aria-label="سال">
                <SelectValue placeholder={formatJalaliYear(month.year)} />
            </SelectTrigger>
            <SelectContent>
                {yearOptions.map((year) => (
                    <SelectItem key={year} value={String(year)}>
                        {formatJalaliYear(year)}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );

    // The left half of the header: the chevrons and the title. Its shape — and
    // therefore what the arrows mean — follows whichever level is on top.
    const navigation =
        picker === "years" ? (
            <>
                <NavButton label="۱۲ سال قبل" onClick={() => step(-1)}>
                    <IconChevronRight className="size-4" />
                </NavButton>
                <span className="min-w-28 text-center text-sm font-medium">
                    {decadeLabel}
                </span>
                <NavButton label="۱۲ سال بعد" onClick={() => step(1)}>
                    <IconChevronLeft className="size-4" />
                </NavButton>
            </>
        ) : picker === "months" ? (
            <>
                <NavButton label="سال قبل" onClick={() => step(-1)}>
                    <IconChevronRight className="size-4" />
                </NavButton>
                <Button
                    variant="outline"
                    size="sm"
                    className="min-w-28 justify-center gap-1"
                    onClick={() => setPicker("years")}
                >
                    {formatJalaliYear(month.year)}
                    <IconChevronDown className="size-3.5 text-muted-foreground" />
                </Button>
                <NavButton label="سال بعد" onClick={() => step(1)}>
                    <IconChevronLeft className="size-4" />
                </NavButton>
            </>
        ) : (
            <>
                <NavButton
                    label={span === "month" ? "ماه قبل" : "سال قبل"}
                    onClick={() => step(-1)}
                >
                    <IconChevronRight className="size-4" />
                </NavButton>
                <Button
                    variant="outline"
                    size="sm"
                    className="min-w-28 justify-center gap-1"
                    onClick={openPicker}
                >
                    {span === "month"
                        ? jalaliMonthLabel(month)
                        : formatJalaliYear(month.year)}
                    <IconChevronDown className="size-3.5 text-muted-foreground" />
                </Button>
                <NavButton
                    label={span === "month" ? "ماه بعد" : "سال بعد"}
                    onClick={() => step(1)}
                >
                    <IconChevronLeft className="size-4" />
                </NavButton>
            </>
        );

    return (
        <div className="flex flex-col gap-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="flex items-center gap-1.5">{navigation}</div>

                <div className="flex flex-wrap items-center gap-1.5">
                    {picker !== null && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setPicker(null)}
                        >
                            بازگشت
                        </Button>
                    )}
                    {monthSelect}
                    {yearSelect}
                    <ViewModeSwitcher
                        views={SPAN_VIEWS}
                        value={span}
                        onChange={(mode) => {
                            onSpanChange(mode as CalendarSpan);
                            setPicker(null);
                        }}
                    />
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => {
                            onMonthChange(todayJalaliMonth());
                            onSpanChange("month");
                            setPicker(null);
                        }}
                    >
                        ماه جاری
                    </Button>
                </div>
            </div>

            {picker === "years" ? (
                <div className="grid grid-cols-3 gap-1.5 rounded-lg border p-2 sm:grid-cols-4">
                    {decadeYears.map((year) => {
                        const isSelected = year === month.year;
                        const isCurrent = year === todayMonth.year;

                        return (
                            <Button
                                key={year}
                                variant={isSelected ? "default" : "ghost"}
                                size="sm"
                                className={cn(
                                    "h-9",
                                    !isSelected &&
                                        isCurrent &&
                                        "border border-primary/40",
                                )}
                                onClick={() => chooseYear(year)}
                            >
                                {formatJalaliYear(year)}
                            </Button>
                        );
                    })}
                </div>
            ) : picker === "months" ? (
                <div className="grid grid-cols-3 gap-1.5 rounded-lg border p-2 sm:grid-cols-4">
                    {JALALI_MONTHS.map((name, index) => {
                        const monthNumber = index + 1;
                        const isSelected = monthNumber === month.month;
                        const isCurrent =
                            todayMonth.year === month.year &&
                            todayMonth.month === monthNumber;

                        return (
                            <Button
                                key={name}
                                variant={isSelected ? "default" : "ghost"}
                                size="sm"
                                className={cn(
                                    "h-9",
                                    !isSelected &&
                                        isCurrent &&
                                        "border border-primary/40",
                                )}
                                onClick={() =>
                                    chooseMonth({
                                        ...month,
                                        month: monthNumber,
                                    })
                                }
                            >
                                {name}
                            </Button>
                        );
                    })}
                </div>
            ) : span === "month" ? (
                <div className="overflow-hidden rounded-lg border">
                    <div className="grid grid-cols-7 border-b bg-muted/40">
                        {JALALI_WEEKDAYS.map((weekday) => (
                            <div
                                key={weekday}
                                className="px-2 py-2 text-center text-xs font-medium text-muted-foreground"
                            >
                                {weekday}
                            </div>
                        ))}
                    </div>

                    <div className="grid grid-cols-7">
                        {cells.map(({ day, key }) => (
                            <div
                                key={key}
                                className={cn(
                                    "min-h-24 border-e border-b p-1.5",
                                    "[&:nth-child(7n)]:border-e-0",
                                    "[&:nth-last-child(-n+7)]:border-b-0",
                                    !day && "bg-muted/20",
                                )}
                            >
                                {day && (
                                    <>
                                        <div
                                            className={cn(
                                                "mb-1 text-xs",
                                                day.gregorian === today
                                                    ? "font-bold text-primary"
                                                    : "text-muted-foreground",
                                            )}
                                        >
                                            {day.day.toLocaleString("fa-IR")}
                                        </div>
                                        <div className="flex flex-col gap-1">
                                            {(
                                                meetingsByDate.get(
                                                    day.gregorian,
                                                ) ?? []
                                            ).map((meeting) => (
                                                <Link
                                                    key={meeting.id}
                                                    to="/board-meetings/$meetingId"
                                                    params={{
                                                        meetingId: String(
                                                            meeting.id,
                                                        ),
                                                    }}
                                                    title={
                                                        meeting.registry_label
                                                    }
                                                    className="truncate rounded-md bg-primary/10 px-1.5 py-1 text-xs text-primary transition-colors hover:bg-primary/20"
                                                >
                                                    {meeting.registry_label}
                                                </Link>
                                            ))}
                                        </div>
                                    </>
                                )}
                            </div>
                        ))}
                    </div>
                </div>
            ) : (
                <div className="max-h-[70vh] overflow-y-auto rounded-lg border p-3">
                    <div className="grid grid-cols-1 gap-x-6 gap-y-5 lg:grid-cols-2 2xl:grid-cols-3">
                        {yearMonths.map(
                            ({ monthNumber, name, cells: monthCells }) => (
                                <YearMonth
                                    key={monthNumber}
                                    name={name}
                                    year={month.year}
                                    monthNumber={monthNumber}
                                    cells={monthCells}
                                    meetingsByDate={meetingsByDate}
                                    today={today}
                                    onOpenMonth={chooseMonth}
                                />
                            ),
                        )}
                    </div>
                </div>
            )}

            {picker === null && meetings.length === 0 && (
                <p className="text-center text-xs text-muted-foreground">
                    {span === "month"
                        ? "در این ماه جلسه‌ای ثبت نشده است."
                        : "در این سال جلسه‌ای ثبت نشده است."}
                </p>
            )}
        </div>
    );
}

function NavButton({
    label,
    onClick,
    children,
}: {
    label: string;
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <Button
            variant="outline"
            size="icon-sm"
            title={label}
            aria-label={label}
            onClick={onClick}
        >
            {children}
        </Button>
    );
}

type YearMonthProps = {
    name: string;
    year: number;
    monthNumber: number;
    cells: { day: JalaliMonthDay | null; key: string }[];
    meetingsByDate: Map<string, BoardMeetingDto[]>;
    today: string;
    onOpenMonth: (month: JalaliMonth) => void;
};

/**
 * One month of the year view: the TCK layout (centred month banner, single
 * -letter weekdays, meeting days highlighted) in the hub's own tokens. A day
 * with exactly one meeting links straight to it; a day with several opens the
 * month so the chips can be picked apart.
 */
function YearMonth({
    name,
    year,
    monthNumber,
    cells,
    meetingsByDate,
    today,
    onOpenMonth,
}: YearMonthProps) {
    return (
        <div>
            <button
                type="button"
                onClick={() => onOpenMonth({ year, month: monthNumber })}
                className="mb-1 w-full rounded-md bg-accent py-1 text-center text-sm font-bold text-accent-foreground transition-colors hover:bg-accent/70"
            >
                {name}
            </button>

            <div className="grid grid-cols-7">
                {JALALI_WEEKDAYS_SHORT.map((weekday) => (
                    <div
                        key={weekday}
                        className="pb-1 text-center text-[0.7rem] font-normal text-muted-foreground"
                    >
                        {weekday}
                    </div>
                ))}

                {cells.map(({ day, key }) => {
                    if (!day) {
                        return <div key={key} className="aspect-square" />;
                    }

                    const dayMeetings = meetingsByDate.get(day.gregorian) ?? [];
                    const className = cn(
                        "flex aspect-square items-center justify-center rounded text-xs m-2",
                        day.gregorian === today && "ring-1 ring-primary",
                        dayMeetings.length > 0
                            ? "bg-primary/10 font-medium text-primary hover:bg-primary/20"
                            : "text-muted-foreground/50",
                    );
                    const label = day.day.toLocaleString("fa-IR");

                    if (dayMeetings.length === 1) {
                        const [meeting] = dayMeetings;

                        return (
                            <Link
                                key={key}
                                to="/board-meetings/$meetingId"
                                params={{ meetingId: String(meeting.id) }}
                                title={meeting.registry_label}
                                className={className}
                            >
                                {label}
                            </Link>
                        );
                    }

                    if (dayMeetings.length > 1) {
                        return (
                            <button
                                key={key}
                                type="button"
                                title={dayMeetings
                                    .map((meeting) => meeting.registry_label)
                                    .join("، ")}
                                onClick={() =>
                                    onOpenMonth({ year, month: monthNumber })
                                }
                                className={className}
                            >
                                {label}
                            </button>
                        );
                    }

                    return (
                        <div key={key} className={className}>
                            {label}
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
