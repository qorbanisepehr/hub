"use client";

import * as React from "react";
import { IconLoader2, IconUpload, IconX, IconTrash } from "@tabler/icons-react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { cn } from "@/lib/utils";
import { api } from "@/lib/api";
import { getFileIcon } from "@/lib/file-icon";
import { getFileColorClasses } from "@/lib/file-colors";
import { FileThumbnail } from "@/components/ui/file-thumbnail";
import { Button } from "@/components/ui/button";
import { ConfirmAction } from "@/components/shared/confirm-action";
import { useQuestionnaireDocuments } from "@/features/recruitment/hooks/use-questionnaire-documents";
import type { Document } from "@/features/documents/types";

type FileUploadFieldProps = {
    uuid: string;
    categoryId: number;
    label: string;
    accept?: string;
    multiple?: boolean;
    maxFiles?: number;
    notes?: string;
    recordKey?: string;
    className?: string;
    onUploadComplete?: (doc: Document) => void;
};

function formatBytes(bytes: number) {
    if (bytes === 0) return "0 بایت";
    const units = ["بایت", "کیلوبایت", "مگابایت", "گیگابایت"];
    const index = Math.min(
        Math.floor(Math.log(bytes) / Math.log(1024)),
        units.length - 1,
    );
    return `${(bytes / 1024 ** index).toFixed(index === 0 ? 0 : 1)} ${units[index]}`;
}

const DEFAULT_ACCEPT = [
    ".pdf", ".jpg", ".jpeg", ".png", ".webp",
    "application/pdf", "image/jpeg", "image/png", "image/webp",
].join(",");

export function FileUploadField({
    uuid,
    categoryId,
    label,
    accept = DEFAULT_ACCEPT,
    multiple = false,
    maxFiles = 1,
    notes,
    recordKey,
    className,
    onUploadComplete,
}: FileUploadFieldProps) {
    const queryClient = useQueryClient();
    const inputRef = React.useRef<HTMLInputElement>(null);
    const [isDragging, setIsDragging] = React.useState(false);
    const dragDepthRef = React.useRef(0);

    const { getDocuments } = useQuestionnaireDocuments(uuid);
    const categoryDocs = getDocuments(categoryId, recordKey);

    const uploadMutation = useMutation({
        mutationFn: (file: File) => {
            const formData = new FormData();
            formData.append("document_category_id", String(categoryId));
            formData.append("file", file);
            if (recordKey) {
                formData.append("meta", JSON.stringify({ recordKey }));
            } else if (notes) {
                formData.append("notes", notes);
            }
            return api
                .post<{ data: Document }>(`/questionnaire/${uuid}/documents`, formData, {
                    headers: { "Content-Type": "multipart/form-data" },
                })
                .then((r) => r.data.data);
        },
        onSuccess: (doc) => {
            queryClient.invalidateQueries({ queryKey: ["questionnaire-documents", uuid] });
            onUploadComplete?.(doc);
        },
        onError: () => {
            toast.error("خطا در بارگذاری فایل");
        },
    });

    const deleteMutation = useMutation({
        mutationFn: (docId: number) =>
            api.delete(`/questionnaire/${uuid}/documents/${docId}`),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ["questionnaire-documents", uuid] });
            toast.success("فایل حذف شد");
        },
        onError: () => {
            toast.error("خطا در حذف فایل");
        },
    });

    const handleFiles = React.useCallback(
        (fileList: FileList | File[]) => {
            const files = Array.from(fileList);
            for (const file of files) {
                uploadMutation.mutate(file);
            }
        },
        [uploadMutation],
    );

    const canUpload = multiple ? categoryDocs.length < maxFiles : categoryDocs.length === 0;

    return (
        <div className={cn("space-y-2", className)}>
            <span className="text-sm font-medium">{label}</span>

            {canUpload && (
                <div
                    role="button"
                    tabIndex={0}
                    className={cn(
                        "relative flex min-h-24 cursor-pointer flex-col items-center justify-center gap-2 overflow-hidden rounded border border-dashed bg-background px-4 py-4 text-center transition-[border-color,background-color] duration-200 ease-out",
                        isDragging
                            ? "border-foreground/40 bg-accent/35"
                            : "border-foreground/20 hover:border-foreground/35 hover:bg-muted/35",
                    )}
                    onClick={() => inputRef.current?.click()}
                    onDragEnter={(e) => {
                        e.preventDefault();
                        dragDepthRef.current += 1;
                        setIsDragging(true);
                    }}
                    onDragLeave={(e) => {
                        e.preventDefault();
                        dragDepthRef.current = Math.max(0, dragDepthRef.current - 1);
                        if (dragDepthRef.current === 0) setIsDragging(false);
                    }}
                    onDragOver={(e) => e.preventDefault()}
                    onDrop={(e) => {
                        e.preventDefault();
                        dragDepthRef.current = 0;
                        setIsDragging(false);
                        if (e.dataTransfer.files.length > 0) {
                            handleFiles(e.dataTransfer.files);
                        }
                    }}
                >
                    {uploadMutation.isPending ? (
                        <IconLoader2 className="size-5 animate-spin text-muted-foreground" />
                    ) : (
                        <IconUpload className="size-5 text-muted-foreground" />
                    )}
                    <span className="text-xs text-muted-foreground">
                        {isDragging ? "رها کنید" : "انتخاب فایل"}
                    </span>
                    <input
                        ref={inputRef}
                        type="file"
                        accept={accept}
                        multiple={multiple}
                        className="hidden"
                        onChange={(e) => {
                            if (e.target.files) {
                                handleFiles(e.target.files);
                                e.currentTarget.value = "";
                            }
                        }}
                    />
                </div>
            )}

            {categoryDocs.length > 0 && (
                <div className="rounded-lg border bg-background">
                    {categoryDocs.map((doc) => (
                        <div
                            key={doc.id}
                            className="flex items-center gap-3 border-b px-3 py-2 last:border-b-0"
                        >
                            {doc.mime_type.startsWith("image/") && doc.thumbnail_url ? (
                                <FileThumbnail
                                    file={{ name: doc.original_name, type: doc.mime_type }}
                                    previewImageUrl={doc.thumbnail_url}
                                    className="size-8 shrink-0 rounded"
                                />
                            ) : (
                                <div
                                    className={cn(
                                        "flex size-8 shrink-0 items-center justify-center rounded border",
                                        getFileColorClasses(doc.mime_type),
                                    )}
                                >
                                    {getFileIcon(doc.mime_type, "size-4 stroke-[1.5]")}
                                </div>
                            )}
                            <div className="min-w-0 flex-1">
                                <div className="truncate text-xs font-medium">
                                    {doc.original_name}
                                </div>
                                <div className="truncate text-[10px] text-muted-foreground">
                                    {doc.file_size_formatted}
                                </div>
                            </div>
                            <ConfirmAction
                                trigger={
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        className="size-7 p-0 text-muted-foreground hover:text-destructive"
                                        disabled={deleteMutation.isPending}
                                    >
                                        <IconTrash className="size-3.5" />
                                    </Button>
                                }
                                isPending={deleteMutation.isPending}
                                onConfirm={() => deleteMutation.mutate(doc.id)}
                            />
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
