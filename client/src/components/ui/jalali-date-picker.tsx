"use client";

import * as React from "react";
import { format, parse } from "date-fns";
import { getDateLib } from "@daypicker/persian";
import { useDayPicker, type MonthCaptionProps } from "@daypicker/react";
import {
    IconCalendarMonth,
    IconChevronLeft,
    IconChevronRight,
} from "@tabler/icons-react";

import { cn } from "@/lib/utils";
import {
    JALALI_MONTHS,
    formatJalaliYear,
    jalaliFromIso,
    jalaliInputToIso,
    toPersianDigits,
} from "@/lib/jalali";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { CalendarGrid } from "@/components/ui/calendar";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";

/**
 * A Jalali date field with both ways in: type the date, or pick it from the
 * month grid. The typed form is the one people reach for when they already
 * know the date — ten keystrokes beat scrolling a calendar.
 *
 * The wire format stays Gregorian `yyyy-MM-dd`; only what the user sees is
 * Jalali.
 *
 * The field is controlled: `value` is the truth and `onChange` moves it. Text
 * exists only as a draft — the half-typed day someone is in the middle of —
 * and `null` means "nothing typed", which displays `value`. That keeps a
 * parent reset from being overwritten by a stale copy of the old date.
 */

type Props = {
    value?: string | null;
    onChange?: (value: string) => void;
    placeholder?: string;
    disabled?: boolean;
    className?: string;
    id?: string;
    "aria-invalid"?: boolean;
    "aria-describedby"?: string;
};

/** `yyyy-MM-dd` → its Jalali label, or "" when the value is unusable. */
export function jalaliDisplayValue(iso: string | null | undefined): string {
    const jalali = jalaliFromIso(iso);

    if (!jalali) {
        return "";
    }

    return toPersianDigits(jalali.jalali.replace(/-/g, "/"));
}

/**
 * Typed text is committed only when it is a complete, real Jalali day, so a
 * half-typed «۱۴۰۲/۰۳» never writes a value, and a day that does not exist
 * (1403/12/31 in a non-leap year) is rejected instead of being nudged.
 */
