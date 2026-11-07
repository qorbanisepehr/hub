/**
 * Display formatting for values the user typed in a specific Iranian shape:
 * a bank card, an IBAN, a mobile. Each function normalises first and then
 * groups, so a value saved with dashes, spaces or the `IR` prefix renders the
 * same way as one saved as bare digits. Values of an unexpected shape are
 * returned untouched rather than partly reformatted.
 */

const digitsOnly = (value: string): string => value.replace(/\D/g, "");

/** Group a 16-digit card as 4-4-4-4; anything else passes through. */
export function formatCardNumber(
    value: string | null | undefined,
): string | null {
    if (!value) return null;

    const digits = digitsOnly(value);

    if (digits.length !== 16) return value;
    return digits.replace(/(\d{4})(?=\d)/g, "$1-");
}

/**
 * Group an Iranian IBAN as `IR` + 4-digit groups (IR12 3456 …); anything
 * else passes through.
 */
export function formatIban(value: string | null | undefined): string | null {
    if (!value) return null;

    const normalized = value.toUpperCase().replace(/[\s-]/g, "");
    const digits = normalized.replace(/^IR/, "");

    if (!/^IR\d{24}$/.test(normalized)) return value;
    return `IR${digits.replace(/(\d{4})(?=\d)/g, "$1 ")}`.trim();
}

/** A mobile number grouped as `09XX XXX XXXX`; anything else passes through. */
export function formatMobile(value: string | null | undefined): string | null {
    if (!value) return null;

    const digits = digitsOnly(value);

    if (!/^09\d{9}$/.test(digits)) return value;
    return `${digits.slice(0, 4)} ${digits.slice(4, 7)} ${digits.slice(7)}`;
}
