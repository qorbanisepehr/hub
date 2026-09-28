import { api, FORM_DATA_HEADERS } from "@/lib/api";
import type {
    AttachTagsPayload,
    AttachTagsResponse,
    TagDto,
    TagListParams,
    TagListResponse,
} from "./types";

export function fetchTags(params: TagListParams) {
    return api.get<TagListResponse>("/tags", { params });
}

/** Picker options (unpaginated, capped server-side). */
export function fetchTagOptions(search?: string) {
    const params: Record<string, string> = {};
    if (search) params.filter = search;
    return api.get<{ data: TagDto[] }>("/tags/options", { params });
}

export function createTag(payload: { name: string; slug: string }) {
    return api.post<{ data: TagDto }>("/tags", payload);
}

/** Rename only — the backend ignores `slug` on update (it is immutable). */
export function updateTag(id: number, payload: { name: string }) {
    return api.put<{ data: TagDto }>(`/tags/${id}`, payload);
}

export function deleteTag(id: number) {
    return api.delete<{ message: string }>(`/tags/${id}`);
}

/** Full sync semantics: the entity ends up carrying exactly `slugs`. */
export function attachTags(payload: AttachTagsPayload) {
    return api.post<AttachTagsResponse>("/tags/attach", payload);
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
