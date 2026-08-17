import { toPersianDate } from "@/lib/date-format";

export function asRecord(value: unknown): Record<string, unknown> {
    return value && typeof value === "object"
        ? (value as Record<string, unknown>)
        : {};
}

export function stringValue(value: unknown): string | null {
    if (value === null || value === undefined || value === "") return null;
    return String(value);
}

export function dateValue(value: unknown): string | null {
    if (value === null || value === undefined || value === "") return null;
    return toPersianDate(value as string);
}

export function boolLabel(value: unknown): string | null {
    if (value === undefined || value === null) return null;
    return value ? "بله" : "خیر";
}
