import * as React from "react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { IconFileUpload, IconLoader2, IconX } from "@tabler/icons-react";
import { toast } from "sonner";

import { getApiError } from "@/lib/error-utils";
import { documentKeys } from "@/lib/query-keys";
import { FILE_UPLOAD } from "@/lib/constants";
import { Button } from "@/components/ui/button";
import { ResponsiveDialog } from "@/components/ui/responsive-dialog";
import { FileUpload } from "@/components/ui/file-upload";
import { replaceEmployeeDocument } from "@/features/employees/api";
import type { EmployeeDocument } from "@/features/employees/hooks/use-employee-documents";

type EmployeeDocumentReplaceModalProps = {
    employeeId: number;
    doc: EmployeeDocument | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

const ACCEPT = ".pdf,.jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp";

function validateReplaceFile(file: File): string | null {
    if (file.size > FILE_UPLOAD.MAX_SIZE) {
        return "حداکثر اندازه مجاز ۵۰ مگابایت است";
    }
    const ext = file.name.split(".").pop()?.toLowerCase() ?? "";
    if (["pdf", "jpg", "jpeg", "png", "webp"].includes(ext)) return null;
    return "فرمت پشتیبانی نمی‌شود";
}

/**
 * Replace the current employee document. The backend keeps the old document as
 * soft-deleted history and creates a fresh Document for the new file.
 */
export function EmployeeDocumentReplaceModal({
    employeeId,
    doc,
    open,
    onOpenChange,
}: EmployeeDocumentReplaceModalProps) {
    const queryClient = useQueryClient();
    const [file, setFile] = React.useState<File | null>(null);
    const [serverError, setServerError] = React.useState<string | null>(null);

    const replaceMutation = useMutation({
        mutationFn: (selectedFile: File) => {
            const formData = new FormData();
            formData.append("file", selectedFile);
            return replaceEmployeeDocument(employeeId, doc!.usage_id, formData);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({
                queryKey: documentKeys.entityDocuments(
                    "employees",
                    String(employeeId),
                ),
            });
            queryClient.invalidateQueries({
                queryKey: documentKeys.trashed("employees", String(employeeId)),
            });
            toast.success("مدرک جایگزین شد");
            setFile(null);
            setServerError(null);
            onOpenChange(false);
        },
        onError: (error: unknown) => {
            setServerError(getApiError(error) ?? "خطا در جایگزینی مدرک");
        },
    });

    function handleClose(nextOpen: boolean) {
        if (!nextOpen) {
            setFile(null);
            setServerError(null);
        }
        onOpenChange(nextOpen);
    }

    const isSubmitting = replaceMutation.isPending;

    function handleSubmit() {
        if (!file) return;
        setServerError(null);
        replaceMutation.mutate(file);
    }

    return (
        <ResponsiveDialog
            open={open}
            onOpenChange={handleClose}
            title="جایگزینی مدرک"
            description={
                doc ? `جایگزینی «${doc.structure_name}» با فایل جدید` : undefined
            }
        >
            <div className="space-y-4">
                <FileUpload
                    key={doc?.usage_id ?? "none"}
                    multiple={false}
                    showFileList={false}
                    accept={ACCEPT}
                    browseLabel="انتخاب فایل جدید"
                    onFilesAccepted={(files) => {
                        const selected = files[0];
                        if (!selected) return;
                        const error = validateReplaceFile(selected);
                        if (error) {
                            setServerError(error);
                            return;
                        }
                        setServerError(null);
                        setFile(selected);
                    }}
                />

                {file && (
                    <div className="flex items-center gap-2 rounded-md border p-2">
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-sm">{file.name}</p>
                        </div>
                        {!isSubmitting && (
                            <Button
                                variant="ghost"
                                size="icon-xs"
                                onClick={() => setFile(null)}
                            >
                                <IconX className="size-3" />
                            </Button>
                        )}
                    </div>
                )}

                {serverError && (
                    <p className="text-sm text-destructive">{serverError}</p>
                )}

                <Button
                    type="button"
                    disabled={!file || isSubmitting}
                    onClick={handleSubmit}
                >
                    {isSubmitting ? (
                        <IconLoader2 className="size-4 animate-spin" />
                    ) : (
                        <IconFileUpload className="size-4" />
                    )}
                    جایگزینی مدرک
                </Button>
            </div>
        </ResponsiveDialog>
    );
}
