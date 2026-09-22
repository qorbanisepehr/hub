import { useMemo } from "react";
import { useSelector } from "@tanstack/react-store";

import { toLightboxDocument } from "@/components/documents/document-viewer";
import { previewStore } from "@/features/documents/preview-store";
import {
    closePreview,
    navigatePreview,
    openPreview as openPreviewInStore,
} from "@/features/documents/preview-store";
import type { EntityDocument } from "@/hooks/use-entity-documents";

/**
 * Preview state for a collection of EntityDocuments, backed by the shared
 * app-level preview store (see features/documents/preview-store.ts). The
 * lightbox itself renders once app-wide via `PreviewLightboxHost` — this hook
 * only maps items and hands out open/navigate/close callbacks.
 *
 * Public signature is unchanged from the previous local-state version, so
 * call sites keep working; the difference is that the open preview survives
 * route transitions and never mounts a second lightbox.
 */
export function useDocumentPreview(documents: EntityDocument[]) {
    const lightboxDocs = useMemo(
        () => documents.map(toLightboxDocument),
        [documents],
    );

    const isPreviewOpen = useSelector(previewStore, (state) => state.open);
    const lightboxIndex = useSelector(previewStore, (state) => state.index);

    const openPreview = (doc: EntityDocument) => {
        const index = documents.findIndex((d) => d.usage_id === doc.usage_id);
        if (index !== -1) openPreviewInStore(lightboxDocs, index);
    };

    const openPreviewAtIndex = (index: number) => {
        openPreviewInStore(lightboxDocs, index);
    };

    return {
        lightboxDocs,
        lightboxIndex,
        isPreviewOpen,
        openPreview,
        openPreviewAtIndex,
        closePreview,
        navigatePreview,
    };
}
