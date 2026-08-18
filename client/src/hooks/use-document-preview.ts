import { useMemo, useState, useCallback } from "react";

import { toLightboxDocument } from "@/components/documents/document-viewer";
import type { EntityDocument } from "@/hooks/use-entity-documents";

/**
 * Manages lightbox preview state for a collection of EntityDocuments.
 * Handles index tracking, open/close, and navigation.
 */
export function useDocumentPreview(documents: EntityDocument[]) {
    const [lightboxIndex, setLightboxIndex] = useState<number | null>(null);

    const lightboxDocs = useMemo(
        () => documents.map(toLightboxDocument),
        [documents],
    );

    const openPreview = useCallback(
        (doc: EntityDocument) => {
            const index = documents.findIndex(
                (d) => d.usage_id === doc.usage_id,
            );
            if (index !== -1) setLightboxIndex(index);
        },
        [documents],
    );

    const openPreviewAtIndex = useCallback((index: number) => {
        setLightboxIndex(index);
    }, []);

    const closePreview = useCallback(() => {
        setLightboxIndex(null);
    }, []);

    return {
        lightboxDocs,
        lightboxIndex,
        isPreviewOpen: lightboxIndex !== null,
        openPreview,
        openPreviewAtIndex,
        closePreview,
        navigatePreview: setLightboxIndex,
    };
}
