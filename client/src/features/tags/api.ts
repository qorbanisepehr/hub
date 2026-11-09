import { api, FORM_DATA_HEADERS } from "@/lib/api";
import type {
    AcceptTagMergePayload,
    AcceptTagMergeResponse,
    AttachTagsPayload,
    AttachTagsResponse,
    TagDto,
    TagListParams,
    TagListResponse,
    TagMergeProposalDto,
} from "./types";

export function fetchTags(params: TagListParams) {
    return api.get<TagListResponse>("/tags", { params });
}

/** Picker options (unpaginated, capped server-side). */
export function fetchTagOptions(search?: string, perPage?: number) {
    const params: Record<string, string> = {};
    if (search) params.filter = search;
    if (perPage) params.per_page = String(perPage);
    return api.get<{ data: TagDto[] }>("/tags/options", { params });
}

export function createTag(payload: { name: string; slug: string }) {
    return api.post<{ data: TagDto }>("/tags", payload);
}

/** Rename / re-slug — both halves are editable on update. */
export function updateTag(id: number, payload: { name: string; slug: string }) {
    return api.put<{ data: TagDto }>(`/tags/${id}`, payload);
}

export function deleteTag(id: number) {
    return api.delete<{ message: string }>(`/tags/${id}`);
}

/** Full sync semantics: the entity ends up carrying exactly `slugs`. */
export function attachTags(payload: AttachTagsPayload) {
    return api.post<AttachTagsResponse>("/tags/attach", payload);
}

/**
 * Start a consolidation run and get its id back (202).
 *
 * The model call happens on a worker, so this only says the run was accepted.
 * The proposal arrives by asking again — see `fetchTagMergeProposal`.
 */
export function proposeTagMerges() {
    return api.post<{ data: { run_id: string } }>("/tags/consolidate");
}

/**
 * The proposal for a run that has finished. A read, so it is a `GET`; the
 * server still answers with a header call, because the answer was never
 * stored anywhere a read could find it.
 */
export function fetchTagMergeProposal() {
    return api.get<{ data: TagMergeProposalDto }>("/tags/consolidate");
}

/** Apply the chosen groups: attachments move to the canonical tag, duplicates go. */
export function acceptTagMerges(payload: AcceptTagMergePayload) {
    return api.post<AcceptTagMergeResponse>("/tags/consolidate/accept", payload);
}

export function uploadMeetingDocument(meetingId: number | string, formData: FormData) {
    return api.post<{ data: EntityDocumentResponse }>(
        `/board-meetings/${meetingId}/documents`,
        formData,
        { headers: FORM_DATA_HEADERS },
    );
}

interface EntityDocumentResponse {
    id: number;
    usage_id: number;
    uuid: string;
}
