import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { tagKeys, boardMeetingKeys } from "@/lib/query-keys";
import {
    attachTags,
    createTag,
    deleteTag,
    fetchTagOptions,
    fetchTags,
    updateTag,
} from "../api";
import type { AttachTagsPayload, TagListParams } from "../types";

/**
 * Server-paginated tag list (tag management UI). The params object is the
 * URL-driven state produced by `useDataTableUrlState`, so the query key
 * changes exactly when the URL does.
 */
export function useTags(params: TagListParams) {
    return useQuery({
        queryKey: tagKeys.list(params as Record<string, unknown>),
        queryFn: async () => {
            const { data } = await fetchTags(params);
            return data;
        },
    });
}

/** Flat option list for pickers / filter bars (tag.view, capped at 100). */
export function useTagOptions(search?: string) {
    return useQuery({
        queryKey: [...tagKeys.options(), search ?? ""] as const,
        queryFn: async () => {
            const { data } = await fetchTagOptions(search);
            return data.data;
        },
    });
}

export function useCreateTag() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: createTag,
        onSuccess: () => {
            void queryClient.invalidateQueries({ queryKey: tagKeys.all });
        },
    });
}

export function useUpdateTag() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({ id, name }: { id: number; name: string }) =>
            updateTag(id, { name }),
        onSuccess: () => {
            void queryClient.invalidateQueries({ queryKey: tagKeys.all });
            // Meetings embed tag names; a rename must refresh them.
            void queryClient.invalidateQueries({
                queryKey: boardMeetingKeys.all,
            });
        },
    });
}

export function useDeleteTag() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (id: number) => deleteTag(id),
        onSuccess: () => {
            void queryClient.invalidateQueries({ queryKey: tagKeys.all });
            void queryClient.invalidateQueries({
                queryKey: boardMeetingKeys.all,
            });
        },
    });
}

/** Sync a meeting's tag set; refreshes the meeting detail + lists. */
export function useAttachTags() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (payload: AttachTagsPayload) => attachTags(payload),
        onSuccess: () => {
            void queryClient.invalidateQueries({
                queryKey: boardMeetingKeys.all,
            });
            // New slugs may have been created on first use.
            void queryClient.invalidateQueries({ queryKey: tagKeys.all });
        },
    });
}
