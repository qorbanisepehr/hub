"use client";

import * as React from "react";
import {
    IconCamera,
    IconEye,
    IconFile,
    IconLoader2,
    IconUpload,
} from "@tabler/icons-react";
import { useQuery } from "@tanstack/react-query";

import { cn } from "@/lib/utils";
import { formatBytes } from "@/lib/file-utils";
import { getFileIcon } from "@/lib/file-utils";
import { getFileColorClasses } from "@/lib/file-utils";
import { useEntityDocuments } from "@/hooks/use-entity-documents";
import type { EntityDocument } from "@/hooks/use-entity-documents";
import { fetchDocumentCategories } from "@/features/documents/api";
import { useDocumentRequirements } from "@/features/documents/hooks/use-document-requirements";
import type { DocumentCategory } from "@/features/documents/types";
import { documentKeys } from "@/lib/query-keys";
import { BaseDropzone } from "@/components/shared/base-dropzone";
import { DocumentFileItem } from "@/components/documents";
import { FileThumbnail } from "@/components/ui/file-thumbnail";
import { ConfirmDeleteButton } from "@/components/ui/confirm-delete-button";
import { useDocumentUpload } from "@/hooks/use-document-upload";
import { useDocumentDelete } from "@/hooks/use-document-delete";
import { DocumentPreviewLightbox } from "@/features/documents/components/document-preview-lightbox";
import { toLightboxDocument } from "@/components/documents/document-viewer";

export type FileUploadFieldVariant =
    | "default"
    | "avatar"
    | "thumbnail"
    | "card";

export type FileUploadActionsPlacement = "overlay" | "row" | "column";

type FileUploadFieldProps = {
    uuid: string;
    categorySlug: string;
    label: string;
    /** Grant entity the upload targets. Defaults to "questionnaire". */
    entity?: string;
    variant?: FileUploadFieldVariant;
    accept?: string;
    multiple?: boolean;
    maxFiles?: number;
    notes?: string;
    /** Field placement within the requirement's section, e.g. "front" or "edu-0". */
    fieldKey?: string;
    categoryType?: string;
    aspectRatio?: number;
    description?: string;
    className?: string;
    /** Whether to show the delete action button. Defaults to true. */
    actionsEnabled?: boolean;
    /** Where the delete action button is rendered. Defaults to "overlay". */
    actionsPlacement?: FileUploadActionsPlacement;
    /** When enabled, current documents render a "replace" action. */
    replaceEnabled?: boolean;
    onReplace?: (doc: EntityDocument) => void;
    onUploadComplete?: (doc: EntityDocument) => void;
};

const DEFAULT_ACCEPT = [
    ".pdf",
    ".jpg",
    ".jpeg",
    ".png",
    ".webp",
    "application/pdf",
    "image/jpeg",
    "image/png",
    "image/webp",
].join(",");

const VARIANT_CONTAINER: Record<
    Exclude<FileUploadFieldVariant, "default">,
    string
> = {
    avatar: "w-28 rounded-xl overflow-hidden",
    thumbnail: "size-24 overflow-hidden rounded-lg",
    card: "",
};

/**
 * Shared state computed by the orchestrator and passed to variant sub-components.
 */
export type FileUploadVariantProps = {
    uuid: string;
    entity: string;
    categoryDocs: EntityDocument[];
    effectiveAccept: string;
    effectiveMaxFiles: number;
    canUpload: boolean;
    currentDoc: EntityDocument | null;
    isUploading: boolean;
    isDeleting: boolean;
    isPending: boolean;
    isImage: boolean;
    handleFiles: (fileList: File[]) => void;
    renderDelete: (doc: EntityDocument) => React.ReactNode;
    label: string;
    multiple: boolean;
    aspectRatio?: number;
    description?: string;
    className?: string;
    actionsEnabled: boolean;
    actionsPlacement: FileUploadActionsPlacement;
    replaceEnabled: boolean;
    onReplace?: (doc: EntityDocument) => void;
};

