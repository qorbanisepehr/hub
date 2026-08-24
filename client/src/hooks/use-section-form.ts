import { useCallback, useMemo, useState } from "react";
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
 * Dirty tracking is VALUE-based against a `savedValues` snapshot taken at
 * mount and rebased after every successful save / defaults sync. TanStack's
 * meta-based `isDirty` is deliberately NOT used as the source of truth:
 * programmatic writes with `{ dontUpdateMeta: true }` (auto-select effects)
 * are real unsaved changes it can never see, and its baseline semantics fight
 * async defaults. The value comparison sees exactly what a save would send.
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

    // The form owns its values after mount. Component-provided defaults must
    // be referentially AND structurally stable: TanStack Form v1's update()
    // treats any incoming defaultValues that differ from the runtime ones as
    // "async initial data arrived" and, while the form is untouched, it
    // OVERWRITES every field value with them (TanStack/form#1681). Building
    // defaults inline would do exactly that on the render right after
    // form.reset(...) — fields flash back to the stale server snapshot, then
    // snap forward when the invalidated detail query lands.
    //
    // So: freeze the baseline once, sync server data ONLY via explicit
    // reset(..., { keepDefaultValues: true }), and rebase the frozen state
    // afterwards so update() never gets a delta to act on.
    const [defaultValues] = useState(() => buildDefaultValues(entity));

    const form = useForm({ defaultValues });

    // Seed the saved snapshot from the SAME object tree the form was built
    // from; a second buildDefaultValues(entity) call would produce fresh
    // nested objects that never value-match and falsely report dirty.
    const [savedValues, setSavedValues] = useState<TFormValues>(
        () => form.state.values,
    );

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
            // keepDefaultValues: reset must not touch the runtime defaults —
            // the very next render hands update() the frozen baseline and any
            // delta there would clobber these values (the blink). Rebase the
            // frozen baseline instead; its content matches these values, so
            // the adapter's comparison becomes a no-op write.
            form.reset(reconciledValues, { keepDefaultValues: true });
            setSavedValues(reconciledValues);

            if (successMessage) {
                toast.success(successMessage);
            }
        },
        onError: (error) => {
            toast.error(getApiError(error) ?? errorMessage);
        },
    });

    const isSectionDirty = useCallback(
        (sectionKey: string): boolean => {
            const current = extractSectionData(form.state.values, sectionKey);
            const saved = extractSectionData(savedValues, sectionKey);
            return !deepEqual(current, saved);
        },
        [extractSectionData, form, savedValues],
    );

    /**
     * Save one section unless it already matches the last-saved snapshot.
     * This is the single choke point for spurious saves — repeater
     * expand/collapse, tab switches, and explicit save buttons all funnel
     * through here, so untouched sections never hit the network. Pass
     * `{ force: true }` to save regardless (rarely needed).
     */
    const persistSection = useCallback(
        (
            sectionKey: string,
            options?: { force?: boolean },
        ): boolean => {
            if (!options?.force && !isSectionDirty(sectionKey)) {
                return false;
            }
            const data = extractSectionData(form.state.values, sectionKey);
            saveMutation.mutate({ section: sectionKey, data });
            return true;
        },
        [extractSectionData, form, isSectionDirty, saveMutation],
    );

    const currentValues = useStore(form.store, (s) => s.values);
    const isDirty = useMemo(
        () => !deepEqual(currentValues, savedValues),
        [currentValues, savedValues],
    );

    /**
     * Rebase the saved snapshot after programmatic value writes (auto-select
     * effects settling once options load). Without this, derived defaults
     * written on mount would permanently count as unsaved changes.
     */
    const syncDefaults = useCallback((newValues: TFormValues) => {
        setSavedValues(newValues);
    }, []);

    return { form, saveMutation, persistSection, isDirty, isSectionDirty, syncDefaults };
}
