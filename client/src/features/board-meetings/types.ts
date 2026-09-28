import type { PaginatedResponse } from "@/lib/types";

/**
 * HTTP contracts for the board meetings endpoints (mirror the API
 * resources). The client always sends Gregorian `yyyy-MM-dd` windows;
 * `pdate` (Jalali, `yyyy-MM-dd` digits) is the canonical meeting key.
 */

export interface BoardMeetingTagDto {
    slug: string;
    name: string;
}

export interface BoardMeetingDto {
    id: number;
    /** Jalali `yyyy-MM-dd` (latin digits) — the canonical meeting key. */
    pdate: string;
    /** Gregorian `yyyy-MM-dd` equivalent. */
    date: string | null;
    /** «NNNN/ص/YYYY» form extracted from document filenames; null when none. */
    number: string | null;
    agenda: string | null;
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
    /** Whitelisted sort columns: `pdate` | `number`. */
    sort?: string;
    order?: "asc" | "desc";
    /** Global free-text search needle (pdate/number). */
    filter?: string;
    /** AND-filter: meetings carrying every listed slug. */
    tags?: string[];
    /** Gregorian `yyyy-MM-dd` window (optional). */
    date_from?: string;
    date_to?: string;
}

/** The detail endpoint returns the meeting inside the standard `data` envelope. */
export type BoardMeetingDetailResponse = BoardMeetingDto & {
    minutes_from_letters: string[] | null;
    minutes_from_heyat_modire: string[] | null;
};

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
