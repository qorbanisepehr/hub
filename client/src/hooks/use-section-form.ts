import { useCallback } from "react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useForm, useStore } from "@tanstack/react-form";
import { toast } from "sonner";

import { getApiError } from "@/lib/error-utils";

type SaveSectionResponse<TEntity> = { data: { data: TEntity } };

type UseSectionFormOptions<TEntity, TFormValues> = {
    entity: TEntity;
    buildDefaultValues: (entity: TEntity) => TFormValues;
    extractSectionData: (
        values: TFormValues,
        sectionKey: string,
    ) => Record<string, unknown>;
    /** Saves one section, returning the authoritative entity wrapped like `{ data: { data: TEntity } }`. */
    saveSection: (
        section: string,
        data: Record<string, unknown>,
    ) => Promise<SaveSectionResponse<TEntity>>;
    detailQueryKey: () => readonly unknown[];
    /** Top-level form keys reconciled from the server for a section (identity fields living outside the section objects). */
    sectionTopLevelKeys?: Partial<Record<string, (keyof TFormValues)[]>>;
    successMessage?: string;
    errorMessage?: string;
};

/**
 * Shared plumbing for multi-section forms (employee profile, questionnaire
 * wizard, CV wizard). Each section is saved independently; after a successful
 * save the form reconciles ONLY the saved section (plus its top-level identity
 * fields) from the authoritative server response, leaving every other section's
 * in-memory edits untouched.
 *
 * A full `form.reset(buildDefaultValues(response))` would clobber unsaved edits
 * in other sections (e.g. a save landing while the user is already typing in
 * the next tab/step), so reconciliation spreads the current values and replaces
 * just the saved section. The detail query is invalidated so other consumers
 * re-fetch the fresh entity.
 */
export function useSectionForm<TEntity, TFormValues>({
    entity,
    buildDefaultValues,
    extractSectionData,
    saveSection,
    detailQueryKey,
    sectionTopLevelKeys = {},
    successMessage,
    errorMessage = "خطا در ذخیره‌سازی",
}: UseSectionFormOptions<TEntity, TFormValues>) {
    const queryClient = useQueryClient();

    const form = useForm({
        defaultValues: buildDefaultValues(entity),
    });

    const saveMutation = useMutation({
        mutationFn: ({
            section,
            data,
        }: {
            section: string;
            data: Record<string, unknown>;
        }) => saveSection(section, data),
        onSuccess: (response, { section }) => {
            queryClient.invalidateQueries({
                queryKey: detailQueryKey(),
            });

            const serverValues = buildDefaultValues(response.data.data);
            const topLevelKeys = sectionTopLevelKeys[section] ?? [];
            const serverSection = (
                serverValues as Record<string, unknown>
            )[section];
            const reconciled: Record<string, unknown> = {
                ...form.state.values,
                [section]: serverSection,
            };
            for (const key of topLevelKeys) {
                reconciled[key as string] = serverValues[key];
            }
            form.reset(reconciled as TFormValues);

            if (successMessage) {
                toast.success(successMessage);
            }
        },
        onError: (error) => {
            toast.error(getApiError(error) ?? errorMessage);
        },
    });

    const persistSection = useCallback(
        (sectionKey: string, values: TFormValues = form.state.values) => {
            const data = extractSectionData(values, sectionKey);
            saveMutation.mutate({ section: sectionKey, data });
        },
        [extractSectionData, form, saveMutation],
    );

    const isDirty = useStore(form.store, (s) => s.isDirty);

    return { form, saveMutation, persistSection, isDirty };
}
