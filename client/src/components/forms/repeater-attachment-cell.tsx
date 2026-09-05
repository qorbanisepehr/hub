"use client";

import { IconPaperclip } from "@tabler/icons-react";

import { cn } from "@/lib/utils";
import { FileThumbnail } from "@/components/ui/file-thumbnail";
import { getFileIcon } from "@/lib/file-utils";
import { getFileColorClasses } from "@/lib/file-utils";
import { useDocumentPreview } from "@/hooks/use-document-preview";
import { DocumentPreviewLightbox } from "@/features/documents/components/document-preview-lightbox";
import type { QuestionnaireDocument } from "@/features/questionnaire/hooks/use-questionnaire-documents";

type RepeaterAttachmentCellProps = {
    docs: QuestionnaireDocument[];
    className?: string;
    /**
     * Clicking a thumbnail/file icon opens the full document preview
     * (lightbox with navigation). Renders nothing interactive when off.
     */
    enablePreview?: boolean;
};

function AttachmentThumbnail({
    doc,
    onOpen,
}: {
    doc: QuestionnaireDocument;
    onOpen?: () => void;
}) {
    if (doc.mime_type.startsWith("image/")) {
        return (
            <FileThumbnail
                file={{ name: doc.structure_name, type: doc.mime_type }}
                previewImageUrl={doc.url}
                className="size-8 shrink-0 rounded border-0"
                previewClassName="aspect-square"
                onPreview={onOpen}
            />
        );
    }

    return (
        <button
            type="button"
            className={cn(
                "flex size-8 shrink-0 cursor-pointer items-center justify-center rounded",
                onOpen === undefined && "cursor-default",
                getFileColorClasses(doc.mime_type),
            )}
            onClick={onOpen}
            disabled={onOpen === undefined}
            aria-disabled={onOpen === undefined}
        >
            {getFileIcon(doc.mime_type, "size-4")}
        </button>
    );
}

/**
 * Reusable "attachment" table cell for repeaters.
 * Shows a thumbnail / file-icon for each uploaded document
 * or a muted placeholder when nothing is uploaded.
 *
 * With `enablePreview`, the cell owns its preview lightbox: clicking any
 * thumbnail or file icon opens the document with full navigation — no
 * per-consumer wiring needed.
 */
export function RepeaterAttachmentCell({
    docs,
    className,
    enablePreview = false,
}: RepeaterAttachmentCellProps) {
    const {
        lightboxDocs,
        lightboxIndex,
        isPreviewOpen,
        openPreview,
        closePreview,
        navigatePreview,
    } = useDocumentPreview(enablePreview ? docs : []);

    if (docs.length === 0) {
        return (
            <span className={cn("inline-flex items-center text-muted-foreground/40", className)}>
                <IconPaperclip className="size-3.5" />
            </span>
        );
    }

    const onOpen = enablePreview ? openPreview : undefined;

    return (
        <>
            <div className={cn("flex flex-wrap items-center gap-1.5", className)}>
                {docs.map((doc) => (
                    <AttachmentThumbnail key={doc.usage_id} doc={doc} onOpen={() => onOpen?.(doc)} />
                ))}
            </div>
            {enablePreview && (
                <DocumentPreviewLightbox
                    documents={lightboxDocs}
                    currentIndex={lightboxIndex ?? 0}
                    open={isPreviewOpen}
                    onClose={closePreview}
                    onNavigate={navigatePreview}
                />
            )}
        </>
    );
}

type RepeaterAttachmentColumnOptions = {
    categorySlug: string;
    /** field-key prefix, e.g. "edu-", "lang-", "train-" */
    fieldKeyPrefix: string;
    getDocumentsBySlug: (slug: string, fieldKey?: string) => QuestionnaireDocument[];
    /** Opens the full preview lightbox on thumbnail/icon click. */
    enablePreview?: boolean;
};

/**
 * Builds the standard `_attachment` repeater column for the given
 * document category + field-key prefix, wiring each row to its docs.
 */
export function repeaterAttachmentColumn({
    categorySlug,
    fieldKeyPrefix,
    getDocumentsBySlug,
    enablePreview = false,
}: RepeaterAttachmentColumnOptions) {
    return {
        key: "_attachment",
        label: "پیوست",
        render: (_value: unknown, _item: unknown, index: number) => (
            <RepeaterAttachmentCell
                docs={getDocumentsBySlug(categorySlug, `${fieldKeyPrefix}${index}`)}
                enablePreview={enablePreview}
            />
        ),
    };
}
