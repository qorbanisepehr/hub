import { z } from "zod";

import {
    zodFieldErrors,
    zodIssueMessage,
    type FieldErrors,
} from "@/lib/validation-helpers";

export type SubmitValidationResult = {
    success: boolean;
    errors: string[];
    fieldErrors: FieldErrors;
};

/**
 * Build a submit validator from a compiled zod schema. Pass `undefined` while
 * the options are still loading: the validator then reports no errors (so the
 * summary view doesn't flash spurious errors) and callers gate submission on
 * `optionsReady` instead. Feature shells pair it with their own
 * `buildSubmitSchema` via a thin `buildSubmitValidator(options)` wrapper.
 */
export function buildValidateSubmitData<TSchema extends z.ZodType>(
    schema: TSchema | undefined,
): (data: unknown) => SubmitValidationResult {
    return (data) => {
        if (!schema) {
            return { success: false, errors: [], fieldErrors: {} };
        }
        const result = schema.safeParse(data);
        if (result.success) {
            return { success: true, errors: [], fieldErrors: {} };
        }
        return {
            success: false,
            errors: result.error.issues.map((issue) => zodIssueMessage(issue)),
            fieldErrors: zodFieldErrors(result.error),
        };
    };
}