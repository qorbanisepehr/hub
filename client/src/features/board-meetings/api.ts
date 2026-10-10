import { api, FORM_DATA_HEADERS } from "@/lib/api";
import type { ExportPresentation } from "@/components/shared";
import type {
    AcceptSummaryPayload,
    BoardMeetingDetailResponse,
    BoardMeetingListParams,
    BoardMeetingListResponse,
    BoardMeetingSyncResult,
    BulkDocumentUploadReport,
    GenerateSummaryPayload,
    MeetingDocumentsResponse,
    MeetingSummaryFieldName,
    SummaryBulkResponse,
    SummaryCandidateListResponse,
    SummaryWriteMode,
    UpdateBoardMeetingPayload,
} from "./types";

export function fetchBoardMeetings(params: BoardMeetingListParams) {
    return api.get<BoardMeetingListResponse>("/board-meetings", { params });
}

export type BoardMeetingExportField = {
    key: string;
    label: string;
    column: string;
};

export function fetchBoardMeetingExportFields() {
    return api.get<{ data: BoardMeetingExportField[] }>(
        "/board-meetings/export/fields",
    );
}

/**
 * Streamed registry export. Honors the same filters as the list page —
 * the date window and the AND tag filter — so the file never exceeds the
 * list the user may see. `details` opts the xlsx writer into the three
 * repeater sheets (approvals / attendees / agenda).
 */
export function exportBoardMeetings(params: {
    fields?: string[];
    format?: "xlsx" | "csv" | "pdf" | "docx";
    tags?: string[];
    date_from?: string;
    date_to?: string;
    filter?: string;
    presentation?: ExportPresentation;
}) {
    const presentation = params.presentation;

    return api.get("/board-meetings/export", {
        params: {
            fields: (params.fields ?? []).join(","),
            format: params.format ?? "xlsx",
            tags: params.tags,
            date_from: params.date_from,
            date_to: params.date_to,
            filter: params.filter,
            headers: presentation?.headers,
            calendar: presentation?.calendar,
            digits: presentation?.digits,
            details: presentation?.detailSheets ? 1 : undefined,
        },
        responseType: "blob",
    });
}

export function fetchBoardMeeting(id: number | string) {
    // Standard envelope: the meeting object rides `data`.
    return api.get<{ data: BoardMeetingDetailResponse }>(
        `/board-meetings/${id}`,
    );
}

/** Update the human-owned fields (title/minutes/attendees). */
export function updateBoardMeeting(
    id: number | string,
    payload: UpdateBoardMeetingPayload,
) {
    return api.put<{ data: BoardMeetingDetailResponse }>(
        `/board-meetings/${id}`,
        payload,
    );
}

/**
 * Pull the registry from whichever source is bound and project it into
 * the meetings table. Idempotent, never destructive, and it will not
 * overwrite a meeting a person has already edited.
 */
export function syncBoardMeetings() {
    return api.post<BoardMeetingSyncResult>("/board-meetings/sync");
}

/** Ask for a run. Queued, not performed — the response is the meeting either way. */
export function generateMeetingSummary(
    id: number | string,
    payload: GenerateSummaryPayload,
) {
    return api.post<{ data: BoardMeetingDetailResponse }>(
        `/board-meetings/${id}/summary`,
        payload,
    );
}

/**
 * Refuse a proposal outright, changing nothing.
 *
 * Its own endpoint rather than an accept with an empty selection, because
 * accept has to carry the meeting's tags in full — so a person who wants none
 * of the proposal and no opinion about tags has nothing they could send, and
 * the workaround used to re-write the tag pivot on the way through. Takes no
 * body for the same reason: a body could name tags to set, which is the one
 * thing this must not be able to do.
 */
export function dismissMeetingSummary(id: number | string) {
    return api.post<{ data: BoardMeetingDetailResponse }>(
        `/board-meetings/${id}/summary/dismiss`,
    );
}

/**
 * Record a decision about the pending proposal.
 *
 * A 409 here means somebody else got there first, or the run was applied
 * rather than proposed — so the caller re-reads the meeting instead of
 * retrying, and says so to the user rather than swallowing it.
 */
export function acceptMeetingSummary(
    id: number | string,
    payload: AcceptSummaryPayload,
) {
    return api.post<{ data: BoardMeetingDetailResponse }>(
        `/board-meetings/${id}/summary/accept`,
        payload,
    );
}

/** The meetings a bulk run could still generate for, under the list's filters. */
export function fetchSummaryCandidates(params: BoardMeetingListParams) {
    return api.get<SummaryCandidateListResponse>(
        "/board-meetings/summary/candidates",
        { params },
    );
}

export function bulkGenerateMeetingSummary(payload: {
    meeting_ids: number[];
    fields?: MeetingSummaryFieldName[];
    mode?: SummaryWriteMode;
    provider?: string | null;
    model?: string | null;
}) {
    return api.post<SummaryBulkResponse>(
        "/board-meetings/summary/bulk",
        payload,
    );
}

/**
 * Meeting documents (K4): scoped endpoints with capabilities advertised
 * by the backend — the UI only renders what the API allows.
 */
export function fetchMeetingDocuments(meetingId: number | string) {
    return api.get<MeetingDocumentsResponse>(
        `/board-meetings/${meetingId}/documents`,
    );
}

export function uploadMeetingDocument(
    meetingId: number | string,
    formData: FormData,
) {
    return api.post<{
        data: MeetingDocumentsResponse["data"][number];
        message: string;
    }>(`/board-meetings/${meetingId}/documents`, formData, {
        headers: FORM_DATA_HEADERS,
    });
}

/**
 * Import many مصوب in one pass. The response is a per-file report rather
 * than a single created resource, so a batch that mostly succeeded is 200
 * with the skipped/failed rows naming what was not written.
 */
export function uploadMeetingDocumentsBulk(
    meetingId: number | string,
    formData: FormData,
) {
    return api.post<{
        data: BulkDocumentUploadReport;
        message: string;
    }>(`/board-meetings/${meetingId}/documents/bulk`, formData, {
        headers: FORM_DATA_HEADERS,
    });
}

export function deleteMeetingDocument(
    meetingId: number | string,
    usageId: number,
) {
    return api.delete<{ message: string }>(
        `/board-meetings/${meetingId}/documents/${usageId}`,
    );
}