export function JalaliDatePicker({
    value,
    onChange,
    placeholder = "انتخاب تاریخ",
    disabled,
    className,
    id,
    "aria-invalid": ariaInvalid,
    "aria-describedby": describedBy,
}: Props) {
    const [open, setOpen] = React.useState(false);
    const [draft, setDraft] = React.useState<string | null>(null);
    const inputRef = React.useRef<HTMLInputElement>(null);
    const dialogId = React.useId();
    // Set while focus goes back to the field after a pick, so that return is
    // not mistaken for a fresh click.
    const returnFocusRef = React.useRef(false);

    const committed = value ?? "";
    const text = draft ?? jalaliDisplayValue(committed);
    const selected = value ? parse(value, "yyyy-MM-dd", new Date()) : undefined;
    const selectedIsValid =
        selected !== undefined && !Number.isNaN(selected.getTime());

    /**
     * A picked day arrives as Gregorian ISO and is finished; a typed one as
     * Jalali text. Only the typed path has to parse — and may fail.
     */
    const commitIso = (iso: string) => {
        setDraft(null);
        onChange?.(iso);
    };

    const commitText = (raw: string): boolean => {
        const iso = jalaliInputToIso(raw);

        if (iso === null) {
            return false;
        }

        commitIso(iso);
        return true;
    };

    const handleChange = (next: string) => {
        setDraft(next);
        commitText(next);
    };

    // A draft that cannot be read is dropped rather than left in the field
    // pretending to be the record's date; the committed value shows again.
    const settle = () => {
        if (draft !== null && !jalaliInputToIso(draft)) {
            setDraft(null);
        }
    };

    const openCalendar = () => {
        if (!disabled) {
            setOpen(true);
        }
    };

    return (
        <Popover
            open={open && !disabled}
            onOpenChange={(next) => {
                if (!next) {
                    settle();
                }
                setOpen(next);
            }}
        >
            <div
                className={cn("relative flex w-full items-center", className)}
                dir="ltr"
            >
                <PopoverTrigger
                    disabled={disabled}
                    // The trigger is the text input itself; it announces a
                    // dialog popup rather than button semantics.
                    nativeButton={false}
                    render={
                        <input
                            ref={inputRef}
                            id={id}
                            type="text"
                            inputMode="text"
                            autoComplete="off"
                            dir="ltr"
                            placeholder={placeholder}
                            disabled={disabled}
                            role="combobox"
                            aria-haspopup="dialog"
                            aria-expanded={open}
                            aria-controls={open ? dialogId : undefined}
                            aria-autocomplete="none"
                            aria-invalid={ariaInvalid}
                            aria-describedby={describedBy}
                            // Bound to the display text: a calendar pick or a
                            // parent reset changes `value`, and the box has to
                            // follow it — an uncontrolled input would only
                            // ever show what the keyboard typed.
                            value={text}
                            onClick={() => {
                                if (returnFocusRef.current) {
                                    returnFocusRef.current = false;
                                    return;
                                }
                                if (open) {
                                    setOpen(false);
                                } else {
                                    openCalendar();
                                }
                            }}
                            onChange={(event) =>
                                handleChange(event.target.value)
                            }
                            onBlur={settle}
                            onKeyDown={(event) => {
                                if (event.key === "Escape") {
                                    setOpen(false);
                                    return;
                                }
                                if (event.key === "Enter") {
                                    event.preventDefault();
                                    if (commitText(text)) {
                                        setOpen(false);
                                    }
                                    return;
                                }
                                if (event.key === "ArrowDown" && !open) {
                                    event.preventDefault();
                                    openCalendar();
                                }
                            }}
                            className={cn(
                                // Same surface as the stock Input, plus the
                                // room and numeral alignment of a date field.
                                "h-8 w-full min-w-0 rounded-lg border border-input bg-transparent px-2.5 py-1 text-start text-base tabular-nums transition-colors outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 disabled:pointer-events-none disabled:cursor-not-allowed disabled:bg-input/50 disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-3 aria-invalid:ring-destructive/20 md:text-sm dark:bg-input/30 dark:disabled:bg-input/80 dark:aria-invalid:border-destructive/50 dark:aria-invalid:ring-destructive/40",
                                "pe-8",
                            )}
                        />
                    }
                />
                <button
                    type="button"
                    aria-label="باز کردن تقویم"
                    aria-expanded={open}
                    aria-controls={open ? dialogId : undefined}
                    disabled={disabled}
                    // Taking the press without letting the field blur keeps a
                    // half-typed day from being settled by opening the grid.
                    onMouseDown={(event) => event.preventDefault()}
                    onClick={() => {
                        setOpen((previous) => !previous);
                        inputRef.current?.focus();
                    }}
                    className="absolute inset-e-0 flex h-full w-8 items-center justify-center rounded-e-lg text-muted-foreground hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-50"
                >
                    <IconCalendarMonth className="size-4 shrink-0" />
                </button>
            </div>
            <PopoverContent
                id={dialogId}
                role="dialog"
                aria-label={placeholder}
                className="w-auto p-0"
                align="start"
                // Escape and outside presses hand focus back to the field, so
                // a keyboard user is never dropped at the top of the page.
                // (A pick focuses the field itself.)
                finalFocus={() => {
                    if (returnFocusRef.current) {
                        returnFocusRef.current = false;
                        return false;
                    }
                    return inputRef.current;
                }}
            >
                <CalendarGrid
                    // Read by JalaliMonthCaption to move the whole grid.
                    captionLayout="dropdown"
                    components={{ MonthCaption: JalaliMonthCaption }}
                    mode="single"
                    selected={selectedIsValid ? selected : undefined}
                    onSelect={(newDate: Date | undefined) => {
                        if (!newDate) {
                            return;
                        }

                        commitIso(format(newDate, "yyyy-MM-dd"));
                        setOpen(false);
                        returnFocusRef.current = true;
                        inputRef.current?.focus();
                    }}
                    autoFocus
                    defaultMonth={selectedIsValid ? selected : undefined}
                />
                <span aria-live="polite" className="sr-only">
                    {selectedIsValid
                        ? `تاریخ انتخاب‌شده: ${jalaliDisplayValue(value)}`
                        : ""}
                </span>
            </PopoverContent>
        </Popover>
    );
}

