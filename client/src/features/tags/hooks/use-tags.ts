import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { tagKeys, boardMeetingKeys } from "@/lib/query-keys";
import { getApiError } from "@/lib/error-utils";
import {
    acceptTagMerges,
    attachTags,
    createTag,
    deleteTag,
    fetchTagOptions,
    fetchTags,
    proposeTagMerges,
    updateTag,
} from "../api";
import type {
    AcceptTagMergePayload,
    AttachTagsPayload,
    TagListParams,
} from "../types";

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

/**
 * Ask the consolidator for merge groups.
 *
 * Nothing is cached or invalidated: the proposal is not hub state, it is an
 * answer about the hub's state, and re-asking is how a person gets a fresh one.
 * The axios envelope is unwrapped here so the dialog receives the proposal
 * itself rather than a response object it would have to know the shape of.
 */
export function useProposeTagMerges() {
    return useMutation({
        mutationFn: () => proposeTagMerges().then((response) => response.data.data),
        onError: (error) => toast.error(getApiError(error)),
    });
}

/**
 * Apply the chosen merge groups.
 *
 * Invalidates board meetings too, not just tags: merging repoints attachments
 * from the duplicate tags onto the canonical one, so every meeting that carried
 * a duplicate now carries a different tag set than the payload the client holds.
 */
export function useAcceptTagMerges() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (payload: AcceptTagMergePayload) =>
            acceptTagMerges(payload).then((response) => response.data),
        onSuccess: (result) => {
            void queryClient.invalidateQueries({ queryKey: tagKeys.all });
            void queryClient.invalidateQueries({
                queryKey: boardMeetingKeys.all,
            });
            toast.success(
                `${result.merged.toLocaleString("fa-IR")} برچسب ادغام شد`,
            );
        },
        onError: (error) => toast.error(getApiError(error)),
    });
}
