import { useRowDocsFeedback } from "@/features/documents/hooks/use-row-docs-feedback";
import type { MissingRowDoc } from "@/features/documents/docs-feedback";
import type { DynamicDocumentRequirementGroup } from "@/features/documents/types";
import { useFormOptions } from "@/features/form-options/hooks/use-form-options";

import { dependentRowLabel } from "../dependents-docs";
import { DEPENDENT_DOC_CATEGORIES } from "../constants";

type DependentFeedbackRow = { relationship_type?: unknown };

type UseDependentDocsFeedbackReturn = {
    /** True while the employee's documents are still loading. */
    isLoading: boolean;
    /** The server's per-row requirement group for section "dependents". */
    rowGroup: DynamicDocumentRequirementGroup | undefined;
    relationshipOptions: { value: string; label: string }[] | undefined;
    /** Required pages still missing for the row at `dependent-{index}`. */
    getMissing: (index: number) => MissingRowDoc[];
    /**
     * Server-parity messages for every row whose required pages are
     * incomplete, e.g. «کارت ملی: برای «فرزند 1» حداقل 2 فایل لازم است.»
     */
    messages: string[];
};

/**
 * Live required-page feedback for the dependents rows of the profile form.
 * A thin employees-specific adapter over the generic
 * `useRowDocsFeedback` — it only contributes the section key, the row's
 * relationship-based label and its placement builder.
 */
export function useDependentDocsFeedback(
    uuid: string | number,
    rows: DependentFeedbackRow[],
): UseDependentDocsFeedbackReturn {
    const { data: formOptions } = useFormOptions();

    const feedback = useRowDocsFeedback(
        {
            entity: "employees",
            uuid,
            sectionKey: "dependents",
            categories: DEPENDENT_DOC_CATEGORIES,
            fieldKeyFor: (index) => `dependent-${index}`,
        },
        rows,
        {
            rowLabel: (row, index) =>
                dependentRowLabel(
                    row.relationship_type,
                    index,
                    formOptions?.relationship_type,
                ),
        },
    );

    return {
        isLoading: feedback.isLoading,
        rowGroup: feedback.group,
        relationshipOptions: formOptions?.relationship_type,
        getMissing: feedback.getMissing,
        messages: feedback.messages,
    };
}
