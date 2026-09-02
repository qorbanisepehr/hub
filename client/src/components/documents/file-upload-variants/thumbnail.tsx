"use client";

import * as React from "react";
import { IconEye, IconLoader2, IconUpload } from "@tabler/icons-react";

import { BaseDropzone } from "@/components/shared/base-dropzone";
import { FileThumbnail } from "@/components/ui/file-thumbnail";
import { DocumentPreviewLightbox } from "@/features/documents/components/document-preview-lightbox";
import { toLightboxDocument } from "@/components/documents";
import { cn } from "@/lib/utils";
import { getFileIcon, getFileColorClasses } from "@/lib/file-utils";
import type { FileUploadVariantProps } from "../file-upload-field";

export function ThumbnailVariant({
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
    required,
}: FileUploadVariantProps & { containerClass: string }) {
    const [previewOpen, setPreviewOpen] = React.useState(false);

    return (
        <div className={cn("space-y-1.5", className)}>
            {label && (
                <span className="text-sm font-medium">
                    {label}
                    {required && (
                        <span className="text-destructive me-0.5">*</span>
                    )}
                </span>
            )}
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
            {currentDoc && actionsEnabled && actionsPlacement !== "overlay" && (
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
                <p className="text-xs text-muted-foreground">{description}</p>
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