export function FileUploadField({
    uuid,
    categorySlug,
    label,
    entity = "questionnaire",
    variant = "default",
    accept = DEFAULT_ACCEPT,
    multiple = false,
    maxFiles = 1,
    notes,
    fieldKey,
    categoryType = "personnel",
    aspectRatio,
    description,
    className,
    actionsEnabled = true,
    actionsPlacement = "column",
    replaceEnabled = false,
    onReplace,
    onUploadComplete,
}: FileUploadFieldProps) {
    const { getDocumentsBySlug } = useEntityDocuments(entity, uuid);
    const categoryDocs = getDocumentsBySlug(categorySlug, fieldKey).filter(
        (d) => (notes !== undefined ? (d.notes ?? "") === notes : true),
    );

    const { data: categories } = useQuery({
        queryKey: documentKeys.categories(categoryType),
        queryFn: async () => {
            const { data } = await fetchDocumentCategories(categoryType);
            return data.data;
        },
    });

    const { data: requirements } = useDocumentRequirements(entity);

    const categoryId = React.useMemo(() => {
        function find(cats: DocumentCategory[]): number | undefined {
            for (const cat of cats) {
                if (cat.slug === categorySlug) {
                    return cat.id;
                }
                if (cat.children) {
                    const found = find(cat.children);
                    if (found !== undefined) return found;
                }
            }
            return undefined;
        }
        return categories ? find(categories) : undefined;
    }, [categories, categorySlug]);

    const requirement = requirements?.[categorySlug] ?? null;

    const effectiveMaxFiles = requirement?.max_files ?? maxFiles;
    const effectiveAccept = requirement?.mime_types?.join(",") ?? accept;

    const { handleFiles, isUploading } = useDocumentUpload({
        entity,
        uuid,
        categoryId,
        requirement,
        fieldKey,
        notes,
        onUploadComplete,
    });

    const { deleteDocument, isDeleting } = useDocumentDelete({
        entity,
        uuid,
    });

    const canUpload = multiple
        ? categoryDocs.length < effectiveMaxFiles
        : categoryDocs.length === 0;
    const currentDoc = categoryDocs[0] ?? null;
    const isPending = isUploading || isDeleting;
    const isImage = currentDoc?.mime_type.startsWith("image/") ?? false;

    const containerClass = cn(
        variant !== "default" && VARIANT_CONTAINER[variant],
    );

    const renderDelete = (doc: EntityDocument) =>
        actionsEnabled ? (
            <ConfirmDeleteButton
                iconOnly
                size="icon-xs"
                onConfirm={() => deleteDocument(doc.usage_id)}
                isPending={isDeleting}
                stopPropagation
            />
        ) : null;

    const variantProps: FileUploadVariantProps = {
        uuid,
        entity,
        categoryDocs,
        effectiveAccept,
        effectiveMaxFiles,
        canUpload,
        currentDoc,
        isUploading,
        isDeleting,
        isPending,
        isImage,
        handleFiles,
        renderDelete,
        label,
        multiple,
        aspectRatio,
        description,
        className,
        actionsEnabled,
        actionsPlacement,
        replaceEnabled,
        onReplace,
    };

    if (variant === "avatar") {
        return <AvatarVariant {...variantProps} containerClass={containerClass} />;
    }

    if (variant === "thumbnail") {
        return <ThumbnailVariant {...variantProps} containerClass={containerClass} />;
    }

    if (variant === "card") {
        return <CardVariant {...variantProps} />;
    }

    return <DefaultVariant {...variantProps} />;
}

// ── Variant: avatar (photo with configurable aspect ratio) ──

