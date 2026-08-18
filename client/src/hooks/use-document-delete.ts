import { useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { api } from "@/lib/api";
import { publicApi } from "@/lib/public-api";
import { documentKeys } from "@/lib/query-keys";
import { isAuthedDocumentEntity } from "@/hooks/use-entity-documents";

type UseDocumentDeleteOptions = {
    entity: string;
    uuid: string;
    successMessage?: string;
    errorMessage?: string;
};

export function useDocumentDelete({
    entity,
    uuid,
    successMessage = "فایل حذف شد",
    errorMessage = "خطا در حذف فایل",
}: UseDocumentDeleteOptions) {
    const queryClient = useQueryClient();
    const authed = isAuthedDocumentEntity(entity);
    const docClient = authed ? api : publicApi;

    const deleteMutation = useMutation({
        mutationFn: (usageId: number) =>
            docClient.delete(`/${entity}/${uuid}/documents/${usageId}`, {
                ...(authed
                    ? {}
                    : { grant: { entity, uuid, purpose: "edit" } }),
            }),
        onSuccess: () => {
            queryClient.invalidateQueries({
                queryKey: documentKeys.entityDocuments(entity, uuid),
            });
            toast.success(successMessage);
        },
        onError: () => toast.error(errorMessage),
    });

    return {
        deleteMutation,
        deleteDocument: deleteMutation.mutate,
        isDeleting: deleteMutation.isPending,
    };
}
