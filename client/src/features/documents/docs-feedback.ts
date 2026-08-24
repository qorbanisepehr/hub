import type {
    DocumentRequirementsEnvelope,
    DynamicDocumentRequirementGroup,
} from "./types";

/** A document category a section's rows require, with its display label. */
export type RowDocCategory = { slug: string; label: string };

/** Required pages still missing for one row placement. */
export type MissingRowDoc = {
    slug: string;
    label: string;
    min: number;
    count: number;
};

/**
 * The dynamic requirement group a section declared for its repeatable
 * rows, if any. Page counts always come from the server — never hardcoded
 * in feature code.
 */
export function resolveDynamicGroup(
    envelope: DocumentRequirementsEnvelope | null | undefined,
    sectionKey: string,
): DynamicDocumentRequirementGroup | undefined {
    return envelope?.dynamicGroups.find(
        (group) => group.section_key === sectionKey,
    );
}

/**
 * Required pages still missing for one row placement. `countFor` resolves
 * how many pages of a category are already attached to the placement.
 */
export function missingRowDocs(
    group: DynamicDocumentRequirementGroup | undefined,
    categories: RowDocCategory[],
    fieldKey: string | null,
    countFor: (slug: string) => number,
): MissingRowDoc[] {
    if (!group || !fieldKey) return [];

    return categories.flatMap(({ slug, label }) => {
        const min = group.requirements[slug]?.min_files ?? 0;
        if (min <= 0) return [];

        const count = countFor(slug);

        return count < min ? [{ slug, label, min, count }] : [];
    });
}

/**
 * Server-parity message for one missing category, e.g.
 * «کارت ملی: برای «فرزند 1» حداقل 2 فایل لازم است.»
 */
export function rowDocsMessage(
    categoryLabel: string,
    rowLabel: string,
    min: number,
): string {
    return `${categoryLabel}: برای «${rowLabel}» حداقل ${min} فایل لازم است.`;
}
