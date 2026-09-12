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

/** Group a 16-digit card number as 4-4-4-4 for display; passes through others. */
export function formatCardNumber(
    value: string | null | undefined,
): string | null {
    if (!value) return null;
    const digits = value.replace(/\D/g, "");
    if (digits.length !== 16) return value;
    return digits.replace(/(\d{4})(?=\d)/g, "$1-");
}
