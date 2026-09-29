import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { boardMeetingKeys } from "@/lib/query-keys";
import { getApiError } from "@/lib/error-utils";
import {
    deleteMeetingDocument,
    fetchBoardMeeting,
    fetchBoardMeetings,
    fetchMeetingDocuments,
    syncBoardMeetings,
    updateBoardMeeting,
    uploadMeetingDocument,
} from "../api";
import type { BoardMeetingListParams, UpdateBoardMeetingPayload } from "../types";

/**
 * Server-paginated board meeting list. The params object is the URL-driven
 * state produced by `useDataTableUrlState` — page/per_page/sort/order plus
 * the optional date window — so the query key changes exactly when the URL
 * does and the shared pagination footer drives the server.
 */
export function useBoardMeetings(params: BoardMeetingListParams) {
    return useQuery({
        queryKey: boardMeetingKeys.list(params as Record<string, unknown>),
        queryFn: async () => {
            const { data } = await fetchBoardMeetings(params);
            return data;
        },
    });
}

export function useBoardMeeting(id: number | string) {
    return useQuery({
        queryKey: boardMeetingKeys.detail(id),
        queryFn: async () => {
            const { data } = await fetchBoardMeeting(id);
            // The detail endpoint speaks the standard envelope; the hook
            // unwraps it — consumers get the meeting object itself.
            return data.data;
        },
    });
}

/**
 * Meeting documents + capabilities. Upload/delete mutations refresh both
 * the documents query and the meeting detail (its `approval_count` and
 * the `document_id` on each approval).
 */
export function useMeetingDocuments(meetingId: number | string) {
    return useQuery({
        queryKey: boardMeetingKeys.documents(meetingId),
        queryFn: async () => {
            const { data } = await fetchMeetingDocuments(meetingId);
            return data;
        },
    });
}

/** Update UI-owned meeting fields; refreshes detail + lists. */
export function useUpdateBoardMeeting(meetingId: number | string) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (payload: UpdateBoardMeetingPayload) =>
            updateBoardMeeting(meetingId, payload),
        onSuccess: () => {
            void queryClient.invalidateQueries({
                queryKey: boardMeetingKeys.detail(meetingId),
            });
            void queryClient.invalidateQueries({
                queryKey: boardMeetingKeys.lists(),
            });
        },
    });
}

/**
 * Run the registry sync. Lived behind the toolbar gear rather than a
 * top-level button: it reaches out to the source and rewrites the
 * registry columns, so it is not part of the everyday read rhythm even
 * though it is idempotent and never destructive.
 */
export function useSyncBoardMeetings() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async () => {
            const { data } = await syncBoardMeetings();
            return data;
        },
        onSuccess: (result) => {
            const { created, refreshed, locked } = result.meetings;

            toast.success("همگام‌سازی انجام شد", {
                description: `${created.toLocaleString("fa-IR")} جلسهٔ جدید، ${refreshed.toLocaleString("fa-IR")} به‌روزرسانی، ${locked.toLocaleString("fa-IR")} مورد قفل‌شده دست‌نخورده`,
            });

            void queryClient.invalidateQueries({
                queryKey: boardMeetingKeys.all,
            });
        },
        onError: (error) => {
            toast.error("همگام‌سازی ناموفق بود", {
                description: getApiError(error) ?? "خطای ناشناخته",
            });
        },
    });
}

export function useUploadMeetingDocument(meetingId: number | string) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (formData: FormData) =>
            uploadMeetingDocument(meetingId, formData),
        onSuccess: () => {
            void queryClient.invalidateQueries({
                queryKey: boardMeetingKeys.documents(meetingId),
            });
            void queryClient.invalidateQueries({
                queryKey: boardMeetingKeys.detail(meetingId),
            });
        },
    });
}

export function useDeleteMeetingDocument(meetingId: number | string) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (usageId: number) =>
            deleteMeetingDocument(meetingId, usageId),
        onSuccess: () => {
            void queryClient.invalidateQueries({
                queryKey: boardMeetingKeys.documents(meetingId),
            });
            void queryClient.invalidateQueries({
                queryKey: boardMeetingKeys.detail(meetingId),
            });
        },
    });
}
