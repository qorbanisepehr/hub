import { useQuery } from "@tanstack/react-query";

import { api } from "@/lib/api";
import { publicApi } from "@/lib/public-api";
import { documentKeys } from "@/lib/query-keys";

export type EntityDocument = {
    id: number;
    usage_id: number;
    uuid: string;
    structure_name: string;
    structure_name_slug?: string;
    mime_type: string;
    size: number;
    category: { id: number; name: string; slug: string } | null;
    section_key: string | null;
    field_key: string | null;
    metadata: Record<string, unknown> | null;
    notes: string | null;
    deleted_at?: string | null;
    url: string;
    download_url?: string;
};

/**
 * Backend-authoritative set of actions the UI may offer for an entity's
 * documents. The client never derives these from the entity type — it only
 * renders what the API advertises.
 */
export type DocumentCapabilities = {
    view: boolean;
    download: boolean;
    upload: boolean;
    delete: boolean;
    replace: boolean;
    restore: boolean;
    force_delete: boolean;
    history: boolean;
    library_select: boolean;
};

const NO_CAPABILITIES: DocumentCapabilities = {
    view: false,
    download: false,
    upload: false,
    delete: false,
    replace: false,
    restore: false,
    force_delete: false,
    history: false,
    library_select: false,
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
    const { data, isLoading } = useQuery({
        queryKey: documentKeys.entityDocuments(entity, uuid),
        queryFn: () => {
            if (!uuid) {
                throw new Error(`${entity} uuid is required.`);
            }

            return client
                .get<{
                    data: EntityDocument[];
                    capabilities?: DocumentCapabilities;
                }>(`/${entity}/${uuid}/documents`, {
                    ...(isAuthedDocumentEntity(entity)
                        ? {}
                        : { grant: { entity, uuid, purpose: "view" } }),
                })
                .then((r) => r.data);
        },
        enabled: !!uuid,
    });

    const documents = data?.data ?? [];
    const capabilities = data?.capabilities ?? NO_CAPABILITIES;

    function getDocumentsBySlug(
        slug: string,
        fieldKey?: string,
    ): EntityDocument[] {
        return documents.filter(
            (d) =>
                d.category?.slug === slug &&
                (fieldKey ? d.field_key === fieldKey : true),
        );
    }

    function getDocumentsBySlugExcept(
        slug: string,
        excludedFieldKeys: string[],
    ): EntityDocument[] {
        return documents.filter(
            (d) =>
                d.category?.slug === slug &&
                d.field_key &&
                !excludedFieldKeys.includes(d.field_key),
        );
    }

    return {
        documents,
        isLoading,
        capabilities,
        getDocumentsBySlug,
        getDocumentsBySlugExcept,
    };
}
