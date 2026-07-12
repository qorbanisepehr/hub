import * as React from "react";
import { useForm } from "@tanstack/react-form";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { z } from "zod";
import { IconFileUpload, IconLoader2 } from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Field, FieldError, FieldLabel } from "@/components/ui/field";
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
    uploadDocument,
} from "@/features/documents/api";
import { getApiError } from "@/lib/error-utils";

const MAX_FILE_SIZE = 50 * 1024 * 1024;

const documentSchema = z.object({
    document_category_id: z.string().min(1, "دسته‌بندی را انتخاب کنید"),
    file: z
        .instanceof(File, { message: "فایل را انتخاب کنید" })
        .refine((f) => f.size <= MAX_FILE_SIZE, "حجم فایل حداکثر ۵۰ مگابایت")
        .refine(
            (f) =>
                [
                    "pdf",
                    "jpg",
                    "jpeg",
                    "png",
                    "gif",
                    "webp",
                    "doc",
                    "docx",
                    "xls",
                    "xlsx",
                    "ppt",
                    "pptx",
                    "txt",
                    "csv",
                ].includes(f.name.split(".").pop()?.toLowerCase() ?? ""),
            "فرمت فایل پشتیبانی نمی‌شود",
        ),
    notes: z.string().max(1000, "حداکثر ۱۰۰۰ کاراکتر").optional(),
});

type DocumentUploadProps = {
    employeeId: number;
    onSuccess?: () => void;
};

export function DocumentUpload({ employeeId, onSuccess }: DocumentUploadProps) {
    const queryClient = useQueryClient();
    const [uploadKey, setUploadKey] = React.useState(0);
    const [acceptedFile, setAcceptedFile] = React.useState<File | null>(null);
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
        mutationFn: (payload: {
            category: string;
            notes: string;
            file: File;
        }) => {
            const formData = new FormData();
            formData.append("document_category_id", payload.category);
            formData.append("file", payload.file);
            if (payload.notes) formData.append("notes", payload.notes);
            return uploadDocument(employeeId, formData);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({
                queryKey: ["employee-documents", employeeId],
            });
            form.reset();
            setAcceptedFile(null);
            setUploadKey((k) => k + 1);
            setValidationError(null);
            setServerError(null);
            onSuccess?.();
        },
        onError: (error: unknown) => {
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

            if (!acceptedFile) {
                setValidationError("فایل را انتخاب کنید");
                return;
            }

            const result = documentSchema.safeParse({
                ...value,
                file: acceptedFile,
            });

            if (!result.success) {
                setValidationError(result.error.issues[0].message);
                return;
            }

            uploadMutation.mutate({
                category: value.document_category_id,
                notes: value.notes ?? "",
                file: acceptedFile,
            });
        },
    });

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
                multiple={false}
                accept=".pdf,.doc,.docx,.xlsx,.xls,.csv,.png,.jpg,.jpeg"
                browseLabel="انتخاب فایل‌"
                onFilesAccepted={(files) => {
                    const file = files[0];
                    if (file) {
                        setAcceptedFile(file);
                        setValidationError(null);
                        setServerError(null);
                    }
                }}
            />

            <div className="space-y-4">
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
                            >
                                <SelectTrigger id={field.name}>
                                    <SelectValue placeholder="انتخاب دسته‌بندی">
                                        {
                                            categories?.find(
                                                (c) =>
                                                    String(c.id) ===
                                                    field.state.value,
                                            )?.name
                                        }
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
                                placeholder="توضیح کوتاه درباره مدرک..."
                            />
                        </Field>
                    )}
                </form.Field>
            </div>

            {validationError && (
                <p className="text-sm text-destructive">{validationError}</p>
            )}

            {serverError && (
                <p className="text-sm text-destructive">{serverError}</p>
            )}

            <Button
                type="submit"
                disabled={!acceptedFile || uploadMutation.isPending}
            >
                {uploadMutation.isPending ? (
                    <IconLoader2 className="size-4 animate-spin" />
                ) : (
                    <IconFileUpload className="size-4" />
                )}
                آپلود مدرک
            </Button>
        </form>
    );
}
