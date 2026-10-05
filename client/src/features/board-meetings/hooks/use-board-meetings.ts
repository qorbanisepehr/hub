import {
    useInfiniteQuery,
    useMutation,
    useQuery,
    useQueryClient,
} from "@tanstack/react-query";
import { toast } from "sonner";

import { boardMeetingKeys } from "@/lib/query-keys";
import { getApiError, getApiErrorStatus } from "@/lib/error-utils";
import {
    acceptMeetingSummary,
    bulkGenerateMeetingSummary,
    deleteMeetingDocument,
    fetchBoardMeeting,
    fetchBoardMeetings,
    fetchMeetingDocuments,
    fetchSummaryCandidates,
    generateMeetingSummary,
    syncBoardMeetings,
    updateBoardMeeting,
    uploadMeetingDocument,
} from "../api";
import type {
    AcceptSummaryPayload,
    BoardMeetingListParams,
    GenerateSummaryPayload,
    MeetingSummaryFieldName,
    SummaryBulkResult,
    SummaryWriteMode,
    UpdateBoardMeetingPayload,
} from "../types";

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
 * Ask for a run, or record a decision about the last one.
 *
 * Both write the meeting, and both are what the summary tab's own mutations
 * do, so they live together and share the same invalidation: the detail query
 * is the only cache that holds a summary, and the list only needs the tag
 * counts and the meeting's identity back.
 *
 * `onSettled` rather than `onSuccess` on the generate, because the run may
 * still be queued when the request returns — under the async queue the row is
 * stamped `pending` and nothing else, and the tab has to show that rather than
 * the value it had a moment ago.
 */
export function useGenerateMeetingSummary(meetingId: number | string) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (payload: GenerateSummaryPayload) =>
            generateMeetingSummary(meetingId, payload),
        onSettled: () => {
            void queryClient.invalidateQueries({
                queryKey: boardMeetingKeys.detail(meetingId),
            });
        },
    });
}

/**
 * Accept or dismiss a proposal.
 *
 * The 409 is worth naming: it means the proposal is gone, because the run was
 * applied instead of proposed or somebody else decided first. The meeting is
 * still there, so the recovery is a re-read, and saying so is better than
 * retrying into the same conflict or swallowing the failure.
 */
export function useAcceptMeetingSummary(meetingId: number | string) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (payload: AcceptSummaryPayload) =>
            acceptMeetingSummary(meetingId, payload),
        onSuccess: () => {
            void queryClient.invalidateQueries({
                queryKey: boardMeetingKeys.detail(meetingId),
            });
            void queryClient.invalidateQueries({
                queryKey: boardMeetingKeys.lists(),
            });
        },
        onError: (error) => {
            if (getApiErrorStatus(error) === 409) {
                toast.error("پیشنهادی برای تصمیم باقی نمانده است", {
                    description:
                        "اجرای دیگری این خلاصه را ثبت کرده یا پیشنهاد قبلی جایگزین شده است.",
                });

                return;
            }

            toast.error("ثبت تصمیم ناموفق بود", {
                description: getApiError(error) ?? "خطای ناشناخته",
            });
        },
    });
}

/**
 * The meetings a bulk run could still generate for, under the list's filters.
 *
 * Paged rather than fetched whole, and only while the picker is open
 * (`enabled`): the registry runs to hundreds of meetings and every one of them
 * is a slow model call, so the dialog loads a page and appends on request. It
 * is the server's `meta.total` that the dialog's count is checked against — a
 * "select all" that quietly meant "all of the hundred that loaded" would be a
 * checkbox lying.
 */
export function useSummaryCandidates(
    params: BoardMeetingListParams,
    enabled: boolean,
) {
    return useInfiniteQuery({
        queryKey: boardMeetingKeys.summaryCandidates(
            params as Record<string, unknown>,
        ),
        queryFn: async ({ pageParam }) => {
            const { data } = await fetchSummaryCandidates({
                ...params,
                page: pageParam as number,
            });

            return data;
        },
        initialPageParam: 1,
        getNextPageParam: (lastPage) => {
            const meta = lastPage?.meta;

            // The server's own `last_page` rather than arithmetic on `total`,
            // which can shrink between two requests as meetings gain
            // summaries. The empty-page guard covers the other direction: rows
            // that disappeared after the count was taken must end the walk
            // rather than spin it.
            if (!meta || lastPage.data.length === 0) {
                return undefined;
            }

            return meta.current_page < meta.last_page
                ? meta.current_page + 1
                : undefined;
        },
        enabled,
    });
}

/**
 * Queue a run for a chosen set of meetings.
 *
 * The server re-checks eligibility and reports what it skipped, so the count
 * in the toast is the count that actually ran — a dialog that rendered
 * "214 meetings" minutes before somebody generated three of them should not
 * claim 214.
 */
export function useBulkGenerateMeetingSummary() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (payload: {
            meeting_ids: number[];
            fields?: MeetingSummaryFieldName[];
            mode?: SummaryWriteMode;
            provider?: string | null;
            model?: string | null;
        }): Promise<SummaryBulkResult> => {
            const { data } = await bulkGenerateMeetingSummary(payload);

            return data.data;
        },
        onSuccess: ({ queued, skipped }) => {

            toast.success("درخواست تولید ثبت شد", {
                description:
                    skipped > 0
                        ? `${queued.toLocaleString("fa-IR")} جلسه در صف قرار گرفت؛ ${skipped.toLocaleString("fa-IR")} مورد به‌دلیل داشتن خلاصه رد شد.`
                        : `${queued.toLocaleString("fa-IR")} جلسه در صف قرار گرفت.`,
            });

            void queryClient.invalidateQueries({
                queryKey: boardMeetingKeys.all,
            });
        },
        onError: (error) => {
            toast.error("ثبت درخواست ناموفق بود", {
                description: getApiError(error) ?? "خطای ناشناخته",
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
