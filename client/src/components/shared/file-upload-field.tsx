"use client";

import * as React from "react";
import { IconLoader2, IconUpload } from "@tabler/icons-react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { cn } from "@/lib/utils";
import { api } from "@/lib/api";
import { ConfirmDeleteButton } from "@/components/ui/confirm-delete-button";
import { DocumentThumbnail } from "@/components/shared/document-thumbnail";
import { documentKeys } from "@/lib/query-keys";
import { useQuestionnaireDocuments } from "@/features/recruitment/hooks/use-questionnaire-documents";
import { fetchDocumentCategories } from "@/features/documents/api";
import type { Document, DocumentCategory } from "@/features/documents/types";
import { getDocOriginalName, getDocMimeType, getDocFileSizeFormatted, getDocServeUrl, getDocDownloadUrl } from "@/features/documents/types";

type FileUploadFieldProps = {
    uuid: string;
    categorySlug: string;
    label: string;
    accept?: string;
    multiple?: boolean;
    maxFiles?: number;
    notes?: string;
    recordKey?: string;
    categoryType?: string;
    aspectRatio?: number;
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
    categorySlug,
    label,
    accept = DEFAULT_ACCEPT,
    multiple = false,
    maxFiles = 1,
    notes,
    recordKey,
    categoryType = "personnel",
    aspectRatio,
    className,
    onUploadComplete,
}: FileUploadFieldProps) {
    const queryClient = useQueryClient();
    const inputRef = React.useRef<HTMLInputElement>(null);
    const [isDragging, setIsDragging] = React.useState(false);
    const dragDepthRef = React.useRef(0);

    const { getDocumentsBySlug } = useQuestionnaireDocuments(uuid);
    const categoryDocs = getDocumentsBySlug(categorySlug, recordKey);

    const { data: categories } = useQuery({
        queryKey: documentKeys.categories(categoryType),
        queryFn: async () => {
            const { data } = await fetchDocumentCategories(categoryType);
            return data.data;
        },
    });

    const categoryId = React.useMemo(() => {
        function findCategoryId(cats: DocumentCategory[]): number | undefined {
            for (const cat of cats) {
                if (cat.slug === categorySlug) return cat.id;
                if (cat.children) {
                    const found = findCategoryId(cat.children);
                    if (found !== undefined) return found;
                }
            }
            return undefined;
        }
        return categories ? findCategoryId(categories) : undefined;
    }, [categories, categorySlug]);

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
                            <DocumentThumbnail
                                document={doc}
                                variant="compact"
                                size="sm"
                                aspectRatio={aspectRatio}
                                showName
                                showSize
                            />
                            <div className="flex-1" />
                            <ConfirmDeleteButton
                                iconOnly
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
