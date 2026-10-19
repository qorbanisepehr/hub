import type { AxiosResponse } from "axios";

import { api, FORM_DATA_HEADERS } from "@/lib/api";
import type { ExportPresentation } from "@/components/shared";
import type {
    AcceptSummaryPayload,
    BoardMeetingDetailResponse,
    BoardMeetingListParams,
    BoardMeetingListResponse,
    BoardMeetingSyncResult,
    BundleFormat,
    BulkDocumentUploadReport,
    CancelSummaryBatchResponse,
    GenerateSummaryPayload,
    LiveSummaryBatch,
    MeetingDocumentsResponse,
    SummaryBulkPayload,
    SummaryBulkResponse,
    SummaryCandidateListResponse,
    SummaryCandidateParams,
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

/**
 * The printable form of ONE meeting — the detail page, on paper.
 *
 * Separate from `exportBoardMeetings` on purpose: that one is the registry as a
 * table through the tabular kernel, and a single meeting is not a table of one
 * row. This is the document kernel's path, and the route names its format
 * instead of taking a `?format=` guess.
 */
/**
 * ONE call for the whole printable sheet, in whatever format, with whichever
 * template and whichever sections.
 *
 * THIS REPLACES THREE NEAR-IDENTICAL FUNCTIONS, and the duplication had already
 * cost something: `exportBoardMeetingDocx` existed in BOTH this file and
 * `word-templates-api.ts`, with different signatures — one taking a template, one
 * not. An import that picked the wrong one compiled, type-checked and silently
 * dropped the template, which is the worst way for a duplicate to announce itself.
 *
 * The route still NAMES its format rather than taking a `?format=` guess: a `.docx`
 * is a file somebody can change and a `.pdf` is a thing they print, and the URL is
 * where that distinction is legible.
 *
 * `sections` and `template` are BOTH optional and BOTH per-request. Neither is
 * saved: "print this one differently, once" and "from now on" are different asks,
 * and only the first is answerable without a settings screen.
 */
export type BoardMeetingSheetFormat = "pdf" | "docx" | "md" | "json";

export function exportBoardMeetingSheet(
    id: number | string,
    options: {
        format: BoardMeetingSheetFormat;
        template?: string;
        sections?: string[];
    },
) {
    const { format, template, sections } = options;

    return api.get(`/board-meetings/${id}/export/${format}`, {
        responseType: "blob",
        params: {
            ...(template ? { template } : {}),
            // Sent only when the person actually chose, because an absent parameter
            // means "the usual sections" and an empty one means "print nothing" —
            // two different answers, and always both worth being explicit about.
            ...(sections ? { sections } : {}),
        },
    });
}

/**
 * The formats a bundle can be rendered in, with the server's labels.
 *
 * Fetched rather than listed here: a hardcoded list fails by omission, not by
 * error — the dialog would simply stop offering a format the server is happy to
 * produce, and nobody would notice until somebody looked for it.
 */
export function fetchBundleFormats() {
    return api.get<{ data: BundleFormat[] }>("/board-meetings/export/bundle/formats");
}

/**
 * How a bundle request ended: built now, or handed to the queue.
 *
 * Two outcomes rather than one response type, because the server genuinely has
 * two. A small bundle is zipped and streamed inside the request and arrives as
 * bytes; a large one is too big to hold a worker for, so it is built in the
 * background and the caller gets a token plus a notification when it is ready.
 * Folding this into a single "maybe a blob" would leave every caller guessing
 * which of the two it got.
 */
export type BundleResult =
    | { kind: "downloaded"; response: AxiosResponse<Blob> }
    | { kind: "queued"; token: string; meetingCount: number };

/**
 * Many meetings as one archive.
 *
 * With no `ids` the server covers everything the list endpoint can show under
 * the current filters, which is what the toolbar button means. Passing `ids`
 * narrows it to a selection, and the server refuses the whole request if any
 * named meeting has gone rather than quietly bundling fewer than were asked for.
 *
 * Resolved as a union rather than returned raw. The request asks for a blob, so
 * a queued answer arrives as a JSON document inside one — reading it here means
 * the dialog never has to know that a 202 comes back wearing a blob's clothes.
 */
export async function exportMeetingBundle(payload: {
    formats: string[];
    documents?: boolean;
    ids?: number[];
}): Promise<BundleResult> {
    const response = await api.post("/board-meetings/export/bundle", payload, {
        responseType: "blob",
    });

    if (response.status === 202) {
        const queued = JSON.parse(await (response.data as Blob).text()) as {
            data: { token: string; meeting_count: number };
        };

        return {
            kind: "queued",
            token: queued.data.token,
            meetingCount: queued.data.meeting_count,
        };
    }

    return { kind: "downloaded", response };
}

/**
 * Fetch a bundle that was built in the background.
 *
 * By token rather than by meeting, because the token is what identifies one
 * build: the meetings it covers were frozen when it was asked for, and a link
 * built from them would name a different archive whenever the filter changed.
 */
export function downloadQueuedBundle(token: string) {
    return api.get(`/board-meetings/export/bundle/${token}`, {
        responseType: "blob",
    });
}

export function exportBoardMeetingBundle(id: number | string) {
    return api.get(`/board-meetings/${id}/export/zip`, {
        responseType: "blob",
    });
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

/** The meetings a bulk run could act on, under the list's filters and scope. */
export function fetchSummaryCandidates(params: SummaryCandidateParams) {
    return api.get<SummaryCandidateListResponse>(
        "/board-meetings/summary/candidates",
        { params },
    );
}

export function bulkGenerateMeetingSummary(payload: SummaryBulkPayload) {
    return api.post<SummaryBulkResponse>(
        "/board-meetings/summary/bulk",
        payload,
    );
}

/**
 * Stop one meeting's run.
 *
 * A 409 means there was nothing stamped to stop — the run already reported, or
 * has not started yet — so the caller re-reads rather than retrying.
 */
export function cancelMeetingSummary(id: number | string) {
    return api.post<{ data: BoardMeetingDetailResponse }>(
        `/board-meetings/${id}/summary/cancel`,
    );
}

/** Stop every run a bulk dispatch has not reported on yet. */
export function cancelSummaryBatch(batchId: string) {
    return api.post<CancelSummaryBatchResponse>(
        `/board-meetings/summary/batches/${batchId}/cancel`,
    );
}

/**
 * The bulk runs still producing, so a page can offer to stop one it no longer
 * remembers dispatching.
 */
export function fetchLiveSummaryBatches() {
    return api.get<{ data: LiveSummaryBatch[] }>(
        "/board-meetings/summary/batches",
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
