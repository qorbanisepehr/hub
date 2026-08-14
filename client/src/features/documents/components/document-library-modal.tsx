import * as React from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
    IconFile,
    IconLoader2,
    IconLibrary,
    IconPlus,
} from "@tabler/icons-react";
import { toast } from "sonner";

import { documentKeys } from "@/lib/query-keys";
import { getApiError } from "@/lib/error-utils";
import { formatBytes } from "@/lib/file-size";
import { cn } from "@/lib/utils";
import { Skeleton } from "@/components/ui/skeleton";
import { Button } from "@/components/ui/button";
import { ResponsiveDialog } from "@/components/ui/responsive-dialog";
import { FileThumbnail } from "@/components/ui/file-thumbnail";
import { getFileIcon } from "@/lib/file-icon";
import { getFileColorClasses } from "@/lib/file-colors";
import {
    fetchEmployeeDocumentLibrary,
    selectFromLibrary,
} from "@/features/documents/api";
import type { Document } from "@/features/documents/types";

type DocumentLibraryModalProps = {
    employeeId: number;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

/**
 * The employee's own active documents, listed for reuse. Picking one creates
 * a fresh Document + usage on the same employee (the source stays untouched —
 * the backend never shares identities).
 */
export function DocumentLibraryModal({
    employeeId,
    open,
    onOpenChange,
}: DocumentLibraryModalProps) {
    const queryClient = useQueryClient();
    const [selectingId, setSelectingId] = React.useState<number | null>(null);

    const { data: documents, isLoading } = useQuery({
        queryKey: documentKeys.library(employeeId),
        enabled: open,
        queryFn: async () => {
            const { data } = await fetchEmployeeDocumentLibrary(employeeId);
            return data.data;
        },
    });

    const selectMutation = useMutation({
        mutationFn: (sourceDocumentId: number) =>
            selectFromLibrary({
                source_document_id: sourceDocumentId,
                documentable_type: "employee",
                documentable_id: employeeId,
            }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: documentKeys.lists() });
            queryClient.invalidateQueries({
                queryKey: documentKeys.entityDocuments(
                    "employees",
                    String(employeeId),
                ),
            });
            toast.success("مدرک از کتابخانه انتخاب شد");
            onOpenChange(false);
        },
        onError: (error: unknown) => {
            toast.error(getApiError(error) ?? "خطا در انتخاب مدرک");
        },
    });

    function handleSelect(doc: Document) {
        const sourceId = doc.document_id ?? doc.id;
        setSelectingId(sourceId);
        selectMutation.mutate(sourceId);
    }

    return (
        <ResponsiveDialog
            open={open}
            onOpenChange={onOpenChange}
            title="کتابخانه مدارک"
            description="مدارک قابل استفاده این کارمند"
        >
            {isLoading ? (
                <div className="space-y-3 py-4">
                    {Array.from({ length: 3 }).map((_, i) => (
                        <Skeleton key={i} className="h-14 w-full rounded-lg" />
                    ))}
                </div>
            ) : !documents?.length ? (
                <div className="flex flex-col items-center justify-center py-10 text-muted-foreground">
                    <IconLibrary className="size-10 mb-3 opacity-30" />
                    <p className="text-sm">کتابخانه خالی است</p>
                </div>
            ) : (
                <div className="max-h-80 space-y-2 overflow-y-auto py-1">
                    {documents.map((doc) => {
                        const sourceId = doc.document_id ?? doc.id;
                        const isImage = doc.mime_type?.startsWith("image/");
                        const isSelecting = selectingId === sourceId;

                        return (
                            <div
                                key={sourceId}
                                className="flex items-center gap-3 rounded-lg border px-3 py-2"
                            >
                                {isImage ? (
                                    <FileThumbnail
                                        file={{
                                            name:
                                                doc.structure_name ??
                                                doc.category?.name ??
                                                "",
                                            type: doc.mime_type ?? "",
                                        }}
                                        previewImageUrl={doc.serve_url}
                                        className="size-10 shrink-0 rounded-md"
                                        previewClassName="aspect-square"
                                    />
                                ) : (
                                    <div
                                        className={cn(
                                            "flex size-10 shrink-0 items-center justify-center rounded-md border",
                                            getFileColorClasses(
                                                doc.mime_type ?? "",
                                            ),
                                        )}
                                    >
                                        {getFileIcon(
                                            doc.mime_type ?? "",
                                            "size-5",
                                        )}
                                    </div>
                                )}
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-medium">
                                        {doc.structure_name ??
                                            doc.category?.name ??
                                            "مدرک"}
                                    </p>
                                    <p className="truncate text-xs text-muted-foreground">
                                        {doc.category?.name ?? ""}
                                        {doc.size !== undefined
                                            ? ` — ${formatBytes(doc.size)}`
                                            : ""}
                                    </p>
                                </div>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={isSelecting}
                                    onClick={() => handleSelect(doc)}
                                >
                                    {isSelecting ? (
                                        <IconLoader2 className="size-4 animate-spin" />
                                    ) : (
                                        <IconPlus className="size-4" />
                                    )}
                                    انتخاب
                                </Button>
                            </div>
                        );
                    })}
                </div>
            )}
        </ResponsiveDialog>
    );
}
