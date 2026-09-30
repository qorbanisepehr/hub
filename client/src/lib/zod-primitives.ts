import { z } from "zod";

/**
 * Shared zod primitives that normalize `null`/`undefined` before the real
 * checks run. Backend JSONB columns store `null` for untouched optional and
 * conditional (e.g. "is a student") fields, and plain `z.string().optional()`
 * rejects `null` with an English "Invalid input: expected string, received
 * null" error. These helpers eliminate that whole class of leaks centrally so
 * every required/optional text or number field behaves the same.
 */

function toEmptyString(value: unknown): unknown {
    return value == null ? "" : value;
}

/**
 * Optional text. `null`/`undefined` become `""` and validate as empty, so a
 * missing key or an untouched JSONB null never produces a type error.
 */
export function text(max = 255, maxMessage = `حداکثر ${max} کاراکتر.`) {
    return z.preprocess(toEmptyString, z.string().max(max, maxMessage));
}

/**
 * Required text. `null`/`undefined`/`""` all fail with the given (Persian)
 * message instead of a generic zod type error.
 */
export function requiredText(message: string, max = 255) {
    return z.preprocess(
        toEmptyString,
        z.string().min(1, message).max(max, `حداکثر ${max} کاراکتر.`),
    );
}

function toNumber(value: unknown): number | null {
    if (value == null || value === "") return null;
    const n = typeof value === "number" ? value : Number(value);
    return Number.isNaN(n) ? null : n;
}

/**
 * Number field that tolerates numbers, numeric strings (form selects emit
 * strings), `null` and `undefined`. Keeps `.nullable().optional()` semantics
 * so untouched/cleared fields pass while out-of-range values fail.
 */
export function numberField(
    min: number,
    message: string,
    max?: number,
): z.ZodType<number | null | undefined> {
    let schema = z.number().min(min, message);
    if (max !== undefined) {
        schema = schema.max(max, `حداکثر ${max}`);
    }
    return z.preprocess(toNumber, schema.nullable().optional());
}

/**
 * A list search param that a human can also type.
 *
 * The list routes declare array params as `z.array(z.string())`, which only
 * round-trips through the router's JSON-ish encoding — `?tags=["a","b"]`. That
 * is fine for the table's own writes and useless for a link somebody sends to
 * a colleague, and it is exactly the shape a clickable tag has to produce: the
 * detail page knows one slug, not a serialized array.
 *
 * So this accepts either form and always hands the rest of the app a real
 * array. Comma-separated is the reading a person expects, and a tag slug never
 * contains a comma, so the two forms cannot be confused.
 */
export function csvArray() {
    return z.preprocess((value) => {
        if (value == null || value === "") return undefined;
        if (Array.isArray(value)) return value;

        if (typeof value === "string") {
            return value
                .split(",")
                .map((item) => item.trim())
                .filter((item) => item !== "");
        }

        return value;
    }, z.array(z.string()).optional());
}

/**
 * Shared list-route search schema: `page`/`per_page`/`sort`/`order`/`filter`
 * plus any feature-specific query params. List route files call
 * `paginatedSearchSchema({ status: z.string().optional() })` instead of
 * re-declaring the same five fields.
 */
export function paginatedSearchSchema<T extends z.ZodRawShape = {}>(
    extra: T = {} as T,
) {
    return z.object({
        page: z.number().optional(),
        per_page: z.number().optional(),
        sort: z.string().optional(),
        order: z.enum(["asc", "desc"]).optional(),
        filter: z.string().optional(),
        ...extra,
    });
}