function AvatarVariant({
    containerClass,
    label,
    currentDoc,
    isImage,
    isPending,
    isUploading,
    canUpload,
    effectiveAccept,
    handleFiles,
    actionsEnabled,
    actionsPlacement,
    renderDelete,
    aspectRatio,
    description,
    className,
}: FileUploadVariantProps & { containerClass: string }) {
    const [previewOpen, setPreviewOpen] = React.useState(false);

    return (
        <div className={cn("flex flex-col items-center gap-2", className)}>
            {label && <span className="text-sm font-medium">{label}</span>}
            <BaseDropzone
                accept={effectiveAccept}
                onFilesSelected={handleFiles}
                disabled={isPending}
                disablePick={!canUpload}
                className={containerClass}
                style={{ aspectRatio: String(aspectRatio ?? 1) }}
            >
                <div
                    className={cn(
                        "group relative flex size-full items-center justify-center overflow-hidden rounded-xl transition-all duration-200",
                        currentDoc
                            ? "ring-2 ring-border hover:ring-ring"
                            : "border-2 border-dashed border-border hover:border-ring hover:bg-muted/50",
                        isPending && "pointer-events-none opacity-60",
                    )}
                >
                    {currentDoc ? (
                        <>
                            {isImage ? (
                                <FileThumbnail
                                    file={{
                                        name: currentDoc.structure_name,
                                        type: currentDoc.mime_type,
                                    }}
                                    previewImageUrl={currentDoc.url}
                                    className="size-full rounded-none border-0"
                                    previewClassName={
                                        aspectRatio
                                            ? undefined
                                            : "aspect-square"
                                    }
                                    previewAspectRatio={aspectRatio}
                                />
                            ) : (
                                <div className="flex size-full flex-col items-center justify-center gap-1 bg-muted p-2">
                                    <IconFile className="size-8 text-muted-foreground" />
                                    <span className="max-w-full truncate text-[10px] text-muted-foreground">
                                        {currentDoc.structure_name}
                                    </span>
                                </div>
                            )}
                            {actionsEnabled && (
                                <div className="absolute inset-0 flex items-center justify-center gap-1 rounded-xl bg-black/50 opacity-0 transition-opacity group-hover:opacity-100">
                                    <button
                                        type="button"
                                        onClick={(e) => {
                                            e.stopPropagation();
                                            setPreviewOpen(true);
                                        }}
                                        className="rounded-full p-1.5 text-white hover:bg-white/20"
                                        aria-label="پیش‌نمایش"
                                    >
                                        <IconEye className="size-4" />
                                    </button>
                                    {actionsPlacement === "overlay" &&
                                        renderDelete(currentDoc)}
                                </div>
                            )}
                        </>
                    ) : (
                        <div className="flex flex-col items-center gap-1 text-muted-foreground">
                            {isUploading ? (
                                <IconLoader2 className="size-6 animate-spin" />
                            ) : (
                                <IconCamera className="size-6" />
                            )}
                            <span className="text-[10px]">
                                {isUploading
                                    ? "در حال آپلود..."
                                    : "آپلود عکس"}
                            </span>
                        </div>
                    )}
                </div>
            </BaseDropzone>
            {currentDoc &&
                actionsEnabled &&
                actionsPlacement !== "overlay" && (
                    <div
                        className={cn(
                            "flex",
                            actionsPlacement === "column"
                                ? "flex-col items-center"
                                : "flex-row items-center gap-1",
                        )}
                    >
                        <button
                            type="button"
                            onClick={() => setPreviewOpen(true)}
                            className="rounded-full p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                            aria-label="پیش‌نمایش"
                        >
                            <IconEye className="size-4" />
                        </button>
                        {renderDelete(currentDoc)}
                    </div>
                )}
            {description && (
                <p className="max-w-45 text-center text-xs text-muted-foreground">
                    {description}
                </p>
            )}
            {currentDoc && (
                <DocumentPreviewLightbox
                    documents={[toLightboxDocument(currentDoc)]}
                    currentIndex={0}
                    open={previewOpen}
                    onClose={() => setPreviewOpen(false)}
                    onNavigate={() => {}}
                />
            )}
        </div>
    );
}

// ── Variant: thumbnail (square preview) ──

