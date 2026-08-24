import { useEffect, useRef } from "react";
import type { AnyFormApi } from "@tanstack/react-form";

/**
 * One-shot rebase of the saved-values snapshot after async options load.
 *
 * Section-level `useEffect` hooks (spouse employment auto-select, military
 * status init, etc.) fire when their option queries resolve and may call
 * `form.setFieldValue(..., { dontUpdateMeta: true })`. These derived writes
 * are part of the loaded record's baseline — NOT user edits — so they must
 * not count as unsaved changes.
 *
 * Call this hook in each section component that has auto-select effects.
 * When `ready` transitions to `true`, a `requestAnimationFrame` callback
 * runs AFTER the section's own effects have settled and invokes `onSync`
 * with the settled values; `useSectionForm` rebases its saved snapshot so
 * both `isDirty` and `isSectionDirty()` stay false.
 *
 * Deliberately does NOT call `form.reset()`: resetting here would wipe any
 * keystrokes made within the same frame and silently cleared touched/dirty
 * state for edits typed before options finished loading.
 *
 * StrictMode-safe: the cleanup flag prevents a cancelled rAF from syncing,
 * and the `hasSynced` ref allows the re-mount to run the sync again.
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
            onSync?.(form.state.values);
        });

        return () => cancelAnimationFrame(rafRef.current);
    }, [ready, form, onSync]);
}
