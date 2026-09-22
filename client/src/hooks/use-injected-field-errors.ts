import { useCallback, useRef } from "react";

import type { FormMetaWriter } from "@/types/form-types";
import {
    fieldBelongsToSection,
    type FieldErrors,
    type ValidationSection,
} from "@/lib/validation-helpers";

/**
 * Inject per-field validation messages from the submit-time schema check into
 * the TanStack Form field meta, so inline errors render on the current wizard
 * section right after clicking "بررسی اعتبار" — without waiting for submit.
 *
 * Errors are written to the `onSubmit` error map (as { message } objects so
 * FieldError deduplication works). TanStack clears that key automatically once
 * the field becomes valid again on blur/change.
 */
export function useInjectedFieldErrors(form: FormMetaWriter) {
    const injectedFields = useRef<string[]>([]);

    const clear = useCallback(() => {
        for (const name of injectedFields.current) {
            form.setFieldMeta(name, (prev) => ({
                ...prev,
                errorMap: { ...prev.errorMap, onSubmit: undefined },
                errorSourceMap: { ...prev.errorSourceMap, onSubmit: undefined },
            }));
        }
        injectedFields.current = [];
    }, [form]);

    const inject = useCallback(
        (fieldErrors: FieldErrors, section: ValidationSection) => {
            clear();
            for (const [name, messages] of Object.entries(fieldErrors)) {
                if (!fieldBelongsToSection(name, section)) continue;
                form.setFieldMeta(name, (prev) => ({
                    ...prev,
                    isTouched: true,
                    errorMap: {
                        ...prev.errorMap,
                        onSubmit: messages.map((message) => ({ message })),
                    },
                    // Marked "field" (not "form") so the form-level validator
                    // never claims ownership of injected errors, and TanStack
                    // re-validation still clears them once the field is valid.
                    errorSourceMap: { ...prev.errorSourceMap, onSubmit: "field" },
                }));
                injectedFields.current.push(name);
            }
        },
        [clear, form],
    );

    return { inject, clear };
}
