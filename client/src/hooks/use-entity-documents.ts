import { useQuery } from "@tanstack/react-query";

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
    url: string;
};

/**
 * Load the document usages attached to any grant-protected entity
 * (e.g. "questionnaire", "cv"). The query key is namespaced per entity so
 * invalidation stays isolated between features.
 */
export function useEntityDocuments(entity: string, uuid: string | undefined) {
    const { data: documents = [], isLoading } = useQuery({
        queryKey: [`${entity}-documents`, uuid],
        queryFn: () => {
            if (!uuid) {
                throw new Error(`${entity} uuid is required.`);
            }

            return publicApi
                .get<{ data: EntityDocument[] }>(`/${entity}/${uuid}/documents`, {
                    grant: { entity, uuid, purpose: "view" },
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
