"use client";

import * as React from "react";
import {
    IconCamera,
    IconFile,
    IconLoader2,
    IconUpload,
} from "@tabler/icons-react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { cn } from "@/lib/utils";
import { publicApi } from "@/lib/public-api";
import { getApiError } from "@/lib/error-utils";
import { documentKeys } from "@/lib/query-keys";
import { formatBytes } from "@/lib/file-size";
import { useEntityDocuments } from "@/hooks/use-entity-documents";
import type { EntityDocument } from "@/hooks/use-entity-documents";
import { fetchDocumentCategories } from "@/features/documents/api";
import type { DocumentCategory } from "@/features/documents/types";
import { BaseDropzone } from "@/components/shared/base-dropzone";
import { DocumentFileItem } from "@/components/shared/document-file-item";
import { FileThumbnail } from "@/components/ui/file-thumbnail";
import { ConfirmDeleteButton } from "@/components/ui/confirm-delete-button";
import { useDocumentValidation } from "@/hooks/use-document-validation";
import { getFileIcon } from "@/lib/file-icon";
import { getFileColorClasses } from "@/lib/file-colors";

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
    recordKey?: string;
    categoryType?: string;
    aspectRatio?: number;
    description?: string;
    className?: string;
    /** Whether to show the delete action button. Defaults to true. */
    actionsEnabled?: boolean;
    /** Where the delete action button is rendered. Defaults to "overlay". */
    actionsPlacement?: FileUploadActionsPlacement;
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
    recordKey,
    categoryType = "personnel",
    aspectRatio,
    description,
    className,
    actionsEnabled = true,
    actionsPlacement = "column",
    onUploadComplete,
}: FileUploadFieldProps) {
    const queryClient = useQueryClient();

    const { getDocumentsBySlug } = useEntityDocuments(entity, uuid);
    const categoryDocs = getDocumentsBySlug(categorySlug, recordKey).filter(
        (d) => (notes !== undefined ? (d.notes ?? "") === notes : true),
    );

    const { data: categories } = useQuery({
        queryKey: documentKeys.categories(categoryType),
        queryFn: async () => {
            const { data } = await fetchDocumentCategories(categoryType);
            return data.data;
        },
    });

    const { id: categoryId, req: requirement } = React.useMemo(() => {
        function find(cats: DocumentCategory[]): {
            id: number | undefined;
            req: DocumentCategory["requirement"];
        } {
            for (const cat of cats) {
                if (cat.slug === categorySlug) {
                    return { id: cat.id, req: cat.requirement ?? null };
                }
                if (cat.children) {
                    const found = find(cat.children);
                    if (found.id !== undefined) return found;
                }
            }
            return { id: undefined, req: null };
        }
        return categories ? find(categories) : { id: undefined, req: null };
    }, [categories, categorySlug]);

    const { validateFile } = useDocumentValidation(requirement);

    const effectiveMaxFiles = requirement?.max_files ?? maxFiles;
    const effectiveAccept = requirement?.mime_types?.join(",") ?? accept;

    const uploadMutation = useMutation({
        mutationFn: (file: File) => {
            const formData = new FormData();
            formData.append("document_category_id", String(categoryId));
            formData.append("file", file);
            if (recordKey) {
                formData.append("record_key", recordKey);
            } else if (notes) {
                formData.append("notes", notes);
            }
            return publicApi
                .post<{
                    data: EntityDocument;
                }>(`/${entity}/${uuid}/documents`, formData, {
                    headers: { "Content-Type": "multipart/form-data" },
                    grant: {
                        entity,
                        uuid,
                        purpose: "edit",
                    },
                })
                .then((r) => r.data.data);
        },
        onSuccess: (doc) => {
            queryClient.invalidateQueries({
                queryKey: [`${entity}-documents`, uuid],
            });
            onUploadComplete?.(doc);
            toast.success("فایل با موفقیت آپلود شد");
        },
        onError: (e) => {
            toast.error(getApiError(e) ?? "خطا در بارگذاری فایل");
        },
    });

    const deleteMutation = useMutation({
        mutationFn: (usageId: number) =>
            publicApi.delete(`/${entity}/${uuid}/documents/${usageId}`, {
                grant: { entity, uuid, purpose: "edit" },
            }),
        onSuccess: () => {
            queryClient.invalidateQueries({
                queryKey: [`${entity}-documents`, uuid],
            });
            toast.success("فایل حذف شد");
        },
        onError: () => toast.error("خطا در حذف فایل"),
    });

    const handleFiles = React.useCallback(
        async (fileList: File[]) => {
            for (const file of fileList) {
                const errors = await validateFile(file);

                if (errors.length > 0) {
                    toast.error(errors[0]);
                    continue;
                }
                uploadMutation.mutate(file);
            }
        },
        [uploadMutation, validateFile],
    );

    const handleDelete = React.useCallback(
        (usageId: number) => {
            deleteMutation.mutate(usageId);
        },
        [deleteMutation],
    );

    const canUpload = multiple
        ? categoryDocs.length < effectiveMaxFiles
        : categoryDocs.length === 0;
    const currentDoc = categoryDocs[0] ?? null;
    const isUploading = uploadMutation.isPending;
    const isDeleting = deleteMutation.isPending;
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
                onConfirm={() => handleDelete(doc.usage_id)}
                isPending={isDeleting}
                stopPropagation
            />
        ) : null;

    // ── Variant: avatar (photo with configurable aspect ratio) ──
    if (variant === "avatar") {
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
                                            name: currentDoc.original_name,
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
                                            {currentDoc.original_name}
                                        </span>
                                    </div>
                                )}
                                {actionsEnabled &&
                                    actionsPlacement === "overlay" && (
                                        <div className="absolute inset-0 flex items-center justify-center gap-1 rounded-xl bg-black/50 opacity-0 transition-opacity group-hover:opacity-100">
                                            {renderDelete(currentDoc)}
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
                            {renderDelete(currentDoc)}
                        </div>
                    )}
                {description && (
                    <p className="max-w-45 text-center text-xs text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
        );
    }

    // ── Variant: thumbnail (square preview) ──
    if (variant === "thumbnail") {
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
                                            name: currentDoc.original_name,
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
                                            {currentDoc.original_name}
                                        </span>
                                    </div>
                                )}
                                {actionsEnabled &&
                                    actionsPlacement === "overlay" && (
                                        <div className="absolute inset-0 flex items-center justify-center bg-black/50 opacity-0 transition-opacity group-hover:opacity-100">
                                            {renderDelete(currentDoc)}
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
                            {renderDelete(currentDoc)}
                        </div>
                    )}
                {description && (
                    <p className="text-xs text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
        );
    }

    // ── Variant: card (grid of previews + add tile) ──
    if (variant === "card") {
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
                                                    name: doc.original_name,
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
                                            {doc.original_name}
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
                            doc={doc}
                            subtitle={formatBytes(doc.size)}
                            actionsEnabled={actionsEnabled}
                            className="border-b px-3 py-2 last:border-b-0"
                        />
                    ))}
                </div>
            )}
        </div>
    );
}
