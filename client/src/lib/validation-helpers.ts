import type { z } from "zod";

export function zodFieldValidator<T>(schema: z.ZodType<T>) {
    return ({ value }: { value: unknown }) => {
        const result = schema.safeParse(value == null ? "" : value);
        if (result.success) return undefined;
        return { message: result.error.issues[0]?.message ?? "خطای اعتبارسنجی" };
    };
}

/** Returns onChange and onBlur validators. Errors only show after the user leaves the field (onBlur). */
export function zodFieldValidators<T>(schema: z.ZodType<T>) {
    return { onChange: zodFieldValidator(schema), onBlur: zodFieldValidator(schema) };
}
