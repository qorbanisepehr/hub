import { api, FORM_DATA_HEADERS } from "@/lib/api";
import type {
    BoardMeetingDetailResponse,
    BoardMeetingListParams,
    BoardMeetingListResponse,
    MeetingDocumentsResponse,
} from "./types";

export function fetchBoardMeetings(params: BoardMeetingListParams) {
    return api.get<BoardMeetingListResponse>("/board-meetings", { params });
}

export function fetchBoardMeeting(id: number | string) {
    return api.get<BoardMeetingDetailResponse>(`/board-meetings/${id}`);
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
    return api.post<{ data: MeetingDocumentsResponse["data"][number]; message: string }>(
        `/board-meetings/${meetingId}/documents`,
        formData,
        { headers: FORM_DATA_HEADERS },
    );
}

export function deleteMeetingDocument(
    meetingId: number | string,
    usageId: number,
) {
    return api.delete<{ message: string }>(
        `/board-meetings/${meetingId}/documents/${usageId}`,
    );
}
