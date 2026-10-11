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

/**
 * The list filters plus the bulk picker's own scope.
 *
 * `regenerate` swaps the set the picker draws from — meetings that already
 * have a summary rather than the ones that do not — which is a different
 * question, not a filter on the same one.
 *
 * `meeting_ids` is the third way in and overrides both: the row picker has
 * already chosen a set and asks about exactly those, because a row ticked on an
 * earlier page is outside the list window and the registry rows carry no
 * summary to classify it by. The answer is the same list with an `action` per
 * row rather than a filtered one.
 */
export type SummaryCandidateParams = BoardMeetingListParams & {
    regenerate?: boolean;
    meeting_ids?: number[];
};

/**
 * The AI summary artifact. Keys are the server's contract (ADR-003), so this
 * mirrors `MeetingSummary` on the PHP side rather than renaming anything:
 * the client renders what the server stored and decides nothing about it.
 *
 * The five content keys are **optional**, and that is the shape's own
 * distinction rather than sloppiness: a field that was never generated is
 * absent from both the values and the per-field state map, while a field that
 * was asked about and came back empty is present with an empty value and a
 * `failed` state. `fields` is the authority on which is which — reading
 * `executive_summary` as `?? null` would flatten "never asked" into "came back
 * empty", which is the one distinction the whole per-field map exists to keep.
 */
export type MeetingSummaryFieldName =
    | "executive_summary"
    | "topics"
    | "bullet_points"
    | "key_decisions"
    | "suggested_tags";

export const MEETING_SUMMARY_FIELDS: readonly MeetingSummaryFieldName[] = [
    "executive_summary",
    "topics",
    "bullet_points",
    "key_decisions",
    "suggested_tags",
];

export type MeetingSummaryFieldStatus = "ok" | "failed";

export interface MeetingSummaryFieldState {
    status: MeetingSummaryFieldStatus;
    /** Why a field failed; a stable key, translated client-side. */
    error: string | null;
    /** Null when the field came back empty: what is on screen is not fresh. */
    generated_at: string | null;
}

/**
 * Row-level status. `none` is its own answer: the run asked and a person
 * accepted nothing, which is neither a failure nor a run still in flight.
 */
export type MeetingSummaryStatus =
    "pending" | "none" | "ok" | "partial" | "failed";

/** Why a run could not fill a field, or the row at all. Stable keys. */
export type MeetingSummaryReason =
    | "no_structured_output"
    | "no_fields_returned"
    | "not_configured"
    | "unavailable"
    | "abandoned"
    | "empty";

/**
 * One tag a run suggested: the Persian label people read, and the slug it is
 * stored and matched by.
 *
 * Two strings rather than one because the hub keeps two. A tag is identified
 * by its slug (filters, pivots, the edit dialog) but *read* by its name, and a
 * model handed a single string can only fill one of them — which is why tags
 * generated from the minutes alone came back as English slugs.
 */
export interface MeetingSuggestionTag {
    name: string;
    slug: string;
}

export interface MeetingSummaryArtifact {
    status: MeetingSummaryStatus;
    executive_summary?: string | null;
    topics?: string[];
    bullet_points?: string[];
    key_decisions?: string[];
    suggested_tags?: MeetingSuggestionTag[];
    fields: Partial<Record<MeetingSummaryFieldName, MeetingSummaryFieldState>>;
    /** A proposal awaiting a decision, in the same shape, or null. */
    pending: MeetingSummaryArtifact | null;
    /** The registry key the run was made under — always the same value. */
    agent: string | null;
    provider: string | null;
    model: string | null;
    usage: {
        input_tokens: number;
        output_tokens: number;
        total_tokens: number;
    } | null;
    error: MeetingSummaryReason | null;
    generated_at: string | null;
    /**
     * When the run behind a `pending` stamp began, else null.
     *
     * Written and cleared together with the stamp, and never reused for
     * anything else: `generated_at` dates the *content*, and a run in flight has
     * established none yet, so a reader that wants both facts needs both keys.
     */
    started_at?: string | null;
}

