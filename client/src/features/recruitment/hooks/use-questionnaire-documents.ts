import { useQuery } from "@tanstack/react-query";

import { api } from "@/lib/api";
import type { Document } from "@/features/documents/types";

export function useQuestionnaireDocuments(uuid: string | undefined) {
    const { data: documents = [], isLoading } = useQuery({
        queryKey: ["questionnaire-documents", uuid],
        queryFn: () =>
            api.get<{ data: Document[] }>(`/questionnaire/${uuid}/documents`).then((r) => r.data.data),
        enabled: !!uuid,
    });

    function matchMeta(doc: Document, recordKey?: string): boolean {
        const meta = doc.meta;
        if (recordKey) {
            return meta?.recordKey === recordKey;
        }
        return !meta?.recordKey;
    }

    function hasDocument(categoryId: number, recordKey?: string): boolean {
        return documents.some(
            (d) => d.document_category_id === categoryId && matchMeta(d, recordKey),
        );
    }

    function hasDocumentBySlug(slug: string, recordKey?: string): boolean {
        return documents.some(
            (d) => d.category?.slug === slug && matchMeta(d, recordKey),
        );
    }

    function getDocuments(categoryId: number, recordKey?: string): Document[] {
        return documents.filter(
            (d) => d.document_category_id === categoryId && matchMeta(d, recordKey),
        );
    }

    function getDocumentsBySlug(slug: string, recordKey?: string): Document[] {
        return documents.filter(
            (d) => d.category?.slug === slug && matchMeta(d, recordKey),
        );
    }

    function getAllDocumentsBySlug(slug: string): Document[] {
        return documents.filter((d) => d.category?.slug === slug);
    }

    function getDocumentsBySlugExcept(slug: string, excludedKeys: string[]): Document[] {
        return documents.filter(
            (d) => d.category?.slug === slug && d.record_key && !excludedKeys.includes(d.record_key),
        );
    }

    function countByRecordKey(categoryId: number): Map<string, number> {
        const map = new Map<string, number>();
        for (const doc of documents) {
            if (doc.document_category_id === categoryId) {
                const key = (doc.meta?.recordKey as string) ?? "_none";
                map.set(key, (map.get(key) ?? 0) + 1);
            }
        }
        return map;
    }

    return { documents, isLoading, hasDocument, hasDocumentBySlug, getDocuments, getDocumentsBySlug, getAllDocumentsBySlug, getDocumentsBySlugExcept, countByRecordKey };
}
