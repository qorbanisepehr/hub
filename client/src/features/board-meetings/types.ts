import type { PaginatedResponse } from "@/lib/types";

/**
 * HTTP contracts for the board meetings endpoints (mirror the API
 * resources). The client sends and receives Gregorian `yyyy-MM-dd`
 * dates (the canonical meeting key); Jalali rendering stays client-side.
 */

export interface BoardMeetingTagDto {
    slug: string;
    name: string;
}

export interface BoardMeetingAgendaItemDto {
    position: number;
    title: string;
}

export interface BoardMeetingAttendeeDto {
    name: string;
    role: string;
    is_present: boolean;
}

export interface BoardMeetingDto {
    id: number;
    /** Gregorian `yyyy-MM-dd` — the canonical meeting date. */
    date: string | null;
    title: string | null;
    status: "scheduled" | "held" | "archived";
    /** «NNNN/ص/YYYY» registry number; null when the archive has none. */
    number: string | null;
    documents: string[];
    meta: Record<string, unknown>;
    synced_at: string | null;
    tags: BoardMeetingTagDto[];
}

export type BoardMeetingListResponse = PaginatedResponse<BoardMeetingDto>;

/**
 * Standard list params — the same contract employees/audit/cartable
 * speak. Single-value filters with the `{id}_not` convention and the
 * optional `date_from`/`date_to` window. `tags` is an AND-filter (A16):
 * only meetings carrying every slug.
 */
export interface BoardMeetingListParams {
    page?: number;
    per_page?: number;
    /** Whitelisted sort columns: `date` | `number`. */
    sort?: string;
    order?: "asc" | "desc";
    /** Global free-text search needle (number/title). */
    filter?: string;
    /** AND-filter: meetings carrying every listed slug. */
    tags?: string[];
    /** Gregorian `yyyy-MM-dd` window (optional). */
    date_from?: string;
    date_to?: string;
}

/** The detail endpoint returns the meeting inside the standard `data` envelope. */
export type BoardMeetingDetailResponse = BoardMeetingDto & {
    minutes_text: string | null;
    agenda: BoardMeetingAgendaItemDto[];
    attendees: BoardMeetingAttendeeDto[];
    summary: Record<string, unknown> | null;
};

/** PUT payload — the UI-owned fields (importer-owned ones are server-only). */
export interface UpdateBoardMeetingPayload {
    title?: string | null;
    status?: BoardMeetingDto["status"];
    number?: string | null;
    minutes_text?: string | null;
}

/**
 * A document usage on a meeting (shape of the shared documentPayload from
 * the backend DocumentService — same contract as the CV/employee docs).
 */
export interface MeetingDocumentDto {
    id: number;
    usage_id: number;
    document_id: number;
    uuid: string;
    mime_type: string;
    size: number;
    category: { id: number; name: string; slug: string } | null;
    structure_name: string;
    structure_name_slug?: string;
    section_key: string | null;
    field_key: string | null;
    notes: string | null;
    deleted_at: string | null;
    url: string;
    download_url: string;
}

/** Backend-authoritative capability set for meeting documents. */
export interface MeetingDocumentCapabilities {
    view: boolean;
    download: boolean;
    upload: boolean;
    delete: boolean;
    replace: boolean;
    restore: boolean;
    force_delete: boolean;
    history: boolean;
    library_select: boolean;
}

export interface MeetingDocumentsResponse {
    data: MeetingDocumentDto[];
    capabilities: MeetingDocumentCapabilities;
}
