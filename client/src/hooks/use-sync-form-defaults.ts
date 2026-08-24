import { useEffect, useRef } from "react";
import type { AnyFormApi } from "@tanstack/react-form";

/**
 * One-shot sync of form defaults after async options load.
 *
 * Section-level `useEffect` hooks (spouse employment auto-select, military
 * status init, etc.) fire when their option queries resolve and may call
 * `form.setFieldValue()`. Because TanStack Form's `isDirty` compares
 * current values against the original `defaultValues`, these auto-selections
 * falsely mark the form dirty.
 *
 * Call this hook in each section component that has auto-select effects.
 * When `ready` transitions to `true`, a `requestAnimationFrame` callback
 * calls `form.reset(form.state.values)` — updating the defaults to match
 * the post-effect values so `isDirty` stays `false`.
 *
 * StrictMode-safe: the cleanup flag prevents a cancelled rAF from syncing,
 * and the `hasSynced` ref allows the re-mount to run the sync again.
 *
 * @param onSync - Optional callback invoked with the new values after the
 *   form is reset. Use this to update `lastSavedRef` in `useSectionForm`
 *   so that `isSectionDirty()` doesn't permanently return true.
 */
export function useSyncFormDefaults(
    form: AnyFormApi,
    ready: boolean,
    onSync?: (values: unknown) => void,
): void {
    const hasSynced = useRef(false);
    const rafRef = useRef(0);

    useEffect(() => {
        if (!ready || hasSynced.current) return;

        rafRef.current = requestAnimationFrame(() => {
            hasSynced.current = true;
            form.reset(form.state.values);
            onSync?.(form.state.values);
        });

        return () => cancelAnimationFrame(rafRef.current);
    }, [ready, form, onSync]);
}
