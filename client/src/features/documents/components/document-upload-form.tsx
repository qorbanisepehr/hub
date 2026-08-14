import * as React from "react";
import { useForm } from "@tanstack/react-form";
import { useStore } from "@tanstack/react-form";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
    IconFile,
    IconFileText,
    IconFileUpload,
    IconLoader2,
    IconSelector,
    IconX,
} from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Field, FieldLabel } from "@/components/ui/field";
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
} from "@/features/documents/api";
import { getApiError } from "@/lib/error-utils";
import { documentKeys } from "@/lib/query-keys";
import { FILE_UPLOAD } from "@/lib/constants";
import { cn } from "@/lib/utils";

type FileItem = {
    file: File;
    status: "pending" | "uploading" | "success" | "error";
    error?: string;
};

type DocumentUploadFormProps = {
    documentableType: string;
    documentableId: number;
    onSuccess?: () => void;
    onDirtyChange?: (isDirty: boolean) => void;
};

function validateDocumentFile(file: File): string | null {
    if (file.size > FILE_UPLOAD.MAX_SIZE) return `${file.name}: حداکثر اندازه ۵۰ مگابایت`;
    const ext = file.name.split(".").pop()?.toLowerCase() ?? "";
    if (["pdf","jpg","jpeg","png","gif","webp","doc","docx","xls","xlsx","ppt","pptx","txt","csv"].includes(ext)) return null;
    return `${file.name}: فرمت پشتیبانی نمی‌شود`;
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
    documentableType,
    documentableId,
    onSuccess,
    onDirtyChange,
}: DocumentUploadFormProps) {
    const queryClient = useQueryClient();
    const [file, setFile] = React.useState<FileItem | null>(null);
    const [serverError, setServerError] = React.useState<string | null>(null);
    const [categoryOpen, setCategoryOpen] = React.useState(false);

    const { data: categories } = useQuery({
        queryKey: documentKeys.categories(documentableType),
        queryFn: async () => {
            const { data } = await fetchDocumentCategories();
            return data.data;
        },
    });

    const uploadMutation = useMutation({
        mutationFn: (payload: { category: string; notes: string; file: File }) => {
            const formData = new FormData();
            formData.append("document_category_id", payload.category);
            formData.append("documentable_type", documentableType);
            formData.append("documentable_id", String(documentableId));
            formData.append("file", payload.file);
            if (payload.notes) formData.append("notes", payload.notes);
            return uploadDocument(formData);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: documentKeys.lists() });
            resetAll();
            onSuccess?.();
        },
        onError: (error: unknown) => {
            if (file) {
                setFile({
                    ...file,
                    status: "error",
                    error: getApiError(error) ?? undefined,
                });
            }
            setServerError(getApiError(error));
        },
    });

    const form = useForm({
        defaultValues: {
            document_category_id: "",
            notes: "",
        },
        onSubmit: async ({ value }) => {
            setServerError(null);

            if (!file) {
                return;
            }

            if (!value.document_category_id) {
                setServerError("دسته‌بندی را انتخاب کنید");
                return;
            }

            setFile({ ...file, status: "uploading" });
            uploadMutation.mutate({
                category: value.document_category_id,
                notes: value.notes ?? "",
                file: file.file,
            });
        },
    });

    const formDirty = useStore(form.store, (state) => state.isDirty);
    const hasFile = file !== null;
    const isDirty = formDirty || hasFile;
    const isUploading = uploadMutation.isPending;

    React.useEffect(() => {
        onDirtyChange?.(isDirty);
    }, [isDirty, onDirtyChange]);

    function resetAll() {
        form.reset();
        setFile(null);
        setServerError(null);
    }

    function handleFilesAccepted(newFiles: File[]) {
        setServerError(null);

        if (newFiles.length > 1) {
            setServerError("فقط یک فایل مجاز است");
            return;
        }

        const error = validateDocumentFile(newFiles[0]);
        if (error) {
            setServerError(error);
            return;
        }

        setFile({ file: newFiles[0], status: "pending" });
    }

    function removeFile() {
        setFile(null);
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
                multiple={false}
                showFileList={false}
                accept=".pdf,.doc,.docx,.xlsx,.xls,.csv,.png,.jpg,.jpeg"
                browseLabel="انتخاب فایل"
                onFilesAccepted={handleFilesAccepted}
            />

            {file && (
                <div className="flex items-center gap-2 rounded-md border p-2">
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-sm">{file.file.name}</p>
                    </div>
                    {file.status === "success" && (
                        <span className="text-xs text-emerald-500">✓</span>
                    )}
                    {file.status === "error" && (
                        <span className="text-xs text-destructive">{file.error}</span>
                    )}
                    {!isUploading && (
                        <Button variant="ghost" size="icon-xs" onClick={removeFile}>
                            <IconX className="size-3" />
                        </Button>
                    )}
                </div>
            )}

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
                                                            <IconFile className="size-4" />
                                                        </span>
                                                    ) : item.depth === 1 ? (
                                                        <span className="text-muted-foreground">
                                                            <IconFileText className="size-4" />
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

            {serverError && (
                <p className="text-sm text-destructive">{serverError}</p>
            )}

            <Button
                type="submit"
                disabled={!file || isUploading}
            >
                {isUploading ? (
                    <IconLoader2 className="size-4 animate-spin" />
                ) : (
                    <IconFileUpload className="size-4" />
                )}
                آپلود مدرک
            </Button>
        </form>
    );
}