/** The detail endpoint returns the meeting inside the standard `data` envelope. */
export type BoardMeetingDetailResponse = BoardMeetingDto & {
    minutes: string | null;
    agenda: BoardMeetingAgendaItemDto[];
    attendees: BoardMeetingAttendeeDto[];
    /** Which source records produced this meeting (letters, attendance form). */
    provenance: Record<string, unknown>;
    /** The AI summary, or null when no run has ever been made. */
    summary: MeetingSummaryArtifact | null;
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

/** How a run reaches the record: propose it, or write it in. */
export type SummaryWriteMode = "review" | "apply";

/**
 * Ask for a run.
 *
 * `fields` absent or empty means every field.
 *
 * The per-run model choice is a `provider` and a `model` name rather than one
 * row id. The old `model_id` was a compromise that did not work: a name is
 * unique only within a provider — the same `gpt-4o` can sit under two of them —
 * and an id had to imply the provider to be unambiguous, which is exactly what
 * stopped a person choosing "this model, on that gateway". Naming both halves
 * says it, and a run whose names no longer resolve is refused here rather than
 * silently landing on a different model.
 *
 * Omitted (or null) means the agent's own binding, and then the registry
 * default. The two are set independently: naming a model with no provider is the
 * ordinary case, and naming a provider with no model is a deliberate choice of
 * gateway.
 */
export interface GenerateSummaryPayload {
    fields?: MeetingSummaryFieldName[];
    mode?: SummaryWriteMode;
    provider?: string | null;
    model?: string | null;
    /**
     * Reconcile the requested fields with what the record already holds,
     * instead of writing them fresh from the minutes. Off by default: a fresh
     * run is the ordinary request and merging is the deliberate one.
     */
    merge?: boolean;
}

/**
 * A decision about a pending proposal.
 *
 * `accepted_fields` is empty-means-none, the opposite of the generate
 * request's `fields` — one is an instruction to produce, the other a decision
 * about what exists, and the same key with two meanings is a trap worth two
 * names. `tag_slugs` is the meeting's tag set *in full*, and required: a
 * checkbox has no third state, so unticking a suggestion means "this meeting
 * is not tagged that" and the list has to say what it is instead. Sending the
 * current set unchanged is how a proposal is dismissed.
 */
export interface AcceptSummaryPayload {
    accepted_fields: MeetingSummaryFieldName[];
    tag_slugs: string[];
}

/**
 * What a bulk run would do with a candidate row, as the server classifies it.
 *
 * `blocked` is the answer for a row whose run is in flight, or whose `none`
 * status is a proposal somebody still has to decide. The picker lists it rather
 * than dropping it, so a ticked row that will not run says why instead of
 * disappearing.
 */
export type MeetingSummaryAction = "generate" | "regenerate" | "blocked";

/** One meeting in the bulk picker — identity only, never the minutes. */
export interface MeetingSummaryCandidateDto {
    id: number;
    held_on: string | null;
    held_on_jalali: string | null;
    registry_label: string;
    title: string | null;
    action: MeetingSummaryAction;
}

export interface SummaryCandidateListResponse extends PaginatedResponse<MeetingSummaryCandidateDto> {}

/**
 * The bulk dispatch body.
 *
 * Two groups rather than one list and a flag, because one dialog can hold both
 * kinds at once: the row picker preselects a mixed set and a person opts in to
 * replacing some of them. Each group is re-checked against the scope it was
 * chosen under, so a meeting that gained a summary in between is skipped
 * instead of silently regenerated.
 */
export interface SummaryBulkPayload {
    meeting_ids: number[];
    regenerate_ids: number[];
    fields?: MeetingSummaryFieldName[];
    mode?: SummaryWriteMode;
    provider?: string | null;
    model?: string | null;
}

/** What a bulk dispatch actually did, including what it declined to do. */
export interface SummaryBulkResult {
    queued: number;
    skipped: number;
    meeting_ids: number[];
}

export interface SummaryBulkResponse {
    data: SummaryBulkResult;
    message: string;
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
    /**
     * `registry_number`/`title`/`source_path` — where a meeting's مصوبات
     * numbers live (vault idea 567). The API emits it inline, in the same
     * shape the shared document worker reads.
     */
    metadata: Record<string, unknown> | null;
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

/** A bulk-upload row that was not written — the metadata names it. */
export interface BulkUploadRejectedRow {
    file: string;
    metadata: Record<string, unknown>;
    reason: string;
}

/**
 * Per-file report of the meeting bulk upload: imported entries carry the same
 * payloads as the list, and skipped/failed rows say what was not written and
 * why — a batch that mostly landed is a normal result, not an error.
 */
export interface BulkDocumentUploadReport {
    imported: MeetingDocumentDto[];
    skipped: BulkUploadRejectedRow[];
    failed: BulkUploadRejectedRow[];
}
