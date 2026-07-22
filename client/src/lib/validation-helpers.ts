import type { z } from "zod";

export function zodFieldValidator<T>(schema: z.ZodType<T>) {
    return (value: unknown) => {
        const result = schema.safeParse(value);
        if (result.success) return undefined;
        return result.error.issues[0]?.message;
    };
}
