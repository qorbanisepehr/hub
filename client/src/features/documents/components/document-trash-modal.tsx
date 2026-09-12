import * as React from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { IconTrashOff } from "@tabler/icons-react";
import { toast } from "sonner";

import { getApiError } from "@/lib/error-utils";
import { documentKeys } from "@/lib/query-keys";
import { EmptyState } from "@/components/layout";
import { Skeleton } from "@/components/ui/skeleton";
import { ResponsiveDialog } from "@/components/ui/responsive-dialog";
import {
    fetchTrashedDocuments,
    restoreDocument,
    forceDeleteDocument,
} from "@/features/documents/api";
import type { Document } from "@/features/documents/types";
import { DocumentTrashTable } from "./document-trash-table";

type DocumentTrashModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    documentableType?: string;
    documentableId?: number;
};

export function DocumentTrashModal({
    open,
    onOpenChange,
    documentableType,
    documentableId,
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

    const entityId = documentableId ? String(documentableId) : undefined;

    const { data: trashedDocuments, isLoading } = useQuery({
        queryKey: documentKeys.trashed(documentableType, entityId),
        enabled: open,
        queryFn: async () => {
            const { data } = await fetchTrashedDocuments(
                documentableType,
                entityId,
            );
            return data.data;
        },
    });

    const restoreMutation = useMutation({
        mutationFn: (documentId: number) => restoreDocument(documentId),
        onSuccess: (response, documentId) => {
            queryClient.setQueryData(
                documentKeys.trashed(documentableType, entityId),
                (old: Document[] | undefined) =>
                    old?.filter((d) => d.id !== documentId),
            );
            queryClient.invalidateQueries({
                queryKey: documentKeys.lists(),
            });
            setRestoringIds(new Set());
            toast.success("مدرک بازیابی شد");
        },
        onError: (err: unknown) => {
            setRestoringIds(new Set());
            toast.error(getApiError(err));
        },
    });

    const forceDeleteMutation = useMutation({
        mutationFn: (documentId: number) => forceDeleteDocument(documentId),
        onSuccess: (_, documentId) => {
            queryClient.setQueryData(
                documentKeys.trashed(documentableType, entityId),
                (old: Document[] | undefined) =>
                    old?.filter((d) => d.id !== documentId),
            );
            setForceDeletingIds(new Set());
            toast.success("مدرک برای همیشه حذف شد");
        },
        onError: (err: unknown) => {
            setForceDeletingIds(new Set());
            toast.error(getApiError(err));
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
                <EmptyState
                    icon={IconTrashOff}
                    message="سطل زباله خالی است"
                    variant="compact"
                />
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
