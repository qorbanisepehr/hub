import { Link } from "@tanstack/react-router";
import {
    IconCalendarMonth,
    IconChevronLeft,
    IconChevronRight,
} from "@tabler/icons-react";
import { useMemo, useState } from "react";

import { Button } from "@/components/ui/button";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import {
    JALALI_MONTHS,
    JALALI_WEEKDAYS,
    buildJalaliMonthWeeks,
    shiftJalaliMonth,
    todayIso,
    todayJalaliMonth,
    type JalaliMonth,
} from "@/lib/jalali";
import { cn } from "@/lib/utils";

import type { BoardMeetingDto } from "../types";

type MeetingCalendarProps = {
    month: JalaliMonth;
    onMonthChange: (month: JalaliMonth) => void;
    /** The meetings in view (already scoped to this month by the page). */
    meetings: BoardMeetingDto[];
};

/** Which grid the calendar shows: day cells, the months of a year, or years. */
type CalendarGrid = "days" | "months" | "years";

/** Years per year-picker page — a dozen fills a 3/4-column grid neatly. */
const YEARS_PER_PAGE = 12;
/** How far back the year dropdown reaches from today, so old registries stay reachable. */
const YEAR_LOOKBACK = 20;
/** How far forward the year dropdown reaches from today. */
const YEAR_LOOKAHEAD = 3;

/**
 * The «تقویم» view: a Jalali month grid with one chip per meeting, keyed by
 * the Gregorian `held_on` the API returns. Clicking a chip opens the meeting.
 *
 * Navigation offers both idioms the hub already uses for dates: the month and
 * year of the header are `Select` dropdowns (the same control the date picker
 * leans on), and the title drills into a months-of-the-year grid and then a
 * years-of-the-decade grid for browsing. The page owns which month is fetched;
 * this component owns only which grid is on screen.
 *
 * The day grid is built with `@internationalized/date`'s Persian calendar
 * (`lib/jalali`), so length-of-month and first-weekday come from the same
 * engine as the rest of the hub rather than from hand-written Jalali math.
 */
export function MeetingCalendar({
    month,
    onMonthChange,
    meetings,
}: MeetingCalendarProps) {
    const [grid, setGrid] = useState<CalendarGrid>("days");

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
    const decadeLabel = `${decadeStart.toLocaleString("fa-IR")} – ${(
        decadeStart +
        YEARS_PER_PAGE -
        1
    ).toLocaleString("fa-IR")}`;

    // Details → months → years, and the reverse from years. The button reads
    // as the next level down, so the header never shows a dead end.
    const toggle =
        grid === "days"
            ? { label: "نمای ماه و سال", next: "months" as const }
            : grid === "months"
              ? { label: "نمای سال‌ها", next: "years" as const }
              : { label: "نمای ماه‌ها", next: "months" as const };

    const monthSelect = (
        <Select
            value={String(month.month)}
            onValueChange={(value: string | null) => {
                if (value) {
                    onMonthChange({ ...month, month: Number(value) });
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
                }
            }}
            itemToStringLabel={(value) =>
                value
                    ? Number(value as string).toLocaleString("fa-IR")
                    : ""
            }
        >
            <SelectTrigger size="sm" className="w-24" aria-label="سال">
                <SelectValue
                    placeholder={month.year.toLocaleString("fa-IR")}
                />
            </SelectTrigger>
            <SelectContent>
                {yearOptions.map((year) => (
                    <SelectItem key={year} value={String(year)}>
                        {year.toLocaleString("fa-IR")}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );

    return (
        <div className="flex flex-col gap-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="flex items-center gap-1.5">
                    {grid === "days" && (
                        <>
                            <Button
                                variant="outline"
                                size="icon-sm"
                                aria-label="ماه قبل"
                                onClick={() =>
                                    onMonthChange(shiftJalaliMonth(month, -1))
                                }
                            >
                                <IconChevronRight className="size-4" />
                            </Button>
                            {monthSelect}
                            {yearSelect}
                            <Button
                                variant="outline"
                                size="icon-sm"
                                aria-label="ماه بعد"
                                onClick={() =>
                                    onMonthChange(shiftJalaliMonth(month, 1))
                                }
                            >
                                <IconChevronLeft className="size-4" />
                            </Button>
                        </>
                    )}

                    {grid === "months" && (
                        <>
                            <Button
                                variant="outline"
                                size="icon-sm"
                                aria-label="سال قبل"
                                onClick={() =>
                                    onMonthChange({
                                        ...month,
                                        year: month.year - 1,
                                    })
                                }
                            >
                                <IconChevronRight className="size-4" />
                            </Button>
                            {yearSelect}
                            <Button
                                variant="outline"
                                size="icon-sm"
                                aria-label="سال بعد"
                                onClick={() =>
                                    onMonthChange({
                                        ...month,
                                        year: month.year + 1,
                                    })
                                }
                            >
                                <IconChevronLeft className="size-4" />
                            </Button>
                        </>
                    )}

                    {grid === "years" && (
                        <>
                            <Button
                                variant="outline"
                                size="icon-sm"
                                aria-label="دههٔ قبل"
                                onClick={() =>
                                    onMonthChange({
                                        ...month,
                                        year: month.year - YEARS_PER_PAGE,
                                    })
                                }
                            >
                                <IconChevronRight className="size-4" />
                            </Button>
                            <span className="min-w-24 text-center text-sm font-medium">
                                {decadeLabel}
                            </span>
                            <Button
                                variant="outline"
                                size="icon-sm"
                                aria-label="دههٔ بعد"
                                onClick={() =>
                                    onMonthChange({
                                        ...month,
                                        year: month.year + YEARS_PER_PAGE,
                                    })
                                }
                            >
                                <IconChevronLeft className="size-4" />
                            </Button>
                        </>
                    )}
                </div>

                <div className="flex items-center gap-1">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => {
                            onMonthChange(todayJalaliMonth());
                            setGrid("days");
                        }}
                    >
                        ماه جاری
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setGrid(toggle.next)}
                    >
                        <IconCalendarMonth className="size-4" />
                        <span className="hidden sm:inline">
                            {toggle.label}
                        </span>
                    </Button>
                </div>
            </div>

            {grid === "days" && (
                <>
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
                                                        {
                                                            meeting.registry_label
                                                        }
                                                    </Link>
                                                ))}
                                            </div>
                                        </>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>

                    {meetings.length === 0 && (
                        <p className="text-center text-xs text-muted-foreground">
                            در این ماه جلسه‌ای ثبت نشده است.
                        </p>
                    )}
                </>
            )}

            {grid === "months" && (
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
                                onClick={() => {
                                    onMonthChange({
                                        ...month,
                                        month: monthNumber,
                                    });
                                    setGrid("days");
                                }}
                            >
                                {name}
                            </Button>
                        );
                    })}
                </div>
            )}

            {grid === "years" && (
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
                                onClick={() => {
                                    onMonthChange({ ...month, year });
                                    setGrid("months");
                                }}
                            >
                                {year.toLocaleString("fa-IR")}
                            </Button>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