function ThumbnailVariant({
    containerClass,
    label,
    currentDoc,
    isImage,
    isPending,
    isUploading,
    canUpload,
    effectiveAccept,
    handleFiles,
    actionsEnabled,
    actionsPlacement,
    renderDelete,
    aspectRatio,
    description,
    className,
}: FileUploadVariantProps & { containerClass: string }) {
    const [previewOpen, setPreviewOpen] = React.useState(false);

    return (
        <div className={cn("space-y-1.5", className)}>
            {label && <span className="text-sm font-medium">{label}</span>}
            <BaseDropzone
                accept={effectiveAccept}
                onFilesSelected={handleFiles}
                disabled={isPending}
                disablePick={!canUpload}
                className={cn(
                    containerClass,
                    "border border-dashed border-border hover:border-ring hover:bg-muted/30",
                    currentDoc && "border-solid hover:bg-transparent",
                )}
                style={
                    aspectRatio
                        ? { aspectRatio: String(aspectRatio) }
                        : undefined
                }
            >
                <div className="group relative size-full">
                    {currentDoc ? (
                        <>
                            {isImage ? (
                                <FileThumbnail
                                    file={{
                                        name: currentDoc.structure_name,
                                        type: currentDoc.mime_type,
                                    }}
                                    previewImageUrl={currentDoc.url}
                                    className="size-full rounded-none border-0"
                                    previewClassName={
                                        aspectRatio
                                            ? undefined
                                            : "aspect-square"
                                    }
                                    previewAspectRatio={aspectRatio}
                                />
                            ) : (
                                <div
                                    className={cn(
                                        "flex size-full flex-col items-center justify-center gap-1 bg-muted p-2",
                                        getFileColorClasses(
                                            currentDoc.mime_type,
                                        ),
                                    )}
                                >
                                    {getFileIcon(
                                        currentDoc.mime_type,
                                        "size-8",
                                    )}
                                    <span className="max-w-full truncate text-[10px] text-muted-foreground">
                                        {currentDoc.structure_name}
                                    </span>
                                </div>
                            )}
                            {actionsEnabled && (
                                <div className="absolute inset-0 flex items-center justify-center gap-1 bg-black/50 opacity-0 transition-opacity group-hover:opacity-100">
                                    <button
                                        type="button"
                                        onClick={(e) => {
                                            e.stopPropagation();
                                            setPreviewOpen(true);
                                        }}
                                        className="rounded-full p-1.5 text-white hover:bg-white/20"
                                        aria-label="پیش‌نمایش"
                                    >
                                        <IconEye className="size-4" />
                                    </button>
                                    {actionsPlacement === "overlay" &&
                                        renderDelete(currentDoc)}
                                </div>
                            )}
                        </>
                    ) : (
                        <div className="flex size-full flex-col items-center justify-center gap-1.5 text-muted-foreground">
                            {isUploading ? (
                                <IconLoader2 className="size-6 animate-spin" />
                            ) : (
                                <IconUpload className="size-6" />
                            )}
                            <span className="text-xs">
                                {isUploading
                                    ? "در حال آپلود..."
                                    : "انتخاب فایل"}
                            </span>
                        </div>
                    )}
                </div>
            </BaseDropzone>
            {currentDoc &&
                actionsEnabled &&
                actionsPlacement !== "overlay" && (
                    <div
                        className={cn(
                            "flex",
                            actionsPlacement === "column"
                                ? "flex-col items-center"
                                : "flex-row items-center gap-1",
                        )}
                    >
                        <button
                            type="button"
                            onClick={() => setPreviewOpen(true)}
                            className="rounded-full p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                            aria-label="پیش‌نمایش"
                        >
                            <IconEye className="size-4" />
                        </button>
                        {renderDelete(currentDoc)}
                    </div>
                )}
            {description && (
                <p className="text-xs text-muted-foreground">
                    {description}
                </p>
            )}
            {currentDoc && (
                <DocumentPreviewLightbox
                    documents={[toLightboxDocument(currentDoc)]}
                    currentIndex={0}
                    open={previewOpen}
                    onClose={() => setPreviewOpen(false)}
                    onNavigate={() => {}}
                />
            )}
        </div>
    );
}

// ── Variant: card (grid of previews + add tile) ──

