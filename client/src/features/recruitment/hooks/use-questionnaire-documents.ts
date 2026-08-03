import { useQuery } from "@tanstack/react-query";

import { publicApi } from "@/lib/public-api";

export type QuestionnaireDocument = {
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

export function useQuestionnaireDocuments(uuid: string | undefined) {
    const { data: documents = [], isLoading } = useQuery({
        queryKey: ["questionnaire-documents", uuid],
        queryFn: () => {
            if (!uuid) {
                throw new Error("Questionnaire uuid is required.");
            }

            return publicApi
                .get<{ data: QuestionnaireDocument[] }>(
                    `/questionnaire/${uuid}/documents`,
                    {
                        grant: {
                            entity: "questionnaire",
                            uuid,
                            purpose: "view",
                        },
                    },
                )
                .then((r) => r.data.data);
        },
        enabled: !!uuid,
    });

    function getDocumentsBySlug(
        slug: string,
        recordKey?: string,
    ): QuestionnaireDocument[] {
        return documents.filter(
            (d) =>
                d.category_slug === slug &&
                (recordKey ? d.record_key === recordKey : true),
        );
    }

    function getDocumentsBySlugExcept(
        slug: string,
        excludedKeys: string[],
    ): QuestionnaireDocument[] {
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
