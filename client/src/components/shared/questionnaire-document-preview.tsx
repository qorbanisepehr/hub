"use client";

import * as React from "react";
import { IconFile, IconX } from "@tabler/icons-react";

import { cn } from "@/lib/utils";
import { DocumentPreviewLightbox } from "@/features/documents/components/document-preview-lightbox";
import { FileThumbnail } from "@/components/ui/file-thumbnail";
import { getFileIcon } from "@/lib/file-icon";
import { getFileColorClasses } from "@/lib/file-colors";
import type { QuestionnaireDocument } from "@/features/recruitment/hooks/use-questionnaire-documents";

type QuestionnaireDocPreviewProps = {
    documents: QuestionnaireDocument[];
    variant?: "thumbnail" | "list" | "compact";
    size?: "sm" | "md";
    showSize?: boolean;
    className?: string;
    onDelete?: (doc: QuestionnaireDocument) => void;
    isDeleting?: boolean;
};

function isImageMime(mime: string): boolean {
    return mime.startsWith("image/");
}

function formatBytes(bytes: number) {
    if (bytes === 0) return "0 بایت";
    const units = ["بایت", "کیلوبایت", "مگابایت", "گیگابایت"];
    const index = Math.min(
        Math.floor(Math.log(bytes) / Math.log(1024)),
        units.length - 1,
    );
    return `${(bytes / 1024 ** index).toFixed(index === 0 ? 0 : 1)} ${units[index]}`;
}

export function QuestionnaireDocThumbnail({
    doc,
    onClick,
    size = "md",
}: {
    doc: QuestionnaireDocument;
    onClick?: () => void;
    size?: "sm" | "md";
}) {
    const sizeClass = size === "sm" ? "size-12" : "size-20";

    if (isImageMime(doc.mime_type)) {
        return (
            <div
                role="button"
                tabIndex={0}
                onClick={onClick}
                onKeyDown={(e) => {
                    if (e.key === "Enter" || e.key === " ") {
                        e.preventDefault();
                        onClick?.();
                    }
                }}
                className={cn(
                    "overflow-hidden rounded-md border bg-background cursor-pointer hover:ring-2 hover:ring-primary/50 transition-all",
                    sizeClass,
                )}
            >
                <FileThumbnail
                    file={{ name: doc.original_name, type: doc.mime_type }}
                    previewImageUrl={doc.url}
                    className="size-full rounded-none border-0"
                    previewClassName="aspect-square"
                />
            </div>
        );
    }

    return (
        <div
            role="button"
            tabIndex={0}
            onClick={onClick}
            onKeyDown={(e) => {
                if (e.key === "Enter" || e.key === " ") {
                    e.preventDefault();
                    onClick?.();
                }
            }}
            className={cn(
                "flex shrink-0 items-center justify-center rounded-md border cursor-pointer hover:ring-2 hover:ring-primary/50 transition-all",
                getFileColorClasses(doc.mime_type),
                sizeClass,
            )}
        >
            {getFileIcon(doc.mime_type, "size-1/3")}
        </div>
    );
}

/**
 * A reusable component that displays questionnaire documents as
 * clickable thumbnails with a lightbox preview overlay.
 *
 * Use `variant="thumbnail"` for inline grid display,
 * `variant="compact"` for small inline with name,
 * `variant="list"` for a vertical list with details.
 */
