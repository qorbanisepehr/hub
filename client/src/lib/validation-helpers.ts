import type { z } from "zod";

export function zodFieldValidator<T>(schema: z.ZodType<T>) {
    return ({ value }: { value: unknown }) => {
        const result = schema.safeParse(value === null ? "" : value);
        if (result.success) return undefined;
        return { message: result.error.issues[0]?.message ?? "خطای اعتبارسنجی" };
    };
}

/** Returns onBlur validator so errors only show after the user leaves the field. */
export function zodFieldValidators<T>(schema: z.ZodType<T>) {
    return { onBlur: zodFieldValidator(schema) };
}
