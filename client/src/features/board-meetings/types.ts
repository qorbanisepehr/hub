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
 * optional `date_from`/`date_to` window.
 */
export interface BoardMeetingListParams {
    page?: number;
    per_page?: number;
    /** Whitelisted sort columns: `pdate` | `number`. */
    sort?: string;
    order?: "asc" | "desc";
    /** Global free-text search needle (pdate/number). */
    filter?: string;
    /** Gregorian `yyyy-MM-dd` window (optional). */
    date_from?: string;
    date_to?: string;
}

/** The detail endpoint returns the item fields directly (no envelope). */
export type BoardMeetingDetailResponse = BoardMeetingDto & {
    minutes_from_letters: string[] | null;
    minutes_from_heyat_modire: string[] | null;
};
