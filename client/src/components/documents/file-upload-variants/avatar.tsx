"use client";

import * as React from "react";
import {
    IconCamera,
    IconEye,
    IconFile,
    IconLoader2,
} from "@tabler/icons-react";

import { BaseDropzone } from "@/components/shared/base-dropzone";
import { Button } from "@/components/ui/button";
import { FileThumbnail } from "@/components/ui/file-thumbnail";
import { DocumentPreviewLightbox } from "@/features/documents/components/document-preview-lightbox";
import { toLightboxDocument } from "@/components/documents";
import { cn } from "@/lib/utils";
import type { FileUploadVariantProps } from "../file-upload-field";

export function AvatarVariant({
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
        <div className={cn("flex flex-col items-center gap-2", className)}>
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
                                <div className="absolute inset-0 flex items-center justify-center gap-1 rounded-xl bg-primary/50 opacity-0 transition-opacity group-hover:opacity-100">
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        onClick={(e) => {
                                            e.stopPropagation();
                                            setPreviewOpen(true);
                                        }}
                                        className="p-1.5 text-primary-foreground"
                                        aria-label="پیش‌نمایش"
                                    >
                                        <IconEye className="size-4" />
                                    </Button>
                                    {actionsPlacement === "overlay" &&
                                        renderDelete(currentDoc, true)}
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
            {currentDoc && actionsEnabled && actionsPlacement !== "overlay" && (
                <div
                    className={cn(
                        "flex",
                        actionsPlacement === "column"
                            ? "flex-col items-center"
                            : "flex-row items-center gap-1",
                    )}
                >
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        onClick={() => setPreviewOpen(true)}
                        className="p-1"
                        aria-label="پیش‌نمایش"
                    >
                        <IconEye className="size-4" />
                    </Button>
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