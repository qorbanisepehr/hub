import type { PaginatedResponse } from "@/lib/types";

/**
 * HTTP contracts for the tagging endpoints (mirror the API resources).
 * Tags are the general tagging service (K3): `slug` is the stable key,
 * `name` the display label, `usage_count` the cross-domain attachment
 * count reported by the list endpoint.
 */

export interface TagDto {
    id: number;
    name: string;
    slug: string;
    usage_count?: number;
}

export type TagListResponse = PaginatedResponse<TagDto>;

/** Standard list params — the shared page/per_page/sort/order/filter contract. */
export interface TagListParams {
    page?: number;
    per_page?: number;
    /** Whitelisted sort columns: `name` | `usage_count`. */
    sort?: string;
    order?: "asc" | "desc";
    filter?: string;
}

export interface AttachTagsPayload {
    taggable_type: string;
    taggable_id: number | string;
    slugs: string[];
}

export interface AttachTagsResponse {
    tags: Array<{ slug: string; name: string }>;
}
