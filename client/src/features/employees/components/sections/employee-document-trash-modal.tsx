import * as React from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { IconArrowBackUp, IconLoader2, IconTrashOff } from "@tabler/icons-react";
import { toast } from "sonner";

import { getApiError } from "@/lib/error-utils";
import { documentKeys } from "@/lib/query-keys";
import { Skeleton } from "@/components/ui/skeleton";
import { Button } from "@/components/ui/button";
import { ResponsiveDialog } from "@/components/ui/responsive-dialog";
import { toPersianDate } from "@/lib/date-format";
import { ConfirmDeleteActions } from "@/features/documents/components/confirm-delete-actions";
import {
    fetchEmployeeTrashedDocuments,
    forceDeleteEmployeeDocument,
    restoreEmployeeDocument,
    type TrashedEmployeeDocument,
} from "@/features/employees/api";

type EmployeeDocumentTrashModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    employeeId: number;
};

const trashKey = (employeeId: number) =>
    documentKeys.trashed("employees", String(employeeId));

const activeKey = (employeeId: number) =>
    documentKeys.entityDocuments("employees", String(employeeId));

export function EmployeeDocumentTrashModal({
    open,
    onOpenChange,
    employeeId,
}: EmployeeDocumentTrashModalProps) {
    const queryClient = useQueryClient();
    const [confirmingDeleteId, setConfirmingDeleteId] = React.useState<
        number | null
    >(null);
    const [restoringIds, setRestoringIds] = React.useState<Set<number>>(
        new Set(),
    );
    const [forceDeletingIds, setForceDeletingIds] = React.useState<Set<number>>(
        new Set(),
    );

    const { data: trashedDocuments, isLoading } = useQuery({
        queryKey: trashKey(employeeId),
        enabled: open,
        queryFn: async () => {
            const { data } = await fetchEmployeeTrashedDocuments(employeeId);
            return data.data;
        },
    });

    const refreshActive = () => {
        queryClient.invalidateQueries({ queryKey: activeKey(employeeId) });
    };

    const restoreMutation = useMutation({
        mutationFn: (usageId: number) =>
            restoreEmployeeDocument(employeeId, usageId),
        onSuccess: (_, usageId) => {
            queryClient.setQueryData<TrashedEmployeeDocument[]>(
                trashKey(employeeId),
                (old) => old?.filter((d) => d.usage_id !== usageId),
            );
            setRestoringIds(new Set());
            refreshActive();
            toast.success("مدرک بازیابی شد");
        },
        onError: (err: unknown) => {
            setRestoringIds(new Set());
            toast.error(getApiError(err));
        },
    });

    const forceDeleteMutation = useMutation({
        mutationFn: (usageId: number) =>
            forceDeleteEmployeeDocument(employeeId, usageId),
        onSuccess: (_, usageId) => {
            queryClient.setQueryData<TrashedEmployeeDocument[]>(
                trashKey(employeeId),
                (old) => old?.filter((d) => d.usage_id !== usageId),
            );
            setForceDeletingIds(new Set());
            refreshActive();
            toast.success("مدرک برای همیشه حذف شد");
        },
        onError: (err: unknown) => {
            setForceDeletingIds(new Set());
            toast.error(getApiError(err));
        },
    });

    function handleRestore(usageId: number) {
        setRestoringIds((prev) => new Set(prev).add(usageId));
        restoreMutation.mutate(usageId);
    }

    function handleStartForceDelete(usageId: number) {
        setConfirmingDeleteId(usageId);
    }

    function handleCancelForceDelete() {
        setConfirmingDeleteId(null);
    }

    function handleConfirmForceDelete(usageId: number) {
        setConfirmingDeleteId(null);
        setForceDeletingIds((prev) => new Set(prev).add(usageId));
        forceDeleteMutation.mutate(usageId);
    }

    return (
        <ResponsiveDialog
            open={open}
            onOpenChange={onOpenChange}
            title="سطل زباله مدارک"
            description="مدارک حذف‌شده این کارمند"
        >
            {isLoading ? (
                <div className="space-y-3 py-4">
                    {Array.from({ length: 2 }).map((_, i) => (
                        <Skeleton key={i} className="h-12 w-full rounded-lg" />
                    ))}
                </div>
            ) : !trashedDocuments?.length ? (
                <div className="flex flex-col items-center justify-center py-8 text-muted-foreground">
                    <IconTrashOff className="size-10 mb-3 opacity-30" />
                    <p className="text-sm">سطل زباله خالی است</p>
                </div>
            ) : (
                <div className="divide-y">
                    {trashedDocuments.map((doc) => {
                        const isRestoring = restoringIds.has(doc.usage_id);
                        const isForceDeleting = forceDeletingIds.has(
                            doc.usage_id,
                        );
                        const isConfirming =
                            confirmingDeleteId === doc.usage_id;

                        return (
                            <div
                                key={doc.usage_id}
                                className="flex items-center justify-between gap-3 py-3"
                            >
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-medium">
                                        {doc.structure_name}
                                    </p>
                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                        {doc.field_key
                                            ? `${doc.field_key} — `
                                            : ""}
                                        {doc.deleted_at
                                            ? toPersianDate(doc.deleted_at)
                                            : ""}
                                    </p>
                                </div>
                                <div className="flex shrink-0 items-center gap-1">
                                    <Button
                                        variant="ghost"
                                        size="icon-xs"
                                        onClick={() => handleRestore(doc.usage_id)}
                                        disabled={isRestoring}
                                        aria-label={`بازیابی ${doc.structure_name}`}
                                    >
                                        {isRestoring ? (
                                            <IconLoader2 className="size-3.5 animate-spin" />
                                        ) : (
                                            <IconArrowBackUp className="size-3.5" />
                                        )}
                                    </Button>
                                    {isConfirming ? (
                                        <ConfirmDeleteActions
                                            docId={doc.usage_id}
                                            isPending={isForceDeleting}
                                            onConfirm={handleConfirmForceDelete}
                                            onCancel={handleCancelForceDelete}
                                        />
                                    ) : (
                                        <Button
                                            variant="ghost"
                                            size="icon-xs"
                                            onClick={() =>
                                                handleStartForceDelete(
                                                    doc.usage_id,
                                                )
                                            }
                                            disabled={isForceDeleting}
                                            aria-label={`حذف همیشگی ${doc.structure_name}`}
                                        >
                                            {isForceDeleting ? (
                                                <IconLoader2 className="size-3.5 animate-spin" />
                                            ) : (
                                                <IconTrashOff className="size-3.5" />
                                            )}
                                        </Button>
                                    )}
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}
        </ResponsiveDialog>
    );
}
