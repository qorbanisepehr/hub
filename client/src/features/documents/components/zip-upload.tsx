import * as React from "react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { z } from "zod";
import { IconFileZip, IconLoader2 } from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import { Progress } from "@/components/ui/progress";
import { FileUpload } from "@/components/ui/file-upload";
import { zipUploadDocuments } from "@/features/documents/api";
import { getApiError } from "@/lib/error-utils";
import { employeeKeys } from "@/lib/query-keys";

const zipFileSchema = z
    .instanceof(File)
    .refine((f) => f.size <= 100 * 1024 * 1024, "حداکثر اندازه ۱۰۰ مگابایت")
    .refine(
        (f) => f.name.split(".").pop()?.toLowerCase() === "zip",
        "فقط فایل فشرده zip پشتیبانی می‌شود",
    );

function validateZipFile(file: File): string | null {
    const result = zipFileSchema.safeParse(file);
    return result.success ? null : result.error.errors[0].message;
}

type ZipUploadProps = {
    employeeId: number;
    onSuccess?: () => void;
};

export function ZipUpload({ employeeId, onSuccess }: ZipUploadProps) {
    const queryClient = useQueryClient();
    const [file, setFile] = React.useState<File | null>(null);
    const [progress, setProgress] = React.useState(0);
    const [uploadKey, setUploadKey] = React.useState(0);
    const [validationError, setValidationError] = React.useState<string | null>(
        null,
    );
    const [serverError, setServerError] = React.useState<string | null>(null);

    const uploadMutation = useMutation({
        mutationFn: async () => {
            if (!file) throw new Error("No file");

            const formData = new FormData();
            formData.append("file", file);

            return zipUploadDocuments(employeeId, formData, (progressEvent) => {
                const percent = progressEvent.total
                    ? Math.round(
                          (progressEvent.loaded * 100) / progressEvent.total,
                      )
                    : 0;
                setProgress(percent);
            });
        },
        onSuccess: () => {
            queryClient.invalidateQueries({
                queryKey: employeeKeys.documents(employeeId),
            });
            setProgress(0);
            setFile(null);
            setValidationError(null);
            setServerError(null);
            setUploadKey((k) => k + 1);
            setTimeout(() => {
                onSuccess?.();
            }, 1500);
        },
        onError: (error: unknown) => {
            setProgress(0);
            setServerError(getApiError(error));
        },
    });

    function handleFileAccepted(files: File[]) {
        const zipFile = files[0];
        if (!zipFile) return;

        const error = validateZipFile(zipFile);
        if (error) {
            setValidationError(error);
            return;
        }

        setValidationError(null);
        setServerError(null);
        setFile(zipFile);
    }

    const isUploading = uploadMutation.isPending;

    return (
        <div className="space-y-4">
            <FileUpload
                key={uploadKey}
                multiple={false}
                showFileList={false}
                accept=".zip"
                description="zip"
                browseLabel="انتخاب فایل‌"
                onFilesAccepted={handleFileAccepted}
            />

            {file && (
                <div className="space-y-2">
                    <div className="flex items-center gap-2 rounded-md border p-2">
                        <IconFileZip className="size-4 text-muted-foreground" />
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-sm">{file.name}</p>
                            <p className="text-xs text-muted-foreground">
                                {(file.size / 1024 / 1024).toFixed(2)} MB
                            </p>
                        </div>
                    </div>

                    {isUploading && (
                        <div className="space-y-1">
                            <div className="flex justify-between text-xs text-muted-foreground">
                                <span>در حال پردازش...</span>
                                <span>{progress}%</span>
                            </div>
                            <Progress value={progress} className="h-1.5" />
                        </div>
                    )}
                </div>
            )}

            {validationError && (
                <p className="text-sm text-destructive">{validationError}</p>
            )}

            {serverError && (
                <p className="text-sm text-destructive">{serverError}</p>
            )}

            {uploadMutation.isSuccess && (
                <p className="text-sm text-emerald-500">
                    فایل فشرده با موفقیت پردازش شد
                </p>
            )}

            <Button
                type="button"
                disabled={!file || isUploading}
                onClick={() => {
                    setServerError(null);
                    setValidationError(null);
                    uploadMutation.mutate();
                }}
            >
                {isUploading ? (
                    <IconLoader2 className="size-4 animate-spin" />
                ) : (
                    <IconFileZip className="size-4" />
                )}
                پردازش فایل فشرده
            </Button>
        </div>
    );
}
