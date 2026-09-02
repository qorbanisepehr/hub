"use client";

import { IconLoader2, IconUpload } from "@tabler/icons-react";

import { BaseDropzone } from "@/components/shared/base-dropzone";
import { DocumentFileItem } from "@/components/documents";
import { cn } from "@/lib/utils";
import { formatBytes } from "@/lib/file-utils";
import type { FileUploadVariantProps } from "../file-upload-field";

export function DefaultVariant({
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