import { useQuery } from "@tanstack/react-query";

import { api } from "@/lib/api";

export type QuestionnaireDocument = {
    id: number;
    uuid: string;
    original_name: string;
    mime_type: string;
    size: number;
    category_slug: string | null;
    record_key: string | null;
    slot: string | null;
    url: string;
};

export function useQuestionnaireDocuments(uuid: string | undefined) {
    const { data: documents = [], isLoading } = useQuery({
        queryKey: ["questionnaire-documents", uuid],
        queryFn: () =>
            api
                .get<{ data: QuestionnaireDocument[] }>(
                    `/questionnaire/${uuid}/documents`,
                )
                .then((r) => r.data.data),
        enabled: !!uuid,
    });

    function hasDocumentBySlug(slug: string, recordKey?: string): boolean {
        return documents.some(
            (d) =>
                d.category_slug === slug &&
                (recordKey ? d.record_key === recordKey : true),
        );
    }

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
        hasDocumentBySlug,
        getDocumentsBySlug,
        getDocumentsBySlugExcept,
    };
}
