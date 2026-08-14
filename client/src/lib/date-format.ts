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
