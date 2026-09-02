"use client";

import { IconLoader2, IconUpload } from "@tabler/icons-react";

import { BaseDropzone } from "@/components/shared/base-dropzone";
import { FileThumbnail } from "@/components/ui/file-thumbnail";
import { cn } from "@/lib/utils";
import { getFileIcon, getFileColorClasses, formatBytes } from "@/lib/file-utils";
import type { FileUploadVariantProps } from "../file-upload-field";

export function CardVariant({
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
    required,
}: FileUploadVariantProps) {
    return (
        <div className={cn("space-y-2", className)}>
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
                <p className="text-xs text-muted-foreground">{description}</p>
            )}
        </div>
    );
}