import type { PaginatedResponse } from "@/lib/types";

/**
 * HTTP contracts for the board meetings endpoints (mirror the API
 * resources). A meeting is identified by its registry key — the Jalali
 * date plus a sequence — so `held_on_jalali` is the stable identity and
 * `held_on` is the Gregorian canonical date every other hub domain uses.
 * Jalali *rendering* stays client-side off `held_on`, same as everywhere
 * else in the app.
 */

export interface BoardMeetingTagDto {
    slug: string;
    name: string;
}

export interface BoardMeetingApprovalDto {
    /** Registry number as the archive files it, e.g. `1001/ص/1404`. */
    number: string;
    title: string | null;
    /** Set once the approval PDF is in the hub Document system (F3). */
    document_id: number | null;
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
    held_on: string | null;
    /** Registry key, `yyyy-MM-dd` in the Jalali calendar. */
    held_on_jalali: string | null;
    /** Presentational form of the key, e.g. `1404/06/15 — جلسه 2`. */
    registry_label: string;
    sequence: number;
    title: string | null;
    approval_count: number;
    approvals: BoardMeetingApprovalDto[];
    attendee_count: number;
    tags: BoardMeetingTagDto[];
    /** Which source seeded the registry columns, e.g. `farzin`. */
    origin: string;
    imported_at: string | null;
    source_locked_at: string | null;
    /** A person has edited this row; a later sync will not overwrite it. */
    source_locked: boolean;
}

export type BoardMeetingListResponse = PaginatedResponse<BoardMeetingDto>;

/**
 * Standard list params — the same contract employees/audit/cartable
 * speak. Single-value filters with the `{id}_not` convention and the
 * optional `date_from`/`date_to` window. `tags` is an AND-filter:
 * only meetings carrying every slug. Free text matches the registry key
 * and the human title.
 */
export interface BoardMeetingListParams {
    page?: number;
    per_page?: number;
    /** Whitelisted sort columns: `held_on` | `sequence` | `imported_at`. */
    sort?: string;
    order?: "asc" | "desc";
    /** Global free-text search needle (registry key / title). */
    filter?: string;
    /** AND-filter: meetings carrying every listed slug. */
    tags?: string[];
    /** Gregorian `yyyy-MM-dd` window (optional). */
    date_from?: string;
    date_to?: string;
    /** Jalali year, `yyyy` (optional). */
    jalali_year?: string;
}

/** The detail endpoint returns the meeting inside the standard `data` envelope. */
export type BoardMeetingDetailResponse = BoardMeetingDto & {
    minutes: string | null;
    agenda: BoardMeetingAgendaItemDto[];
    attendees: BoardMeetingAttendeeDto[];
    /** Which source records produced this meeting (letters, attendance form). */
    provenance: Record<string, unknown>;
    /** Reserved for the AI summary (increment 2) — null until then. */
    summary: Record<string, unknown> | null;
};

/**
 * PUT payload — the human-owned fields only. The registry columns are
 * refreshed by a sync and `summary` belongs to the AI job, so neither is
 * accepted here. Tags go through the shared taggables endpoint.
 */
export interface UpdateBoardMeetingPayload {
    title?: string | null;
    minutes?: string | null;
    attendees?: BoardMeetingAttendeeDto[] | null;
}

/** What one registry sync actually moved. */
export interface BoardMeetingSyncResult {
    records: { synced: number; failed: number };
    meetings: { created: number; refreshed: number; locked: number };
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
