import * as React from "react";
import { useForm, useStore } from "@tanstack/react-form";
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
        .refine((f) => f.size <= MAX_FILE_SIZE, "حجم فایل حداکثر ۵۰ مگابایت"),
    notes: z.string().max(1000, "حداکثر ۱۰۰۰ کاراکتر").optional(),
});

type FormValues = z.infer<typeof documentSchema>;

type DocumentUploadProps = {
    employeeId: number;
    onSuccess?: () => void;
};

export function DocumentUpload({ employeeId, onSuccess }: DocumentUploadProps) {
    const queryClient = useQueryClient();
    const [uploadKey, setUploadKey] = React.useState(0);
    const [acceptedFile, setAcceptedFile] = React.useState<File | null>(null);

    const { data: categories } = useQuery({
        queryKey: ["document-categories", "employee"],
        queryFn: async () => {
            const { data } = await fetchDocumentCategories("employee");
            return data.data;
        },
    });

    const uploadMutation = useMutation({
        mutationFn: (values: FormValues) => {
            const formData = new FormData();
            formData.append(
                "document_category_id",
                values.document_category_id,
            );
            formData.append("file", values.file);
            if (values.notes) formData.append("notes", values.notes);
            return uploadDocument(employeeId, formData);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({
                queryKey: ["employee-documents", employeeId],
            });
            form.reset();
            setAcceptedFile(null);
            setUploadKey((k) => k + 1);
            onSuccess?.();
        },
    });

    const form = useForm({
        defaultValues: {
            document_category_id: "",
            file: undefined as unknown as File,
            notes: "",
        } as FormValues,
    });

    const categoryIdValue = useStore(
        form.store,
        (state) => state.values.document_category_id,
    );
    const notesValue = useStore(form.store, (state) => state.values.notes);
    const [validationError, setValidationError] = React.useState<string | null>(
        null,
    );

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                e.stopPropagation();
            }}
            className="space-y-4"
        >
            <FileUpload
                key={uploadKey}
                multiple={false}
                accept=".pdf,.doc,.docx,.xlsx,.xls,.csv,.png,.jpg,.jpeg"
                onFilesAccepted={(files) => {
                    const file = files[0];
                    if (file) {
                        setAcceptedFile(file);
                    }
                }}
            />

            <div className="space-y-4">
                <form.Field
                    name="document_category_id"
                    validators={{
                        onBlur: z.string().min(1, "دسته‌بندی را انتخاب کنید"),
                    }}
                >
                    {(field) => {
                        const isInvalid =
                            field.state.meta.isTouched &&
                            !field.state.meta.isValid;
                        return (
                            <Field data-invalid={isInvalid}>
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
                                {isInvalid && (
                                    <FieldError
                                        errors={field.state.meta.errors}
                                    />
                                )}
                            </Field>
                        );
                    }}
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

            {uploadMutation.error && (
                <p className="text-sm text-destructive">
                    {getApiError(uploadMutation.error)}
                </p>
            )}

            <Button
                type="button"
                disabled={!acceptedFile || uploadMutation.isPending}
                onClick={() => {
                    setValidationError(null);
                    const result = documentSchema.safeParse({
                        document_category_id: categoryIdValue,
                        file: acceptedFile,
                        notes: notesValue,
                    });
                    if (!result.success) {
                        setValidationError(result.error.issues[0].message);
                        return;
                    }
                    uploadMutation.mutate(result.data as FormValues);
                }}
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
