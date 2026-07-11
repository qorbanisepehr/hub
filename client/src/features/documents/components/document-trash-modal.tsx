import * as React from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { IconTrashOff } from "@tabler/icons-react";

import { Skeleton } from "@/components/ui/skeleton";
import { ResponsiveDialog } from "@/components/ui/responsive-dialog";
import {
    fetchTrashedDocuments,
    restoreDocument,
    forceDeleteDocument,
} from "@/features/documents/api";
import { DocumentTrashTable } from "./document-trash-table";

type DocumentTrashModalProps = {
    employeeId: number;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export function DocumentTrashModal({
    employeeId,
    open,
    onOpenChange,
}: DocumentTrashModalProps) {
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
        queryKey: ["employee-documents", employeeId, "trash"],
        enabled: open,
        queryFn: async () => {
            const { data } = await fetchTrashedDocuments(employeeId);
            return data.data;
        },
    });

    const restoreMutation = useMutation({
        mutationFn: (documentId: number) => restoreDocument(documentId),
        onSuccess: (response, documentId) => {
            queryClient.setQueryData(
                ["employee-documents", employeeId],
                (old: any) => [response.data.data, ...(old ?? [])],
            );
            queryClient.invalidateQueries({
                queryKey: ["employee-documents", employeeId, "trash"],
            });
            setRestoringIds(new Set());
        },
        onError: () => {
            setRestoringIds(new Set());
        },
    });

    const forceDeleteMutation = useMutation({
        mutationFn: (documentId: number) => forceDeleteDocument(documentId),
        onSuccess: (_, documentId) => {
            queryClient.setQueryData(
                ["employee-documents", employeeId, "trash"],
                (old: any) => old?.filter((d: any) => d.id !== documentId),
            );
            setForceDeletingIds(new Set());
        },
        onError: () => {
            setForceDeletingIds(new Set());
        },
    });

    function handleRestore(documentId: number) {
        setRestoringIds((prev) => new Set(prev).add(documentId));
        restoreMutation.mutate(documentId);
    }

    function handleStartForceDelete(documentId: number) {
        setConfirmingDeleteId(documentId);
    }

    function handleCancelForceDelete() {
        setConfirmingDeleteId(null);
    }

    function handleConfirmForceDelete(documentId: number) {
        setConfirmingDeleteId(null);
        setForceDeletingIds((prev) => new Set(prev).add(documentId));
        forceDeleteMutation.mutate(documentId);
    }

    return (
        <ResponsiveDialog
            open={open}
            onOpenChange={onOpenChange}
            title="سطل زباله"
            description="مدارک حذف شده"
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
                <DocumentTrashTable
                    documents={trashedDocuments}
                    confirmingDeleteId={confirmingDeleteId}
                    restoringIds={restoringIds}
                    forceDeletingIds={forceDeletingIds}
                    onRestore={handleRestore}
                    onStartForceDelete={handleStartForceDelete}
                    onConfirmForceDelete={handleConfirmForceDelete}
                    onCancelForceDelete={handleCancelForceDelete}
                />
            )}
        </ResponsiveDialog>
    );
}
