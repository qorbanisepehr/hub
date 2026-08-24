import { z } from "zod";

/**
 * Dynamic enum validation backed by fetched form options. Unlike `z.enum`,
 * which needs a static tuple of values, these helpers validate against the
 * option set returned by the API, so admin edits are reflected immediately.
 *
 * `null`/`undefined` are normalized to `""` first (backend JSONB stores `null`
 * for untouched fields) to match the shared `zod-primitives` behavior.
 */

export type OptionSource = { value: string; label: string };

function toEmptyString(value: unknown): unknown {
    return value == null ? "" : value;
}

function valueSet(options: OptionSource[]): Set<string> {
    return new Set(options.map((option) => option.value));
}

/** Required single-value option. Empty and unknown labels fail with `message`. */
export function optionEnum(
    options: OptionSource[],
    message: string,
): z.ZodType<string> {
    const allowed = valueSet(options);
    return z.preprocess(
        toEmptyString,
        z.string().superRefine((value, ctx) => {
            if (value === "" || !allowed.has(value)) {
                ctx.addIssue({ code: z.ZodIssueCode.custom, message });
            }
        }),
    );
}

/** Optional single-value option. Empty is allowed; unknown non-empty fails. */
export function optionEnumOptional(
    options: OptionSource[],
    message: string,
): z.ZodType<string | undefined> {
    const allowed = valueSet(options);
    return z
        .preprocess(
            toEmptyString,
            z.string().superRefine((value, ctx) => {
                if (value !== "" && !allowed.has(value)) {
                    ctx.addIssue({ code: z.ZodIssueCode.custom, message });
                }
            }),
        )
        .optional();
}

/** Optional multi-value option. Unknown members fail; empty array is allowed. */
export function optionArrayEnum(
    options: OptionSource[],
): z.ZodType<string[] | undefined> {
    const allowed = valueSet(options);
    return z
        .preprocess(
            (value) => (value == null ? [] : value),
            z.array(
                z.string().superRefine((value, ctx) => {
                    if (value !== "" && !allowed.has(value)) {
                        ctx.addIssue({
                            code: z.ZodIssueCode.custom,
                            message: "گزینه انتخاب‌شده معتبر نیست.",
                        });
                    }
                }),
            ),
        )
        .optional();
}

export type PlaceOption = {
    value: string;
    label: string;
    parent_value?: string | null;
};

/**
 * Validation for a combined place string «{استان}-{شهر}» (e.g. «تهران-تهران»).
 * The province segment must be an active province label and the city segment an
 * active city label whose parent is that province, so stored values always
 * resolve without extra lookups.
 */
export function placeEnum(
    provinces: PlaceOption[],
    cities: PlaceOption[],
    message: string,
): z.ZodType<string> {
    const provinceValueSet = new Set(
        provinces?.map((option) => option.value),
    );

    return z.preprocess(
        toEmptyString,
        z.string().superRefine((value, ctx) => {
            if (value === "") {
                ctx.addIssue({ code: z.ZodIssueCode.custom, message });
                return;
            }
            const [provinceValue, cityValue] = value.split("-", 2);
            if (
                !provinceValue ||
                !cityValue ||
                !provinceValueSet.has(provinceValue) ||
                !cities.some(
                    (option) =>
                        option.value === cityValue &&
                        option.parent_value === provinceValue,
                )
            ) {
                ctx.addIssue({ code: z.ZodIssueCode.custom, message });
            }
        }),
    );
}
