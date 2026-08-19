import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import {
    fetchRetentionPolicies,
    createRetentionPolicy,
    updateRetentionPolicy,
    deleteRetentionPolicy,
} from "../api";
import { auditKeys } from "@/lib/query-keys";

export function useRetentionPolicies() {
    return useQuery({
        queryKey: auditKeys.retentionPolicies(),
        queryFn: () => fetchRetentionPolicies(),
    });
}

export function useCreateRetentionPolicy() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: createRetentionPolicy,
        onSuccess: () => {
            queryClient.invalidateQueries({
                queryKey: auditKeys.retentionPolicies(),
            });
        },
    });
}

export function useUpdateRetentionPolicy() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({ id, data }: { id: number; data: Parameters<typeof updateRetentionPolicy>[1] }) =>
            updateRetentionPolicy(id, data),
        onSuccess: () => {
            queryClient.invalidateQueries({
                queryKey: auditKeys.retentionPolicies(),
            });
        },
    });
}

export function useDeleteRetentionPolicy() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: deleteRetentionPolicy,
        onSuccess: () => {
            queryClient.invalidateQueries({
                queryKey: auditKeys.retentionPolicies(),
            });
        },
    });
}
