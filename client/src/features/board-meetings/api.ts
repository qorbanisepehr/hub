import { api } from "@/lib/api";
import type {
    BoardMeetingDetailResponse,
    BoardMeetingListParams,
    BoardMeetingListResponse,
} from "./types";

export function fetchBoardMeetings(params: BoardMeetingListParams) {
    return api.get<BoardMeetingListResponse>("/board-meetings", { params });
}

export function fetchBoardMeeting(id: number | string) {
    return api.get<BoardMeetingDetailResponse>(`/board-meetings/${id}`);
}
