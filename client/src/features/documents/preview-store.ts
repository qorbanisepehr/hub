import { createStore } from "@tanstack/react-store";

import type { Document } from "@/features/documents/types";

/**
 * Global document-preview state (P0 of the state-management migration plan,
 * docs/state-management-migration-plan.md).
 *
 * Owns the lightbox selection for the whole app; the lightbox itself is
 * rendered once by `PreviewLightboxHost` in the root route. State lives
 * outside the React tree, so an open preview survives route transitions and
 * re-renders stay scoped to components that read the store.
 *
 * No-mirror rule: this store holds only the preview selection — never server
 * data. Items are passed in at open time by the caller from its own props.
 */
type PreviewState = {
    open: boolean;
    items: Document[];
    /** Index into `items`; null while closed. */
    index: number | null;
};

export const previewStore = createStore<PreviewState>({
    open: false,
    items: [],
    index: null,
});

/**
 * Open the shared lightbox on `items` at `index`. Callers pass their own
 * document list (already mapped via `toLightboxDocument` when starting from
 * EntityDocuments); the store holds the reference for the preview session.
 */
export function openPreview(items: Document[], index: number): void {
    if (items.length === 0) return;
    previewStore.setState(() => ({
        open: true,
        items,
        index,
    }));
}

export function navigatePreview(index: number): void {
    previewStore.setState((state) => ({
        ...state,
        index,
    }));
}

export function closePreview(): void {
    previewStore.setState(() => ({
        open: false,
        items: [],
        index: null,
    }));
}
