import { useCallback, useMemo } from "react";

import { useEntityDocuments } from "@/hooks/use-entity-documents";

import {
    missingRowDocs,
    resolveDynamicGroup,
    rowDocsMessage,
    type MissingRowDoc,
    type RowDocCategory,
} from "../docs-feedback";
import { useDocumentRequirements } from "./use-document-requirements";

type UseRowDocsFeedbackConfig<Row> = {
    /** Entity API segment, e.g. "employees". */
    entity: string;
    uuid: string | number | undefined;
    /** Section key owning the repeatable rows, e.g. "dependents". */
    sectionKey: string;
    /** Categories each row requires, with display labels. */
    categories: RowDocCategory[];
    /** Repeater placement builder, e.g. (index) => `dependent-${index}`. */
    fieldKeyFor?: (index: number) => string | null;
};

/**
 * Live required-page feedback for a section's repeatable rows. Reads the
 * server's dynamic requirement group plus the entity's attached documents
 * and computes, per row, which pages are still missing — shared through the
 * react-query caches, so every consumer (section, review tab, submit guard)
 * sees the same state without extra requests.
 */
export function useRowDocsFeedback<Row>(
    { entity, uuid, sectionKey, categories, fieldKeyFor }: UseRowDocsFeedbackConfig<Row>,
    rows: Row[],
    options?: {
        /** Skip feedback while a repeater edit is in flight. */
        enabled?: boolean;
        /** Human label of a row, used in messages; defaults to its index. */
        rowLabel?: (row: Row, index: number) => string;
    },
) {
    const { isLoading, getDocumentsBySlug } = useEntityDocuments(
        entity,
        uuid == null ? undefined : String(uuid),
    );
    const { data: envelope } = useDocumentRequirements(entity);

    const group = useMemo(
        () => resolveDynamicGroup(envelope, sectionKey),
        [envelope, sectionKey],
    );

    const getMissing = useCallback(
        (index: number): MissingRowDoc[] => {
            if (options?.enabled === false || isLoading || !group) return [];

            const fieldKey = fieldKeyFor?.(index) ?? `${sectionKey}-${index}`;

            return missingRowDocs(group, categories, fieldKey, (slug) =>
                getDocumentsBySlug(slug, fieldKey).length,
            );
        },
        [options?.enabled, isLoading, group, fieldKeyFor, sectionKey, categories, getDocumentsBySlug],
    );

    const messages = useMemo(() => {
        if (isLoading) return [];

        return rows.flatMap((row, index) => {
            const missing = getMissing(index);
            if (!missing.length) return [];

            const label = options?.rowLabel?.(row, index) ?? String(index + 1);

            return missing.map(({ label: categoryLabel, min }) =>
                rowDocsMessage(categoryLabel, label, min),
            );
        });
    }, [rows, getMissing, isLoading, options?.rowLabel]);

    return { isLoading, group, getMissing, messages };
}
