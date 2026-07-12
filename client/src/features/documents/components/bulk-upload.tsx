import * as React from "react";
import { useForm } from "@tanstack/react-form";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { z } from "zod";
import {
    IconFileUpload,
    IconLoader2,
    IconX,
} from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Field, FieldError, FieldLabel } from "@/components/ui/field";
import { Progress } from "@/components/ui/progress";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { FileUpload } from "@/components/ui/file-upload";
import {
    fetchDocumentCategories,
    bulkUploadDocuments,
} from "@/features/documents/api";
import { isAxiosError } from "axios";
import { getApiError } from "@/lib/error-utils";

function formatFileSize(bytes: number): string {
    if (bytes >= 1048576) return `${(bytes / 1048576).toFixed(1)} MB`;
    if (bytes >= 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${bytes} B`;
}

const bulkSchema = z.object({
    document_category_id: z.string().min(1, "دسته‌بندی را انتخاب کنید"),
    notes: z.string().max(1000, "حداکثر ۱۰۰۰ کاراکتر").optional(),
});

const MAX_FILES = 20;

const bulkFileSchema = z
    .instanceof(File)
    .refine(
        (f) => f.size <= 50 * 1024 * 1024,
        "حداکثر اندازه ۵۰ مگابایت",
    )
    .refine(
        (f) =>
            [
                "pdf", "jpg", "jpeg", "png", "gif", "webp",
                "doc", "docx", "xls", "xlsx", "ppt", "pptx", "txt", "csv",
            ].includes(f.name.split(".").pop()?.toLowerCase() ?? ""),
        "فرمت پشتیبانی نمی‌شود",
    );

function validateFile(file: File): string | null {
    const result = bulkFileSchema.safeParse(file);
    return result.success ? null : `${file.name}: ${result.error.errors[0].message}`;
}

type BulkFormValues = z.infer<typeof bulkSchema>;

type FileItem = {
    file: File;
    progress: number;
    status: "pending" | "uploading" | "success" | "error";
    error?: string;
};

type BulkUploadProps = {
    employeeId: number;
    onSuccess?: () => void;
};

export function BulkUpload({ employeeId, onSuccess }: BulkUploadProps) {
    const queryClient = useQueryClient();
    const [files, setFiles] = React.useState<FileItem[]>([]);
    const [overallProgress, setOverallProgress] = React.useState(0);
    const [validationError, setValidationError] = React.useState<string | null>(
        null,
    );
    const [serverError, setServerError] = React.useState<string | null>(null);

    const { data: categories } = useQuery({
        queryKey: ["document-categories", "employee"],
        queryFn: async () => {
            const { data } = await fetchDocumentCategories("employee");
            return data.data;
        },
    });

    const uploadMutation = useMutation({
        mutationFn: async (values: BulkFormValues): Promise<{
            data: {
                uploaded: { original_name: string }[];
                failed: { name: string; error: string }[];
                skipped: { name: string; reason: string }[];
            };
        }> => {
            const formData = new FormData();
            formData.append("document_category_id", values.document_category_id);
            if (values.notes) formData.append("notes", values.notes);

            files.forEach((item) => {
                formData.append("files[]", item.file);
            });

            setFiles((prev) =>
                prev.map((item) => ({ ...item, status: "uploading" as const })),
            );

            const response = await bulkUploadDocuments(
                employeeId,
                formData,
                (progressEvent) => {
                    const percent = progressEvent.total
                        ? Math.round((progressEvent.loaded * 100) / progressEvent.total)
                        : 0;
                    setOverallProgress(percent);
                    setFiles((prev) =>
                        prev.map((item) =>
                            item.status === "uploading"
                                ? { ...item, progress: percent }
                                : item,
                        ),
                    );
                },
            );

            return response.data;
        },
        onSuccess: (data) => {
            queryClient.invalidateQueries({
                queryKey: ["employee-documents", employeeId],
            });

            const uploadedNames = new Set(data.data.uploaded.map((d: { original_name: string }) => d.original_name));
            const skippedNames = new Set(data.data.skipped.map((d: { name: string }) => d.name));
            const failedFiles = data.data.failed as { name: string; error: string }[];

            setFiles((prev) =>
                prev.map((item) => {
                    if (uploadedNames.has(item.file.name)) {
                        return { ...item, status: "success", progress: 100 };
                    }
                    if (skippedNames.has(item.file.name)) {
                        return { ...item, status: "error", error: "تکراری" };
                    }
                    const failed = failedFiles.find((f) => f.name === item.file.name);
                    if (failed) {
                        return { ...item, status: "error", error: failed.error };
                    }
                    return item;
                }),
            );

            setOverallProgress(0);

            const failedCount = failedFiles.length;
            const skippedCount = data.data.skipped.length;
            const uploadedCount = uploadedNames.size;

            if (uploadedCount === 0 && (failedCount > 0 || skippedCount > 0)) {
                const parts: string[] = [];
                if (failedCount > 0) parts.push(`${failedCount} فایل ناموفق`);
                if (skippedCount > 0) parts.push(`${skippedCount} فایل تکراری`);
                setServerError(parts.join(" و "));
            } else if (uploadedCount > 0) {
                setServerError(null);
                setTimeout(() => {
                    setFiles([]);
                    form.reset();
                    onSuccess?.();
                }, 1500);
            }
        },
        onError: (error: unknown) => {
            let perFileErrors: Record<number, string> = {};

            if (isAxiosError(error) && error.response?.data) {
                const data = error.response.data as Record<string, unknown>;
                if (data.errors && typeof data.errors === "object") {
                    const errors = data.errors as Record<string, string[]>;
                    for (const [key, msgs] of Object.entries(errors)) {
                        const match = key.match(/^files\.(\d+)$/);
                        if (match && Array.isArray(msgs) && msgs.length > 0) {
                            perFileErrors[parseInt(match[1], 10)] = msgs[0].replace(/files\.\d+/g, "فایل");
                        }
                    }
                }
            }

            setFiles((prev) =>
                prev.map((item, index) => {
                    if (item.status !== "uploading") return item;
                    if (perFileErrors[index]) {
                        return { ...item, status: "error", error: perFileErrors[index] };
                    }
                    return { ...item, status: "error", error: "خطا در آپلود" };
                }),
            );
            setOverallProgress(0);
            setServerError(getApiError(error));
        },
    });

    const form = useForm({
        defaultValues: {
            document_category_id: "",
            notes: "",
        } as BulkFormValues,
        onSubmit: async ({ value }) => {
            setValidationError(null);
            setServerError(null);

            if (!value.document_category_id) {
                setValidationError("دسته‌بندی را انتخاب کنید");
                return;
            }

            if (files.length === 0) {
                setValidationError("فایلی انتخاب نکرده‌اید");
                return;
            }

            uploadMutation.mutate(value);
        },
    });

    function handleFilesAccepted(newFiles: File[]) {
        setValidationError(null);
        setServerError(null);

        const totalAfter = files.length + newFiles.length;
        if (totalAfter > MAX_FILES) {
            setValidationError(`حداکثر ${MAX_FILES} فایل مجاز است`);
            return;
        }

        const rejected = newFiles.filter((f) => validateFile(f) !== null);
        if (rejected.length > 0) {
            setValidationError(rejected.map((f) => validateFile(f)).join("، "));
            return;
        }

        setFiles((prev) => [
            ...prev,
            ...newFiles.map((file) => ({
                file,
                progress: 0,
                status: "pending" as const,
            })),
        ]);
    }

    function removeFile(index: number) {
        setFiles((prev) => prev.filter((_, i) => i !== index));
    }

    const isUploading = uploadMutation.isPending;

    return (
        <div className="space-y-4">
            <FileUpload
                multiple={true}
                showFileList={false}
                accept=".pdf,.doc,.docx,.xlsx,.xls,.csv,.png,.jpg,.jpeg"
                onFilesAccepted={handleFilesAccepted}
            />

            {files.length > 0 && (
                <div className="space-y-2">
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">
                            {files.length} فایل انتخاب شده
                        </span>
                        {!isUploading && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => setFiles([])}
                            >
                                پاک کردن همه
                            </Button>
                        )}
                    </div>

                    <div className="max-h-40 space-y-1 overflow-y-auto">
                        {files.map((item, index) => (
                            <div
                                key={index}
                                className="flex items-center gap-2 rounded-md border p-2"
                            >
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm">
                                        {item.file.name}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {formatFileSize(item.file.size)}
                                    </p>
                                </div>
                                {item.status === "uploading" && (
                                    <Progress
                                        value={item.progress}
                                        className="h-1.5 w-20"
                                    />
                                )}
                                {item.status === "success" && (
                                    <span className="text-xs text-emerald-500">
                                        ✓
                                    </span>
                                )}
                                {item.status === "error" && (
                                    <span className="text-xs text-destructive">
                                        {item.error}
                                    </span>
                                )}
                                {!isUploading && (
                                    <Button
                                        variant="ghost"
                                        size="icon-xs"
                                        onClick={() => removeFile(index)}
                                    >
                                        <IconX className="size-3" />
                                    </Button>
                                )}
                            </div>
                        ))}
                    </div>

                    {isUploading && (
                        <div className="space-y-1">
                            <div className="flex justify-between text-xs text-muted-foreground">
                                <span>در حال آپلود...</span>
                                <span>{overallProgress}%</span>
                            </div>
                            <Progress
                                value={overallProgress}
                                className="h-1.5"
                            />
                        </div>
                    )}
                </div>
            )}

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    form.handleSubmit();
                }}
                className="space-y-4"
            >
                <form.Field name="document_category_id">
                    {(field) => (
                        <Field>
                            <FieldLabel htmlFor={field.name}>
                                دسته‌بندی
                            </FieldLabel>
                            <Select
                                value={field.state.value || null}
                                onValueChange={(val) =>
                                    field.handleChange(val ?? "")
                                }
                                disabled={isUploading}
                            >
                                <SelectTrigger id={field.name}>
                                    <SelectValue placeholder="انتخاب دسته‌بندی">
                                        {categories?.find(
                                            (c) =>
                                                String(c.id) ===
                                                field.state.value,
                                        )?.name}
                                    </SelectValue>
                                </SelectTrigger>
                                <SelectContent>
                                    {categories?.map((cat) => (
                                        <SelectItem
                                            key={cat.id}
                                            value={String(cat.id)}
                                        >
                                            {cat.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Field>
                    )}
                </form.Field>

                <form.Field name="notes">
                    {(field) => (
                        <Field>
                            <FieldLabel htmlFor={field.name}>
                                توضیحات:
                            </FieldLabel>
                            <Input
                                id={field.name}
                                name={field.name}
                                value={field.state.value}
                                onBlur={field.handleBlur}
                                onChange={(e) =>
                                    field.handleChange(e.target.value)
                                }
                                placeholder="توضیح کوتاه درباره مدارک..."
                                disabled={isUploading}
                            />
                        </Field>
                    )}
                </form.Field>
            </form>

            {validationError && (
                <p className="text-sm text-destructive">{validationError}</p>
            )}

            {serverError && (
                <p className="text-sm text-destructive">{serverError}</p>
            )}

            <Button
                type="button"
                disabled={files.length === 0 || isUploading}
                onClick={() => {
                    setValidationError(null);
                    setServerError(null);
                    form.handleSubmit();
                }}
            >
                {isUploading ? (
                    <IconLoader2 className="size-4 animate-spin" />
                ) : (
                    <IconFileUpload className="size-4" />
                )}
                آپلود {files.length > 0 && `(${files.length})`}
            </Button>
        </div>
    );
}