/** Side arrows for the month grid, drawn over the caption's own gutter. */
const captionArrow =
    "flex size-(--cell-size) items-center justify-center rounded-(--cell-radius) text-muted-foreground hover:bg-accent hover:text-foreground aria-disabled:pointer-events-none aria-disabled:opacity-50";

/**
 * Year and month chosen from two visible selects, the arrows kept for
 * neighbours. The stock caption's selects are deliberately see-through over a
 * static label, so changing «شهریور» to «مهر» looks like nothing happened.
 *
 * The month list is the app's own names (DayPicker's locale formatter hands
 * back a label, not a number, and the stock list is indexed in the Gregorian
 * calendar); the year range stays DayPicker's, so `startMonth`/`endMonth`
 * still bound it. Choosing is not navigation: the grid stays where it is
 * until a month actually changes.
 */
function JalaliMonthCaption({
    calendarMonth,
    displayIndex,
    ...props
}: MonthCaptionProps) {
    const { goToMonth, nextMonth, previousMonth, dayPickerProps } =
        useDayPicker();
    const dateLib = getDateLib(dayPickerProps.dateLib?.options);
    const view = calendarMonth.date;
    const month = dateLib.getMonth(view);
    const year = dateLib.getYear(view);
    const firstYear = dateLib.getYear(
        dayPickerProps.startMonth ?? dateLib.addYears(view, -100),
    );
    const lastYear = dateLib.getYear(
        dateLib.startOfMonth(
            dayPickerProps.endMonth ?? dateLib.addYears(view, 100),
        ),
    );
    const years = Array.from(
        { length: Math.max(lastYear - firstYear + 1, 1) },
        (_, offset) => firstYear + offset,
    );

    return (
        <div {...props} className={cn("relative", props.className)}>
            {displayIndex === 0 && (
                <div className="absolute inset-x-0 top-0 z-10 flex items-center justify-between">
                    <button
                        type="button"
                        aria-label="ماه قبل"
                        aria-disabled={previousMonth === undefined || undefined}
                        className={captionArrow}
                        onClick={() => {
                            if (previousMonth) {
                                goToMonth(previousMonth);
                            }
                        }}
                    >
                        <IconChevronRight className="size-4" />
                    </button>
                    <button
                        type="button"
                        aria-label="ماه بعد"
                        aria-disabled={nextMonth === undefined || undefined}
                        className={captionArrow}
                        onClick={() => {
                            if (nextMonth) {
                                goToMonth(dateLib.startOfMonth(nextMonth));
                            }
                        }}
                    >
                        <IconChevronLeft className="size-4" />
                    </button>
                </div>
            )}
            <div className="relative z-20 flex items-center justify-center gap-1.5 px-8">
                <Select
                    value={month}
                    onValueChange={(next) => {
                        if (next === null) {
                            return;
                        }

                        goToMonth(
                            dateLib.setMonth(dateLib.startOfMonth(view), next),
                        );
                    }}
                >
                    <SelectTrigger size="sm" aria-label="ماه">
                        <SelectValue>
                            <span className="truncate">
                                {JALALI_MONTHS[month]}
                            </span>
                        </SelectValue>
                    </SelectTrigger>
                    <SelectContent>
                        {JALALI_MONTHS.map((name, index) => (
                            <SelectItem key={name} value={index}>
                                {name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                <Select
                    value={year}
                    onValueChange={(next) => {
                        if (next !== null) {
                            goToMonth(
                                dateLib.setYear(dateLib.startOfMonth(view), next),
                            );
                        }
                    }}
                >
                    <SelectTrigger size="sm" aria-label="سال">
                        <SelectValue>
                            <span className="truncate tabular-nums">
                                {formatJalaliYear(year)}
                            </span>
                        </SelectValue>
                    </SelectTrigger>
                    <SelectContent>
                        {years.map((year) => (
                            <SelectItem key={year} value={year}>
                                {formatJalaliYear(year)}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
        </div>
    );
}
