"use client";

import * as React from "react";
import { IconCalendarMonth } from "@tabler/icons-react";
import { format, parse } from "date-fns";

import { cn } from "@/lib/utils";
import { toPersianDate } from "@/lib/date-format";
import { Button } from "@/components/ui/button";
import { Calendar } from "@/components/ui/calendar";
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/components/ui/popover";

type Props = {
    value?: string;
    onChange?: (value: string) => void;
    placeholder?: string;
    disabled?: boolean;
    className?: string;
};

export function DatePicker({
    value,
    onChange,
    placeholder = "انتخاب تاریخ",
    disabled,
    className,
}: Props) {
    const [open, setOpen] = React.useState(false);

    const date = value ? parse(value, "yyyy-MM-dd", new Date()) : undefined;

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger
                disabled={disabled}
                render={
                    <Button
                        variant="outline"
                        className={cn(
                            "w-full justify-start text-right font-normal",
                            !date && "text-muted-foreground",
                            className,
                        )}
                    />
                }
            >
                <IconCalendarMonth className="size-4 shrink-0" />
                {date ? toPersianDate(date) : <span>{placeholder}</span>}
            </PopoverTrigger>
            <PopoverContent className="w-auto p-0" align="start">
                <Calendar
                    mode="single"
                    selected={date}
                    onSelect={(newDate) => {
                        if (newDate) {
                            onChange?.(format(newDate, "yyyy-MM-dd"));
                            setOpen(false);
                        }
                    }}
                    autoFocus
                    captionLayout="dropdown"
                    defaultMonth={date}
                />
            </PopoverContent>
        </Popover>
    );
}
