import { useCallback, useRef } from "react";
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
 * Structural equality for JSON-safe form values. Nested objects/arrays
 * (`military_status`, repeater rows) compare by value, so a fresh-but-
 * identical object tree from a rebuild never counts as dirty.
 */
function deepEqual(a: unknown, b: unknown): boolean {
    if (a === b) return true;
    if (Array.isArray(a) || Array.isArray(b)) {
        if (!Array.isArray(a) || !Array.isArray(b)) return false;
        if (a.length !== b.length) return false;
        return a.every((item, index) => deepEqual(item, b[index]));
    }
    if (a == null || b == null || typeof a !== "object" || typeof b !== "object") {
        return a === b;
    }
    const recordA = a as Record<string, unknown>;
    const recordB = b as Record<string, unknown>;
    const keysA = Object.keys(recordA);
    const keysB = Object.keys(recordB);
    if (keysA.length !== keysB.length) return false;
    return keysA.every(
        (key) => key in recordB && deepEqual(recordA[key], recordB[key]),
    );
}

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
 *
 * Dirty tracking is section-level: a `lastSavedRef` stores the form snapshot
 * after each successful save. `isSectionDirty(key)` compares current values
 * against that snapshot, so saved sections are never falsely dirty even if the
 * server normalises values.
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

    // Seed the saved snapshot from the SAME object tree the form was built
    // from; a second buildDefaultValues(entity) call would produce fresh
    // nested objects that never reference-match and falsely report dirty.
    const lastSavedRef = useRef(form.state.values);

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
            const currentValues = JSON.parse(JSON.stringify(form.state.values)) as Record<string, unknown>;
            const reconciled: Record<string, unknown> = {
                ...currentValues,
                [section]: serverSection,
            };
            for (const key of topLevelKeys) {
                reconciled[key as string] = serverValues[key];
            }
            const reconciledValues = reconciled as TFormValues;
            form.reset(reconciledValues);
            lastSavedRef.current = reconciledValues;

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

    const isSectionDirty = useCallback(
        (sectionKey: string): boolean => {
            const current = extractSectionData(form.state.values, sectionKey);
            const saved = extractSectionData(lastSavedRef.current, sectionKey);
            return !deepEqual(current, saved);
        },
        [extractSectionData, form],
    );

    const isDirty = useStore(form.store, (s) => s.isDirty);

    /**
     * Update the last-saved snapshot after the form defaults are synced
     * (e.g. from useSyncFormDefaults). Without this, isSectionDirty()
     * would permanently return true because lastSavedRef still holds the
     * pre-sync defaults.
     */
    const syncDefaults = useCallback((newValues: TFormValues) => {
        lastSavedRef.current = newValues;
    }, []);

    return { form, saveMutation, persistSection, isDirty, isSectionDirty, syncDefaults };
}
