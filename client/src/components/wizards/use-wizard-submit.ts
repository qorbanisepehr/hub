import { useEffect, useState } from "react";
import {
    useMutation,
    useQueryClient,
    type UseMutationResult,
} from "@tanstack/react-query";
import { toast } from "sonner";

import { useInjectedFieldErrors } from "@/hooks/use-injected-field-errors";
import { getSubmitErrors } from "@/lib/error-utils";
import {
    countSectionFieldErrors,
    scrollToFirstInvalidField,
    type FieldErrors,
    type ValidationSection,
} from "@/lib/validation-helpers";

type SubmitValidationResult = {
    success: boolean;
    errors: string[];
    fieldErrors: FieldErrors;
};

type SubmitGuard = {
    /** Returns non-empty error messages to block the final submit. */
    errors: () => string[];
    /** Optional toast shown when this guard blocks the submit. */
    message?: string;
};

type UseWizardSubmitOptions = {
    /** TanStack form from `useSectionForm`; typed loosely to match the codebase's existing form plumbing. */
    form: any;
    isDirty: boolean;
    optionsReady: boolean;
    validateSubmit: (values: unknown) => SubmitValidationResult;
    /** Returns the key of the step/tab currently in view. */
    getCurrentSectionKey: () => string;
    validationSections: readonly ValidationSection[];
    /** Ordered submit guards, run after the schema check (empty = always pass). */
    guards?: SubmitGuard[];
    /** Returns document-requirement errors for the current step (none when omitted). */
    getDocumentErrors?: () => string[];
    /** Label of the review step, appended to point the user where detail lives. */
    reviewStepLabel?: string;
    submit: {
        submitFn: () => Promise<unknown>;
        detailQueryKey: () => readonly unknown[];
        successMessage: string;
        errorFallback: string;
    };
};

/**
 * Shared final-submit + validate orchestration for the multi-section shells
 * (questionnaire wizard, CV wizard, employee profile tabs). Each shell already
 * funnels its form/save/dirty state through `useSectionForm` and its own
 * `buildValidateSubmitData(submitOptions)`; this hook owns the remaining
 * per-click glue — the submit mutation (invalidate + toast + flatten errors),
 * the clear-on-dirty effect, the ordered submit guard chain, and the validate
 * click with its per-section error injection and toast.
 *
 * Feature differences are injected as options: the current section key, the
 * validate section list, an optional document-requirement check, optional
 * submit guards, and an optional review-step label for the hint text (the
 * tab-based employee shell passes none).
 */
export function useWizardSubmit({
    form,
    isDirty,
    optionsReady,
    validateSubmit,
    getCurrentSectionKey,
    validationSections,
    guards = [],
    getDocumentErrors,
    reviewStepLabel,
    submit,
}: UseWizardSubmitOptions) {
    const queryClient = useQueryClient();
    const [submitErrors, setSubmitErrors] = useState<string[]>([]);
    const { inject: injectFieldErrors, clear: clearInjectedErrors } =
        useInjectedFieldErrors(form);

    const submitMutation = useMutation({
        mutationFn: submit.submitFn,
        onSuccess: () => {
            queryClient.invalidateQueries({
                queryKey: submit.detailQueryKey(),
            });
            toast.success(submit.successMessage);
        },
        onError: (error: Error) => {
            setSubmitErrors(getSubmitErrors(error, submit.errorFallback));
        },
    }) as UseMutationResult<unknown, Error, void, unknown>;

    useEffect(() => {
        if (isDirty) {
            setSubmitErrors([]);
        }
    }, [isDirty]);

    const handleSubmit = () => {
        if (!optionsReady) return;

        const validation = validateSubmit(form.state.values);
        if (!validation.success) {
            setSubmitErrors(validation.errors);
            toast.error("لطفاً خطاهای زیر را اصلاح کنید.");
            return;
        }

        for (const guard of guards) {
            const errors = guard.errors();
            if (errors.length > 0) {
                setSubmitErrors(errors);
                if (guard.message) toast.error(guard.message);
                return;
            }
        }

        setSubmitErrors([]);
        submitMutation.mutate();
    };

    const handleValidateClick = () => {
        if (!optionsReady) return;

        const result = validateSubmit(form.state.values);
        const documentErrors = getDocumentErrors?.() ?? [];

        if (result.success && documentErrors.length === 0) {
            clearInjectedErrors();
            toast.success("همه فیلدهای الزامی تکمیل شده‌اند.");
            return;
        }

        const sectionKey = getCurrentSectionKey();
        const section = validationSections.find((s) => s.key === sectionKey);
        if (section) {
            injectFieldErrors(result.fieldErrors, section);
        }

        const currentFieldCount = section
            ? countSectionFieldErrors(result.fieldErrors, section)
            : 0;
        const currentDocCount =
            sectionKey === "documents" ? documentErrors.length : 0;
        const currentCount = currentFieldCount + currentDocCount;
        const otherCount =
            result.errors.length + documentErrors.length - currentCount;

        if (currentCount > 0) {
            scrollToFirstInvalidField();
        }

        const inThisSection =
            currentCount > 0
                ? `${currentCount} خطا در این بخش${
                      otherCount > 0
                          ? ` و ${otherCount} خطا در سایر بخش‌ها`
                          : ""
                  }`
                : `${otherCount} خطا در سایر بخش‌ها وجود دارد`;
        const hint = reviewStepLabel
            ? currentCount > 0
                ? ` (در «${reviewStepLabel}» قابل مشاهده است).`
                : ` که در «${reviewStepLabel}» قابل مشاهده است.`
            : currentCount > 0
              ? ""
              : ".";

        toast.error("فیلدهای الزامی ناقص هستند", {
            description: `${inThisSection}${hint}`,
            duration: 5000,
        });
    };

    return { submitErrors, submitMutation, handleSubmit, handleValidateClick };
}
