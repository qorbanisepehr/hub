import * as React from "react";
import { useForm } from "@tanstack/react-form";
import { useStore } from "@tanstack/react-form";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
    IconFile,
    IconFileText,
    IconFileUpload,
    IconFileZip,
    IconFolder,
    IconLoader2,
    IconSelector,
    IconX,
} from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Field, FieldLabel } from "@/components/ui/field";
import { Progress } from "@/components/ui/progress";
import {
    Command,
    CommandEmpty,
    CommandInput,
    CommandItem,
    CommandList,
} from "@/components/ui/command";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { FileUpload } from "@/components/ui/file-upload";
import {
    fetchDocumentCategories,
    uploadDocument,
    bulkUploadDocuments,
    zipUploadDocuments,
} from "@/features/documents/api";
import { isAxiosError } from "axios";
import { getApiError } from "@/lib/error-utils";
import { employeeKeys } from "@/lib/query-keys";
import { FILE_UPLOAD } from "@/lib/constants";
import { cn } from "@/lib/utils";

type FileItem = {
    file: File;
    progress: number;
    status: "pending" | "uploading" | "success" | "error";
    error?: string;
};

type DocumentUploadFormProps = {
    employeeId: number;
    onSuccess?: () => void;
    onDirtyChange?: (isDirty: boolean) => void;
};

function isZipFile(file: File): boolean {
    return file.name.split(".").pop()?.toLowerCase() === "zip";
}

function validateDocumentFile(file: File): string | null {
    if (file.size > FILE_UPLOAD.MAX_SIZE) return `${file.name}: حداکثر اندازه ۵۰ مگابایت`;
    const ext = file.name.split(".").pop()?.toLowerCase() ?? "";
    if (["pdf","jpg","jpeg","png","gif","webp","doc","docx","xls","xlsx","ppt","pptx","txt","csv"].includes(ext)) return null;
    return `${file.name}: فرمت پشتیبانی نمی‌شود`;
}

function validateZipFile(file: File): string | null {
    if (file.size > 100 * 1024 * 1024) return `${file.name}: حداکثر اندازه ۱۰۰ مگابایت`;
    return null;
}

