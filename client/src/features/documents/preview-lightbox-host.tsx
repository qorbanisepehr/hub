import { useEffect } from "react";
import { useSelector } from "@tanstack/react-store";
import { useRouterState } from "@tanstack/react-router";

import { DocumentPreviewLightbox } from "@/features/documents/components/document-preview-lightbox";
import {
    closePreview,
    navigatePreview,
    previewStore,
} from "./preview-store";

/**
 * App-level singleton lightbox. Mounted once in `routes/__root.tsx`; every
 * preview trigger in the app writes to `previewStore` instead of mounting
 * its own lightbox.
 *
 * Closes on route change: previewing a document for an entity the user has
 * left is more confusing than re-opening it. (If UX later wants previews to
 * survive navigation, drop the effect — the store already supports it.)
 */
export function PreviewLightboxHost() {
    const open = useSelector(previewStore, (state) => state.open);
    const items = useSelector(previewStore, (state) => state.items);
    const index = useSelector(previewStore, (state) => state.index);

    const locationHref = useRouterState({
        select: (state) => state.location.href,
    });

    useEffect(() => {
        // Imperative snapshot read: navigation is the only trigger, and the
        // open flag must not be a reactive dependency here.
        if (previewStore.state.open) {
            closePreview();
        }
        // Navigation is the intended trigger; the store snapshot is read
        // imperatively on purpose.
        // oxlint-disable-next-line react/exhaustive-effect-dependencies -- navigation-triggered
    }, [locationHref]);

    return (
        <DocumentPreviewLightbox
            documents={items}
            currentIndex={index ?? 0}
            open={open && index !== null}
            onClose={closePreview}
            onNavigate={navigatePreview}
        />
    );
}
