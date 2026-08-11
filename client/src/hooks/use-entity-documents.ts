import { useQuery } from "@tanstack/react-query";

import { api } from "@/lib/api";
import { publicApi } from "@/lib/public-api";

export type EntityDocument = {
    id: number;
    usage_id: number;
    uuid: string;
    original_name: string;
    mime_type: string;
    size: number;
    category_slug: string | null;
    record_key: string | null;
    notes: string | null;
    deleted_at?: string | null;
    url: string;
    download_url?: string;
};

/**
 * Entities whose document API is protected by Sanctum auth instead of an access
 * grant. The entity value doubles as the API path prefix, so the auth model is
 * derived from it (questionnaire/cv use grant tokens, employees use the session).
 */
const AUTHED_DOCUMENT_ENTITIES = new Set(["employees"]);

export function isAuthedDocumentEntity(entity: string): boolean {
    return AUTHED_DOCUMENT_ENTITIES.has(entity);
}

/**
 * Load the document usages attached to any entity (grant-protected like
 * "questionnaire"/"cv", or auth-protected like "employees"). The query key is
 * namespaced per entity so invalidation stays isolated between features.
 */
export function useEntityDocuments(entity: string, uuid: string | undefined) {
    const client = isAuthedDocumentEntity(entity) ? api : publicApi;
    const { data: documents = [], isLoading } = useQuery({
        queryKey: [`${entity}-documents`, uuid],
        queryFn: () => {
            if (!uuid) {
                throw new Error(`${entity} uuid is required.`);
            }

            return client
                .get<{ data: EntityDocument[] }>(`/${entity}/${uuid}/documents`, {
                    ...(isAuthedDocumentEntity(entity)
                        ? {}
                        : { grant: { entity, uuid, purpose: "view" } }),
                })
                .then((r) => r.data.data);
        },
        enabled: !!uuid,
    });

    function getDocumentsBySlug(
        slug: string,
        recordKey?: string,
    ): EntityDocument[] {
        return documents.filter(
            (d) =>
                d.category_slug === slug &&
                (recordKey ? d.record_key === recordKey : true),
        );
    }

    function getDocumentsBySlugExcept(
        slug: string,
        excludedKeys: string[],
    ): EntityDocument[] {
        return documents.filter(
            (d) =>
                d.category_slug === slug &&
                d.record_key &&
                !excludedKeys.includes(d.record_key),
        );
    }

    return {
        documents,
        isLoading,
        getDocumentsBySlug,
        getDocumentsBySlugExcept,
    };
}