export function QuestionnaireDocumentPreview({
    documents,
    variant = "thumbnail",
    size = "md",
    showSize = false,
    className,
    onDelete,
    isDeleting,
}: QuestionnaireDocPreviewProps) {
    const [lightboxOpen, setLightboxOpen] = React.useState(false);
    const [currentIndex, setCurrentIndex] = React.useState(0);

    // Convert QuestionnaireDocument to the shape DocumentPreviewLightbox expects
    const lightboxDocs = React.useMemo(() => {
        return documents.map((d) => ({
            id: d.id,
            uuid: d.uuid,
            original_name: d.original_name,
            mime_type: d.mime_type,
            size: d.size,
            record_key: d.record_key,
            category_slug: d.category_slug,
            url: d.url,
            // Bridge to Document type shape:
            documentable_type: "questionnaire",
            documentable_id: 0,
            document_category_id: 0,
            status: "pending" as const,
            notes: null,
            meta: null,
            current_revision: {
                id: 0,
                original_name: d.original_name,
                mime_type: d.mime_type,
                file_size: d.size,
                file_size_formatted: formatBytes(d.size),
                form_data: null,
                uploaded_by: null,
                uploader_name: null,
                created_at: "",
            },
            uploaded_by: null,
            uploader_name: null,
            serve_url: d.url,
            thumbnail_url: "",
            download_url: d.url,
            slot: null,
            entity_type: null,
            entity_id: null,
            disk: "local",
            path: "",
            hash: "",
            created_at: "",
            updated_at: "",
            deleted_at: null,
            category: undefined,
        }));
    }, [documents]);

    function openLightbox(index: number) {
        setCurrentIndex(index);
        setLightboxOpen(true);
    }

    if (documents.length === 0) return null;

    if (variant === "compact") {
        return (
            <>
                <div className={cn("flex flex-wrap gap-2", className)}>
                    {documents.map((doc, i) => (
                        <div
                            key={doc.id}
                            role="button"
                            tabIndex={0}
                            onClick={() => openLightbox(i)}
                            onKeyDown={(e) => {
                                if (e.key === "Enter" || e.key === " ") {
                                    e.preventDefault();
                                    openLightbox(i);
                                }
                            }}
                            className="flex items-center gap-2 rounded-md border bg-background px-2 py-1 cursor-pointer hover:bg-muted/50 transition-colors"
                        >
                            {isImageMime(doc.mime_type) ? (
                                <FileThumbnail
                                    file={{ name: doc.original_name, type: doc.mime_type }}
                                    previewImageUrl={doc.url}
                                    className="size-8 rounded-none border-0 shrink-0"
                                    previewClassName="aspect-square"
                                />
                            ) : (
                                <div className="flex size-8 items-center justify-center rounded bg-muted shrink-0">
                                    <IconFile className="size-4 text-muted-foreground" />
                                </div>
                            )}
                            <span className="truncate text-xs max-w-24">
                                {doc.original_name}
                            </span>
                        </div>
                    ))}
                </div>
                <DocumentPreviewLightbox
                    documents={lightboxDocs as any}
                    currentIndex={currentIndex}
                    open={lightboxOpen}
                    onClose={() => setLightboxOpen(false)}
                    onNavigate={(index) => setCurrentIndex(index)}
                />
            </>
        );
    }

    if (variant === "list") {
        return (
            <>
                <div className={cn("divide-y rounded-lg border", className)}>
                    {documents.map((doc, i) => (
                        <div
                            key={doc.id}
                            className="flex items-center gap-3 px-3 py-2 group"
                        >
                            <div
                                role="button"
                                tabIndex={0}
                                onClick={() => openLightbox(i)}
                                onKeyDown={(e) => {
                                    if (e.key === "Enter" || e.key === " ") {
                                        e.preventDefault();
                                        openLightbox(i);
                                    }
                                }}
                                className="flex items-center gap-3 flex-1 min-w-0 cursor-pointer"
                            >
                                {isImageMime(doc.mime_type) ? (
                                    <FileThumbnail
                                        file={{ name: doc.original_name, type: doc.mime_type }}
                                        previewImageUrl={doc.url}
                                        className="size-10 rounded-none border-0 shrink-0"
                                        previewClassName="aspect-square"
                                    />
                                ) : (
                                    <div className="flex size-10 items-center justify-center rounded bg-muted shrink-0">
                                        <IconFile className="size-5 text-muted-foreground" />
                                    </div>
                                )}
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm">
                                        {doc.original_name}
                                    </p>
                                    {showSize && (
                                        <p className="text-xs text-muted-foreground">
                                            {formatBytes(doc.size)}
                                        </p>
                                    )}
                                </div>
                            </div>
                            {onDelete && (
                                <button
                                    type="button"
                                    disabled={isDeleting}
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        onDelete(doc);
                                    }}
                                    className="opacity-0 group-hover:opacity-100 transition-opacity rounded-full p-1 text-muted-foreground hover:text-destructive hover:bg-destructive/10"
                                >
                                    <IconX className="size-4" />
                                </button>
                            )}
                        </div>
                    ))}
                </div>
                <DocumentPreviewLightbox
                    documents={lightboxDocs as any}
                    currentIndex={currentIndex}
                    open={lightboxOpen}
                    onClose={() => setLightboxOpen(false)}
                    onNavigate={(index) => setCurrentIndex(index)}
                />
            </>
        );
    }

    // default: thumbnail grid
    return (
        <>
            <div className={cn("flex flex-wrap gap-3", className)}>
                {documents.map((doc, i) => (
                    <QuestionnaireDocThumbnail
                        key={doc.id}
                        doc={doc}
                        size={size}
                        onClick={() => openLightbox(i)}
                    />
                ))}
            </div>
            <DocumentPreviewLightbox
                documents={lightboxDocs as any}
                currentIndex={currentIndex}
                open={lightboxOpen}
                onClose={() => setLightboxOpen(false)}
                onNavigate={(index) => setCurrentIndex(index)}
            />
        </>
    );
}

/**
 * Grouped view: show documents grouped by category label.
 * Each group has a heading + thumbnail grid with lightbox.
 */
export function QuestionnaireDocumentGrouped({
    groups,
    className,
}: {
    groups: { label: string; docs: QuestionnaireDocument[] }[];
    className?: string;
}) {
    const nonEmpty = groups.filter((g) => g.docs.length > 0);
    if (nonEmpty.length === 0) return null;

    return (
        <div className={cn("space-y-4", className)}>
            {nonEmpty.map((group) => (
                <div key={group.label}>
                    <p className="text-sm font-medium mb-2">{group.label}</p>
                    <QuestionnaireDocumentPreview
                        documents={group.docs}
                        variant="thumbnail"
                        size="md"
                    />
                </div>
            ))}
        </div>
    );
}