function formatFileSize(bytes: number): string {
    if (bytes >= 1048576) return `${(bytes / 1048576).toFixed(1)} MB`;
    if (bytes >= 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${bytes} B`;
}

function detectMode(files: File[]): "single" | "bulk" | "zip" | null {
    if (files.length === 0) return null;
    if (files.length === 1 && isZipFile(files[0])) return "zip";
    if (files.every((f) => !isZipFile(f))) return files.length === 1 ? "single" : "bulk";
    return null;
}

type FlatCategory = { id: number; name: string; depth: number; path: string };

function flattenCategories(
    cats: { id: number; name: string; children?: { id: number; name: string; children?: { id: number; name: string }[] }[] }[],
    depth = 0,
    parentPath = "",
): FlatCategory[] {
    const result: FlatCategory[] = [];
    for (const cat of cats) {
        const path = parentPath ? `${parentPath} > ${cat.name}` : cat.name;
        result.push({ id: cat.id, name: cat.name, depth, path });
        if (cat.children && cat.children.length > 0) {
            result.push(...flattenCategories(cat.children, depth + 1, path));
        }
    }
    return result;
}

export function DocumentUploadForm({
    employeeId,
    onSuccess,
    onDirtyChange,
}: DocumentUploadFormProps) {
    const queryClient = useQueryClient();
    const [files, setFiles] = React.useState<FileItem[]>([]);
    const [uploadKey, setUploadKey] = React.useState(0);
    const [overallProgress, setOverallProgress] = React.useState(0);
    const [validationError, setValidationError] = React.useState<string | null>(null);
    const [serverError, setServerError] = React.useState<string | null>(null);
    const [categoryOpen, setCategoryOpen] = React.useState(false);

    const rawFiles = files.map((f) => f.file);
    const mode = detectMode(rawFiles);

    const { data: categories } = useQuery({
        queryKey: employeeKeys.documentCategories("employee"),
        queryFn: async () => {
            const { data } = await fetchDocumentCategories("employee");
            return data.data;
        },
    });

    const singleMutation = useMutation({
        mutationFn: (payload: { category: string; notes: string; file: File }) => {
            const formData = new FormData();
            formData.append("document_category_id", payload.category);
            formData.append("file", payload.file);
            if (payload.notes) formData.append("notes", payload.notes);
            return uploadDocument(employeeId, formData);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: employeeKeys.documents(employeeId) });
            resetAll();
            onSuccess?.();
        },
        onError: (error: unknown) => {
            setServerError(getApiError(error));
        },
    });

    const bulkMutation = useMutation({
        mutationFn: async (values: { document_category_id: string; notes?: string }) => {
            const formData = new FormData();
            formData.append("document_category_id", values.document_category_id);
            if (values.notes) formData.append("notes", values.notes);
            files.forEach((item) => formData.append("files[]", item.file));

            setFiles((prev) => prev.map((item) => ({ ...item, status: "uploading" as const })));

            const response = await bulkUploadDocuments(employeeId, formData, (progressEvent) => {
                const percent = progressEvent.total
                    ? Math.round((progressEvent.loaded * 100) / progressEvent.total)
                    : 0;
                setOverallProgress(percent);
                setFiles((prev) =>
                    prev.map((item) =>
                        item.status === "uploading" ? { ...item, progress: percent } : item,
                    ),
                );
            });

            return response.data;
        },
        onSuccess: (data) => {
            queryClient.invalidateQueries({ queryKey: employeeKeys.documents(employeeId) });

            const uploadedNames = new Set(data.data.uploaded.map((d: { original_name: string }) => d.original_name));
            const skippedNames = new Set(data.data.skipped.map((d: { name: string }) => d.name));
            const failedFiles = data.data.failed as { name: string; error: string }[];

            setFiles((prev) =>
                prev.map((item) => {
                    if (uploadedNames.has(item.file.name)) return { ...item, status: "success", progress: 100 };
                    if (skippedNames.has(item.file.name)) return { ...item, status: "error", error: "تکراری" };
                    const failed = failedFiles.find((f) => f.name === item.file.name);
                    if (failed) return { ...item, status: "error", error: failed.error };
                    return item;
                }),
            );

            setOverallProgress(0);

            const uploadedCount = uploadedNames.size;
            const failedCount = failedFiles.length;
            const skippedCount = data.data.skipped.length;

            if (uploadedCount === 0 && (failedCount > 0 || skippedCount > 0)) {
                const parts: string[] = [];
                if (failedCount > 0) parts.push(`${failedCount} فایل ناموفق`);
                if (skippedCount > 0) parts.push(`${skippedCount} فایل تکراری`);
                setServerError(parts.join(" و "));
            } else if (uploadedCount > 0) {
                setServerError(null);
                setTimeout(() => {
                    resetAll();
                    onSuccess?.();
                }, 1500);
            }
        },
        onError: (error: unknown) => {
            let perFileErrors: Record<number, string> = {};
            if (isAxiosError(error) && error.response?.data) {
                const errData = error.response.data as Record<string, unknown>;
                if (errData.errors && typeof errData.errors === "object") {
                    const errors = errData.errors as Record<string, string[]>;
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
                    return { ...item, status: "error", error: perFileErrors[index] ?? "خطا در آپلود" };
                }),
            );
            setOverallProgress(0);
            setServerError(getApiError(error));
        },
    });

    const zipMutation = useMutation({
        mutationFn: async () => {
            if (files.length === 0) throw new Error("No file");
            const formData = new FormData();
            formData.append("file", files[0].file);
            return zipUploadDocuments(employeeId, formData, (progressEvent) => {
                const percent = progressEvent.total
                    ? Math.round((progressEvent.loaded * 100) / progressEvent.total)
                    : 0;
                setOverallProgress(percent);
            });
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: employeeKeys.documents(employeeId) });
            setOverallProgress(0);
            resetAll();
            setTimeout(() => {
                onSuccess?.();
            }, 1500);
        },
        onError: (error: unknown) => {
            setOverallProgress(0);
            setServerError(getApiError(error));
        },
    });

    const form = useForm({
        defaultValues: {
            document_category_id: "",
            notes: "",
        },
        onSubmit: async ({ value }) => {
            setValidationError(null);
            setServerError(null);

            if (files.length === 0) {
                setValidationError("فایلی انتخاب نکرده‌اید");
                return;
            }

            if (mode === "zip") {
                zipMutation.mutate();
                return;
            }

            if (!value.document_category_id) {
                setValidationError("دسته‌بندی را انتخاب کنید");
                return;
            }

            if (mode === "single") {
                singleMutation.mutate({
                    category: value.document_category_id,
                    notes: value.notes ?? "",
                    file: files[0].file,
                });
            } else {
                bulkMutation.mutate(value);
            }
        },
    });

    const formDirty = useStore(form.store, (state) => state.isDirty);
    const hasFiles = files.length > 0;
    const isDirty = formDirty || hasFiles;
    const isUploading = singleMutation.isPending || bulkMutation.isPending || zipMutation.isPending;

    React.useEffect(() => {
        onDirtyChange?.(isDirty);
    }, [isDirty, onDirtyChange]);

    function resetAll() {
        form.reset();
        setFiles([]);
        setUploadKey((k) => k + 1);
        setValidationError(null);
        setServerError(null);
        setOverallProgress(0);
    }

    function handleFilesAccepted(newFiles: File[]) {
        setValidationError(null);
        setServerError(null);

        const hasZip = newFiles.some(isZipFile);
        const hasNonZip = newFiles.some((f) => !isZipFile(f));

        if (hasZip && hasNonZip) {
            setValidationError("فایل فشرده را جداگانه انتخاب کنید");
            return;
        }

        if (hasZip) {
            if (newFiles.length > 1) {
                setValidationError("فقط یک فایل فشرده مجاز است");
                return;
            }
            const error = validateZipFile(newFiles[0]);
            if (error) {
                setValidationError(error);
                return;
            }
            setFiles([{ file: newFiles[0], progress: 0, status: "pending" }]);
            return;
        }

        const totalAfter = files.length + newFiles.length;
        if (totalAfter > FILE_UPLOAD.MAX_FILES_BULK) {
            setValidationError(`حداکثر ${FILE_UPLOAD.MAX_FILES_BULK} فایل مجاز است`);
            return;
        }

        const rejected = newFiles.filter((f) => validateDocumentFile(f) !== null);
        if (rejected.length > 0) {
            setValidationError(rejected.map((f) => validateDocumentFile(f)).join("، "));
            return;
        }

        setFiles((prev) => [
            ...prev,
            ...newFiles.map((file) => ({ file, progress: 0, status: "pending" as const })),
        ]);
    }

    function removeFile(index: number) {
        setFiles((prev) => prev.filter((_, i) => i !== index));
    }

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                e.stopPropagation();
                form.handleSubmit();
            }}
            className="space-y-4"
        >
            <FileUpload
                key={uploadKey}
                multiple
                showFileList={false}
                accept=".pdf,.doc,.docx,.xlsx,.xls,.csv,.png,.jpg,.jpeg,.zip"
                browseLabel="انتخاب فایل‌"
                onFilesAccepted={handleFilesAccepted}
            />

            {files.length > 0 && (
                <div className="space-y-2">
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">
                            {files.length} فایل انتخاب شده
                        </span>
                        {!isUploading && (
                            <Button variant="ghost" size="sm" onClick={() => setFiles([])}>
                                پاک کردن همه
                            </Button>
                        )}
                    </div>

                    <div className="max-h-40 space-y-1 overflow-y-auto">
                        {files.map((item, index) => (
                            <div key={index} className="flex items-center gap-2 rounded-md border p-2">
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm">{item.file.name}</p>
                                    <p className="text-xs text-muted-foreground">
                                        {formatFileSize(item.file.size)}
                                    </p>
                                </div>
                                {item.status === "uploading" && (
                                    <Progress value={item.progress} className="h-1.5 w-20" />
                                )}
                                {item.status === "success" && (
                                    <span className="text-xs text-emerald-500">✓</span>
                                )}
                                {item.status === "error" && (
                                    <span className="text-xs text-destructive">{item.error}</span>
                                )}
                                {!isUploading && (
                                    <Button variant="ghost" size="icon-xs" onClick={() => removeFile(index)}>
                                        <IconX className="size-3" />
                                    </Button>
                                )}
                            </div>
                        ))}
                    </div>

                    {isUploading && (bulkMutation.isPending || zipMutation.isPending) && (
                        <div className="space-y-1">
                            <div className="flex justify-between text-xs text-muted-foreground">
                                <span>{mode === "zip" ? "در حال پردازش..." : "در حال آپلود..."}</span>
                                <span>{overallProgress}%</span>
                            </div>
                            <Progress value={overallProgress} className="h-1.5" />
                        </div>
                    )}
                </div>
            )}

            {mode !== "zip" && (
                <>
                    <form.Field name="document_category_id">
                        {(field) => {
                            const flat = flattenCategories(categories ?? []);
                            const selected = flat.find((c) => String(c.id) === field.state.value);

                            return (
                                <Field>
                                    <FieldLabel htmlFor={field.name}>دسته‌بندی</FieldLabel>
                                    <Popover open={categoryOpen} onOpenChange={setCategoryOpen}>
                                        <PopoverTrigger
                                            render={
                                                <button
                                                    id={field.name}
                                                    type="button"
                                                    role="combobox"
                                                    aria-expanded={categoryOpen}
                                                    disabled={isUploading}
                                                    className={cn(
                                                        "border-input flex h-9 w-full items-center justify-between rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs",
                                                        "placeholder:text-muted-foreground",
                                                        "focus:border-ring focus:outline-none focus:ring-1 focus:ring-ring",
                                                        "disabled:cursor-not-allowed disabled:opacity-50",
                                                        "[&>span]:line-clamp-1",
                                                    )}
                                                />
                                            }
                                        >
                                            <span className={selected ? "" : "text-muted-foreground"}>
                                                {selected?.path ?? "انتخاب دسته‌بندی"}
                                            </span>
                                            <IconSelector className="size-4 shrink-0 opacity-50" />
                                        </PopoverTrigger>
                                        <PopoverContent className="w-[--radix-popover-trigger-width] p-0" align="start">
                                            <Command>
                                                <CommandInput placeholder="جستجو..." />
                                                <CommandList>
                                                    <CommandEmpty>یافت نشد</CommandEmpty>
                                                    {flat.map((item) => (
                                                        <CommandItem
                                                            key={item.id}
                                                            value={item.name}
                                                            onSelect={() => {
                                                                field.handleChange(String(item.id));
                                                                setCategoryOpen(false);
                                                            }}
                                                            style={{ paddingInlineStart: `${8 + item.depth * 16}px` }}
                                                        >
                                                            {item.depth === 0 ? (
                                                                <span className="text-muted-foreground">
                                                                    <IconFolder className="size-4" />
                                                                </span>
                                                            ) : item.depth === 1 ? (
                                                                <span className="text-muted-foreground">
                                                                    <IconFile className="size-4" />
                                                                </span>
                                                            ) : (
                                                                <span className="text-muted-foreground">
                                                                    <IconFileText className="size-4" />
                                                                </span>
                                                            )}
                                                            <span>{item.name}</span>
                                                        </CommandItem>
                                                    ))}
                                                </CommandList>
                                            </Command>
                                        </PopoverContent>
                                    </Popover>
                                </Field>
                            );
                        }}
                    </form.Field>

                    <form.Field name="notes">
                        {(field) => (
                            <Field>
                                <FieldLabel htmlFor={field.name}>توضیحات:</FieldLabel>
                                <Input
                                    id={field.name}
                                    name={field.name}
                                    value={field.state.value}
                                    onBlur={field.handleBlur}
                                    onChange={(e) => field.handleChange(e.target.value)}
                                    placeholder="توضیح کوتاه درباره مدرک..."
                                    disabled={isUploading}
                                />
                            </Field>
                        )}
                    </form.Field>
                </>
            )}

            {validationError && (
                <p className="text-sm text-destructive">{validationError}</p>
            )}

            {serverError && (
                <p className="text-sm text-destructive">{serverError}</p>
            )}

            <Button
                type="submit"
                disabled={files.length === 0 || isUploading}
                onClick={() => {
                    setValidationError(null);
                    setServerError(null);
                }}
            >
                {isUploading ? (
                    <IconLoader2 className="size-4 animate-spin" />
                ) : mode === "zip" ? (
                    <IconFileZip className="size-4" />
                ) : (
                    <IconFileUpload className="size-4" />
                )}
                {mode === "zip"
                    ? "پردازش فایل فشرده"
                    : `آپلود${mode === "bulk" && files.length > 0 ? ` (${files.length})` : " مدرک"}`}
            </Button>
        </form>
    );
}