function CardVariant({
    label,
    categoryDocs,
    isUploading,
    isPending,
    canUpload,
    effectiveAccept,
    multiple,
    handleFiles,
    actionsEnabled,
    actionsPlacement,
    renderDelete,
    description,
    className,
}: FileUploadVariantProps) {
    return (
        <div className={cn("space-y-2", className)}>
            {label && <span className="text-sm font-medium">{label}</span>}
            <BaseDropzone
                accept={effectiveAccept}
                multiple={multiple}
                onFilesSelected={handleFiles}
                disabled={isPending}
                disablePick={!canUpload}
                className={cn(
                    "rounded-lg border border-dashed border-border hover:border-ring hover:bg-muted/30 transition-colors",
                    categoryDocs.length > 0 && "border-solid",
                )}
            >
                {categoryDocs.length > 0 ? (
                    <div className="grid grid-cols-2 gap-2 p-2 sm:grid-cols-3">
                        {categoryDocs.map((doc) => (
                            <div
                                key={doc.usage_id}
                                className="group relative flex flex-col overflow-hidden rounded-md border bg-background"
                            >
                                <div className="relative aspect-square">
                                    {doc.mime_type.startsWith("image/") ? (
                                        <FileThumbnail
                                            file={{
                                                name: doc.structure_name,
                                                type: doc.mime_type,
                                            }}
                                            previewImageUrl={doc.url}
                                            className="size-full rounded-none border-0"
                                            previewClassName="aspect-square"
                                        />
                                    ) : (
                                        <div
                                            className={cn(
                                                "flex size-full items-center justify-center",
                                                getFileColorClasses(
                                                    doc.mime_type,
                                                ),
                                            )}
                                        >
                                            {getFileIcon(
                                                doc.mime_type,
                                                "size-8",
                                            )}
                                        </div>
                                    )}
                                    {actionsEnabled &&
                                        actionsPlacement === "overlay" && (
                                            <div className="absolute inset-0 flex items-center justify-center bg-black/50 opacity-0 transition-opacity group-hover:opacity-100">
                                                {renderDelete(doc)}
                                            </div>
                                        )}
                                </div>
                                <div className="flex flex-col gap-0.5 p-2">
                                    <span className="truncate text-xs font-medium">
                                        {doc.structure_name}
                                    </span>
                                    <span className="text-[10px] text-muted-foreground">
                                        {formatBytes(doc.size)}
                                    </span>
                                    {actionsEnabled &&
                                        actionsPlacement !== "overlay" && (
                                            <div
                                                className={cn(
                                                    "flex",
                                                    actionsPlacement ===
                                                        "column"
                                                        ? "flex-col items-center gap-1"
                                                        : "flex-row items-center gap-1",
                                                )}
                                            >
                                                {renderDelete(doc)}
                                            </div>
                                        )}
                                </div>
                            </div>
                        ))}
                        {canUpload && (
                            <div className="flex aspect-square items-center justify-center rounded-md border border-dashed border-border text-muted-foreground transition-colors hover:border-ring hover:bg-muted/30">
                                {isUploading ? (
                                    <IconLoader2 className="size-6 animate-spin" />
                                ) : (
                                    <IconUpload className="size-6" />
                                )}
                            </div>
                        )}
                    </div>
                ) : (
                    <div className="flex flex-col items-center gap-2 py-8 text-muted-foreground">
                        {isUploading ? (
                            <IconLoader2 className="size-6 animate-spin" />
                        ) : (
                            <IconUpload className="size-6" />
                        )}
                        <span className="text-xs">
                            {isUploading
                                ? "در حال آپلود..."
                                : "انتخاب فایل"}
                        </span>
                    </div>
                )}
            </BaseDropzone>
            {description && (
                <p className="text-xs text-muted-foreground">
                    {description}
                </p>
            )}
        </div>
    );
}

// ── Variant: default (list of files) ──

function DefaultVariant({
    label,
    categoryDocs,
    isUploading,
    isPending,
    canUpload,
    effectiveAccept,
    multiple,
    handleFiles,
    uuid,
    entity,
    actionsEnabled,
    replaceEnabled,
    onReplace,
    className,
}: FileUploadVariantProps) {
    return (
        <div className={cn("space-y-2", className)}>
            {label && <span className="text-sm font-medium">{label}</span>}

            {canUpload && (
                <BaseDropzone
                    accept={effectiveAccept}
                    multiple={multiple}
                    onFilesSelected={handleFiles}
                    disabled={isPending}
                    className="flex min-h-24 flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-border bg-background px-4 py-4 text-center transition-colors hover:border-ring hover:bg-muted/30"
                >
                    {isUploading ? (
                        <IconLoader2 className="size-5 animate-spin text-muted-foreground" />
                    ) : (
                        <IconUpload className="size-5 text-muted-foreground" />
                    )}
                    <span className="text-xs text-muted-foreground">
                        {isUploading ? "در حال آپلود..." : "انتخاب فایل"}
                    </span>
                </BaseDropzone>
            )}

            {categoryDocs.length > 0 && (
                <div className="rounded-lg border bg-background">
                    {categoryDocs.map((doc) => (
                        <DocumentFileItem
                            key={doc.usage_id}
                            uuid={uuid}
                            entity={entity}
                            doc={doc}
                            subtitle={formatBytes(doc.size)}
                            actionsEnabled={actionsEnabled}
                            onReplace={
                                replaceEnabled
                                    ? () => onReplace?.(doc)
                                    : undefined
                            }
                            className="border-b px-3 py-2 last:border-b-0"
                        />
                    ))}
                </div>
            )}
        </div>
    );
}
